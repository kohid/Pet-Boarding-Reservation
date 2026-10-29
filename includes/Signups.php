<?php
/**
 * Event sign-ups: private paw_ev_signup posts with their details in meta (_paw_su_*).
 * The only place that reads and writes them. A sign-up keeps the price and session label
 * it was made with, so later edits to the event never change it.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Signups {

	public const TYPE     = 'paw_ev_signup';
	public const MAX_DOGS = 5;
	public const STATUSES = array( 'confirmed', 'attended', 'cancelled' );

	private const META = '_paw_su_';
	private const KEYS = array( 'event', 'session', 'session_label', 'status', 'reference', 'first_name', 'last_name', 'email', 'phone', 'user_id', 'dogs', 'dog_count', 'coupon', 'price_each', 'total', 'notes', 'created', 'ip' );

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		register_post_type(
			self::TYPE,
			array(
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'rewrite'      => false,
				'query_var'    => false,
				'supports'     => array( 'title' ),
			)
		);
	}

	/** The event's sessions as the form lists them: "10:00 AM – 12:00 PM · Session 1: Small dogs". */
	public static function session_labels( array $event ): array {
		return array_map(
			static fn( array $row ): string => EventDates::times( $row['from'], $row['to'] ) . ' · ' . implode( ': ', array_filter( array( $row['name'], $row['who'] ), 'strlen' ) ),
			(array) ( $event['sections']['sessions']['rows'] ?? array() )
		);
	}

	/**
	 * Checks a sign-up for an event. Spots and duplicates are checked against what is stored now.
	 *
	 * @return array|\WP_Error The clean sign-up, or paw_invalid with fields keyed like "dogs.1.name".
	 */
	public static function sanitize( array $event, array $in ) {
		$need   = __( 'This field is required.', 'pawfectly-reservations' );
		$errors = array();
		$out    = array();

		foreach ( array( 'first_name' => 60, 'last_name' => 60, 'phone' => 30 ) as $key => $max ) {
			$out[ $key ] = mb_substr( sanitize_text_field( (string) ( $in[ $key ] ?? '' ) ), 0, $max );
			if ( '' === $out[ $key ] ) {
				$errors[ $key ] = $need;
			}
		}
		if ( ! isset( $errors['phone'] ) && strlen( (string) preg_replace( '/\D/', '', $out['phone'] ) ) < 7 ) {
			$errors['phone'] = __( 'Please enter a valid phone number.', 'pawfectly-reservations' );
		}
		$out['email'] = strtolower( trim( sanitize_email( (string) ( $in['email'] ?? '' ) ) ) );
		if ( '' === $out['email'] ) {
			$errors['email'] = $need;
		} elseif ( ! is_email( $out['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'pawfectly-reservations' );
		}
		// Keep the field order of the form for the error list.
		$errors = array_merge( array_intersect_key( $errors, array( 'first_name' => 1, 'last_name' => 1 ) ), array_intersect_key( $errors, array( 'email' => 1 ) ), array_intersect_key( $errors, array( 'phone' => 1 ) ) );

		$labels         = self::session_labels( $event );
		$out['session'] = -1;
		if ( $labels ) {
			$raw = trim( (string) ( $in['session'] ?? '' ) );
			if ( '' === $raw ) {
				$errors['session'] = __( 'Please pick a session.', 'pawfectly-reservations' );
			} elseif ( ! ctype_digit( $raw ) || ! isset( $labels[ (int) $raw ] ) ) {
				$errors['session'] = __( 'Please pick one of the sessions.', 'pawfectly-reservations' );
			} else {
				$out['session'] = (int) $raw;
			}
		}
		$out['session_label'] = $labels[ $out['session'] ] ?? '';

		$out['dogs'] = array();
		foreach ( array_values( is_array( $in['dogs'] ?? null ) ? $in['dogs'] : array() ) as $dog ) {
			$dog = is_array( $dog ) ? $dog : array();
			$d   = array(
				'name'   => mb_substr( sanitize_text_field( (string) ( $dog['name'] ?? '' ) ), 0, 60 ),
				'breed'  => mb_substr( sanitize_text_field( (string) ( $dog['breed'] ?? '' ) ), 0, 60 ),
				'weight' => mb_substr( sanitize_text_field( (string) ( $dog['weight'] ?? '' ) ), 0, 20 ),
			);
			if ( '' === implode( '', $d ) ) {
				continue; // A dog card left blank.
			}
			if ( '' === $d['name'] ) {
				$errors[ 'dogs.' . count( $out['dogs'] ) . '.name' ] = $need;
			}
			$out['dogs'][] = $d;
		}
		$out['dog_count'] = count( $out['dogs'] );
		if ( 0 === $out['dog_count'] ) {
			$errors['dogs'] = __( 'Please add at least one dog.', 'pawfectly-reservations' );
		} elseif ( $out['dog_count'] > self::MAX_DOGS ) {
			/* translators: %d: most dogs per sign-up */
			$errors['dogs'] = sprintf( __( 'Up to %d dogs per sign-up.', 'pawfectly-reservations' ), self::MAX_DOGS );
		}

		$code          = trim( sanitize_text_field( (string) ( $in['coupon'] ?? '' ) ) );
		$out['coupon'] = false;
		if ( '' !== $code && ! $event['free'] ) {
			if ( '' !== (string) $event['coupon'] && 0 === strcasecmp( $code, (string) $event['coupon'] ) ) {
				$out['coupon'] = true;
			} else {
				$errors['coupon'] = __( "That code isn't valid for this event.", 'pawfectly-reservations' );
			}
		}

		if ( empty( $in['agree'] ) ) {
			$errors['agree'] = __( 'Please confirm to continue.', 'pawfectly-reservations' );
		}
		$out['notes'] = mb_substr( sanitize_textarea_field( (string) ( $in['notes'] ?? '' ) ), 0, 500 );

		// Prices: a snapshot of what the owner saw.
		$out['price_each'] = $event['free'] ? '0.00' : (string) $event['price'];
		$out['total']      = ( $event['free'] || $out['coupon'] ) ? '0.00'
			: ( '' === $out['price_each'] ? '' : number_format( (float) $out['price_each'] * $out['dog_count'], 2, '.', '' ) );

		// One active sign-up per email per event.
		if ( ! isset( $errors['email'] ) && array_filter( self::for_event( (int) $event['id'], true ), static fn( array $su ): bool => $su['email'] === $out['email'] ) ) {
			$errors['email'] = __( 'This email is already signed up for this event. Check your inbox for the confirmation.', 'pawfectly-reservations' );
		}

		// Spots, checked last against what is stored right now.
		$left = self::spots_left( $event, $out['session'] );
		if ( null !== $left && ! isset( $errors['session'] ) && $out['dog_count'] > 0 && $out['dog_count'] <= self::MAX_DOGS && $out['dog_count'] > $left ) {
			$key            = $labels ? 'session' : 'dogs';
			$errors[ $key ] = 0 === $left
				? ( $labels ? __( 'Sorry, that session just filled up. Please pick another.', 'pawfectly-reservations' ) : __( 'Sorry, this event is full.', 'pawfectly-reservations' ) )
				/* translators: %d: spots left */
				: sprintf( _n( 'Only %d spot left in this session.', 'Only %d spots left in this session.', $left, 'pawfectly-reservations' ), $left );
		}

		if ( $errors ) {
			return new \WP_Error( 'paw_invalid', __( 'Please check the highlighted fields.', 'pawfectly-reservations' ), array( 'status' => 400, 'fields' => $errors ) );
		}
		return $out;
	}

	/** Stores a checked sign-up as Confirmed; returns it as get() does. */
	public static function create( array $event, array $clean, int $user_id, string $ip ): array {
		$reference = Reference::generate( 'EVT' );
		for ( $i = 0; $i < 5 && self::reference_exists( $reference ); $i++ ) {
			$reference = Reference::generate( 'EVT' );
		}
		$id   = (int) wp_insert_post(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_title'  => $reference,
			)
		);
		$data = $clean + array(
			'event'     => (int) $event['id'],
			'status'    => 'confirmed',
			'reference' => $reference,
			'user_id'   => $user_id,
			'created'   => gmdate( 'Y-m-d H:i:s' ),
			'ip'        => $ip,
		);
		foreach ( self::KEYS as $key ) {
			update_post_meta( $id, self::META . $key, is_bool( $data[ $key ] ) ? (int) $data[ $key ] : $data[ $key ] );
		}
		return (array) self::get( $id );
	}

	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::TYPE !== $post->post_type ) {
			return null;
		}
		$su = array( 'id' => $id );
		foreach ( self::KEYS as $key ) {
			$su[ $key ] = get_post_meta( $id, self::META . $key, true );
		}
		foreach ( array( 'event', 'session', 'user_id', 'dog_count' ) as $key ) {
			$su[ $key ] = (int) $su[ $key ];
		}
		$su['coupon'] = (bool) $su['coupon'];
		$su['dogs']   = array_values( (array) $su['dogs'] );
		foreach ( array( 'status', 'reference', 'first_name', 'last_name', 'email', 'phone', 'session_label', 'price_each', 'total', 'notes', 'created' ) as $key ) {
			$su[ $key ] = (string) $su[ $key ];
		}
		return $su;
	}

	/** @return list<array> The event's sign-ups, oldest first. */
	public static function for_event( int $event, bool $active_only = false ): array {
		$list = self::query( array( array( 'key' => self::META . 'event', 'value' => (string) $event, 'compare' => '=' ) ) );
		return $active_only ? array_values( array_filter( $list, static fn( array $su ): bool => 'cancelled' !== $su['status'] ) ) : $list;
	}

	/** @return list<array> An owner's sign-ups, oldest first. */
	public static function for_user( int $user_id ): array {
		return $user_id ? self::query( array( array( 'key' => self::META . 'user_id', 'value' => (string) $user_id, 'compare' => '=' ) ) ) : array();
	}

	/** Dogs on active sign-ups for one session (-1: an event without sessions). */
	public static function taken( int $event, int $session ): int {
		return (int) array_sum( array_column( array_filter( self::for_event( $event, true ), static fn( array $su ): bool => $su['session'] === $session ), 'dog_count' ) );
	}

	/** Spots left in a session, or null when the event has no limit. */
	public static function spots_left( array $event, int $session ): ?int {
		$spots = (int) ( $event['spots'] ?? 0 );
		return $spots > 0 ? max( 0, $spots - self::taken( (int) $event['id'], $session ) ) : null;
	}

	public static function set_status( int $id, string $status ): bool {
		if ( ! in_array( $status, self::STATUSES, true ) || ! self::get( $id ) ) {
			return false;
		}
		update_post_meta( $id, self::META . 'status', $status );
		return true;
	}

	/** Owners may cancel a confirmed sign-up until the event starts. */
	public static function can_cancel( array $su, array $event, ?\DateTimeImmutable $now = null ): bool {
		$now = $now ?? new \DateTimeImmutable( 'now', wp_timezone() );
		return 'confirmed' === $su['status'] && $now->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) < $event['start'];
	}

	/** "2 × $15 = $30, paid at the event", "Free with member code", "Free", or "Price on request". */
	public static function total_text( array $su ): string {
		if ( '' === $su['total'] ) {
			return __( 'Price on request', 'pawfectly-reservations' );
		}
		if ( '0.00' === $su['total'] ) {
			return $su['coupon'] ? __( 'Free with member code', 'pawfectly-reservations' ) : __( 'Free', 'pawfectly-reservations' );
		}
		/* translators: 1: number of dogs, 2: price each, 3: total */
		return sprintf( __( '%1$d × %2$s = %3$s, paid at the event', 'pawfectly-reservations' ), $su['dog_count'], Services::money( (float) $su['price_each'] ), Services::money( (float) $su['total'] ) );
	}

	/** @return list<list<string>> A header, then one row per dog. */
	public static function csv_rows( int $event ): array {
		$rows = array( array( 'Reference', 'Status', 'First name', 'Last name', 'Email', 'Phone', 'Session', 'Dog', 'Breed', 'Weight', 'Member code', 'Total', 'Notes', 'Signed up (UTC)' ) );
		foreach ( self::for_event( $event ) as $su ) {
			foreach ( $su['dogs'] as $n => $dog ) {
				$rows[] = array( $su['reference'], $su['status'], $su['first_name'], $su['last_name'], $su['email'], $su['phone'], $su['session_label'], $dog['name'], $dog['breed'], $dog['weight'], $su['coupon'] ? 'yes' : '', 0 === $n ? $su['total'] : '', 0 === $n ? $su['notes'] : '', $su['created'] );
			}
		}
		return $rows;
	}

	private static function query( array $meta ): array {
		$ids = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
			)
		);
		return array_values( array_filter( array_map( static fn( $id ): ?array => self::get( (int) $id ), $ids ) ) );
	}

	private static function reference_exists( string $reference ): bool {
		return (bool) get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'title' => $reference, 'fields' => 'ids', 'posts_per_page' => 1 ) );
	}
}
