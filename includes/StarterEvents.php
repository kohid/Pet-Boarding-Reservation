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

	/** @return array{added: int} */
	public static function on(): array {
		if ( self::state()['on'] ) {
			return array( 'added' => 0 );
		}
		$slugs = self::categories();
		$ids   = array();
		foreach ( self::data() as $in ) {
			[ $sd, $st ] = explode( ' ', $in['start'] );
			[ $ed, $et ] = explode( ' ', $in['end'] );
			$clean       = Events::sanitize(
				array( 'start_date' => $sd, 'start_time' => $st, 'end_date' => $ed, 'end_time' => $et, 'status' => 'publish', 'category' => $slugs[ $in['category'] ] ?? '' )
				+ array_diff_key( $in, array_flip( array( 'start', 'end', 'category' ) ) )
			);
			$id = is_wp_error( $clean ) ? 0 : Events::save( $clean );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		update_option( self::OPTION, array( 'on' => true, 'ids' => $ids ), false );
		return array( 'added' => count( $ids ) );
	}

	/** @return array{removed: int, kept: int} Kept: events people signed up for stay as they are. */
	public static function off(): array {
		$removed = 0;
		$kept    = 0;
		foreach ( (array) ( get_option( self::OPTION, array() )['ids'] ?? array() ) as $id ) {
			$id = (int) $id;
			$ev = Events::get( $id );
			if ( ! $ev || 'trash' === $ev['status'] ) {
				continue; // Already deleted by hand.
			}
			if ( Signups::for_event( $id, true ) ) {
				++$kept;
				continue;
			}
			wp_trash_post( $id );
			++$removed;
		}
		update_option( self::OPTION, array( 'on' => false, 'ids' => array() ), false );
		return array( 'removed' => $removed, 'kept' => $kept );
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
	 * Adds the starter categories that are missing (matched by name, any case) beside the ones
	 * already there. @return array<string, string> Category name => slug.
	 */
	private static function categories(): array {
		$have = Events::categories();
		$by   = static fn( array $list ): array => array_combine( array_map( 'mb_strtolower', array_column( $list, 'name' ) ), array_column( $list, 'slug' ) ) ?: array();
		$add  = array();
		foreach ( self::CATEGORIES as $name => $color ) {
			if ( ! isset( $by( $have )[ mb_strtolower( $name ) ] ) ) {
				$add[] = array( 'name' => $name, 'color' => $color );
			}
		}
		if ( $add ) {
			$saved = Events::save_categories( array_merge( $have, $add ) );
			$have  = is_wp_error( $saved ) ? Events::categories() : $saved;
		}
		$slugs = array();
		foreach ( array_keys( self::CATEGORIES ) as $name ) {
			$slugs[ $name ] = $by( $have )[ mb_strtolower( $name ) ] ?? '';
		}
		return $slugs;
	}
}
