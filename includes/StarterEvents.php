<?php
/**
 * Settings → Starter events: on adds 30 ready-made events (includes/data/starter-events.php),
 * off moves them to the trash, keeping any that people signed up for. They are ordinary events;
 * only the option paw_starter_events remembers which ones were added, so nothing public marks them.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class StarterEvents {

	public const OPTION = 'paw_starter_events';
	/** Held while one request adds the events (add_option() is an atomic insert). */
	private const LOCK = 'paw_starter_lock';
	/** Categories the starter events use, with their colours (an existing one with the same name is reused). */
	private const CATEGORIES = array(
		'Holiday party' => '#e8a33a',
		'Training'      => '#3b7597',
		'Social'        => '#06d6a0',
		'Adoption'      => '#8a5cd8',
		'Grooming'      => '#30afff',
		'Fundraiser'    => '#bc0202',
	);

	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/staff/starter-events',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'route' ),
				'permission_callback' => array( StaffRest::class, 'can_settings' ),
			)
		);
	}

	/** POST /staff/starter-events {on: bool} */
	public static function route( \WP_REST_Request $req ): \WP_REST_Response {
		$result = ! empty( $req->get_json_params()['on'] ) ? self::on() : self::off();
		return new \WP_REST_Response( $result + self::state() + array( 'message' => self::message( $result + self::state() ) ) );
	}

	/** @return array{on: bool, count: int} Whether they are on, and how many were added. */
	public static function state(): array {
		$saved = (array) get_option( self::OPTION, array() );
		return array(
			'on'    => ! empty( $saved['on'] ),
			'count' => count( (array) ( $saved['ids'] ?? array() ) ),
		);
	}

	/** The events, as Events::sanitize() input with a category name. */
	public static function data(): array {
		return require PAW_DIR . 'includes/data/starter-events.php';
	}

	/**
	 * Adds the events, skipping any kept from last time (people signed up for them). One run at
	 * a time: a second request while one is adding gets nothing. The IDs are saved as they are
	 * made, so a run cut short can still be turned off. @return array{added: int}
	 */
	public static function on(): array {
		if ( self::state()['on'] || ! add_option( self::LOCK, time(), '', false ) ) {
			return array( 'added' => 0 );
		}
		try {
			$kept  = self::kept();
			$have  = array_map( static fn( array $ev ): string => $ev['title'] . '|' . $ev['start'], $kept );
			$ids   = array_column( $kept, 'id' );
			$added = 0;
			$slugs = self::categories();
			foreach ( self::data() as $in ) {
				[ $sd, $st ] = explode( ' ', $in['start'] );
				[ $ed, $et ] = explode( ' ', $in['end'] );
				if ( in_array( $in['title'] . '|' . $sd . ' ' . $st . ':00', $have, true ) ) {
					continue; // Still here from last time.
				}
				$clean = Events::sanitize(
					array( 'start_date' => $sd, 'start_time' => $st, 'end_date' => $ed, 'end_time' => $et, 'status' => 'publish', 'category' => $slugs[ $in['category'] ] ?? '' )
					+ array_diff_key( $in, array_flip( array( 'start', 'end', 'category' ) ) )
				);
				$id = is_wp_error( $clean ) ? 0 : Events::save( $clean );
				if ( $id ) {
					$ids[] = $id;
					++$added;
					update_option( self::OPTION, array( 'on' => true, 'ids' => $ids ), false );
				}
			}
			update_option( self::OPTION, array( 'on' => true, 'ids' => $ids ), false );
			return array( 'added' => $added );
		} finally {
			delete_option( self::LOCK );
		}
	}

	/**
	 * Trashes the events, except those people signed up for: they stay, and are remembered so
	 * turning it on again doesn't add them twice. @return array{removed: int, kept: int}
	 */
	public static function off(): array {
		$removed = 0;
		$kept    = array();
		foreach ( (array) ( get_option( self::OPTION, array() )['ids'] ?? array() ) as $id ) {
			$id = (int) $id;
			$ev = Events::get( $id );
			if ( ! $ev || 'trash' === $ev['status'] ) {
				continue; // Already deleted by hand.
			}
			// Under the sign-up lock, so nobody signs up between the check and the trash.
			$trashed = Signups::locked(
				$id,
				static function () use ( $id ): bool {
					if ( Signups::for_event( $id, true ) ) {
						return false;
					}
					wp_trash_post( $id );
					return true;
				}
			);
			if ( true === $trashed ) {
				++$removed;
			} else {
				$kept[] = $id;
			}
		}
		update_option( self::OPTION, array( 'on' => false, 'ids' => array(), 'kept' => $kept ), false );
		return array( 'removed' => $removed, 'kept' => count( $kept ) );
	}

	/** Events kept last time (people signed up) that are still live. */
	private static function kept(): array {
		$out = array();
		foreach ( (array) ( get_option( self::OPTION, array() )['kept'] ?? array() ) as $id ) {
			$ev = Events::get( (int) $id );
			if ( $ev && 'trash' !== $ev['status'] ) {
				$out[] = $ev;
			}
		}
		return $out;
	}

	/** What the switch says after it flips. */
	public static function message( array $r ): string {
		if ( ! empty( $r['on'] ) ) {
			/* translators: %d: number of events */
			return sprintf( _n( '%d event added.', '%d events added.', (int) ( $r['added'] ?? $r['count'] ?? 0 ), 'pawfectly-reservations' ), (int) ( $r['added'] ?? $r['count'] ?? 0 ) );
		}
		/* translators: %d: number of events */
		$text = sprintf( _n( '%d event removed.', '%d events removed.', (int) ( $r['removed'] ?? 0 ), 'pawfectly-reservations' ), (int) ( $r['removed'] ?? 0 ) );
		if ( ! empty( $r['kept'] ) ) {
			/* translators: %d: number of events */
			$text .= ' ' . sprintf( _n( '%d kept because people signed up.', '%d kept because people signed up.', (int) $r['kept'], 'pawfectly-reservations' ), (int) $r['kept'] );
		}
		return $text;
	}

	/**
	 * The starter categories, adding the missing ones beside the others (a category with the same
	 * name, in any case, is reused as it is). @return array<string, string> Category name => slug.
	 */
	private static function categories(): array {
		$have = array();
		foreach ( Events::categories() as $c ) {
			$have[ mb_strtolower( $c['name'] ) ] = $c['slug'];
		}
		$slugs = array();
		foreach ( self::CATEGORIES as $name => $color ) {
			$slugs[ $name ] = $have[ mb_strtolower( $name ) ] ?? Events::add_category( $name, $color );
		}
		return $slugs;
	}
}
