<?php
/**
 * Events: the paw_event post type, its category taxonomy, and the only place that reads and
 * writes an event's fields (post meta prefixed _paw_ev_).
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Events {

	public const TYPE     = 'paw_event';
	public const TAX      = 'paw_event_cat';
	public const SECTIONS = array( 'sessions', 'includes', 'goodies', 'good_to_know', 'closing' );
	/** Rows each repeatable section may hold. */
	public const LIMITS = array(
		'sessions'     => 8,
		'includes'     => 20,
		'goodies'      => 12,
		'good_to_know' => 20,
	);
	/** Category colours; the first is also the colour of an event with no category. */
	public const PALETTE = array( '#ff7f50', '#3b7597', '#06d6a0', '#0f42c0', '#bc0202', '#30afff', '#e8a33a', '#8a5cd8' );

	private const META      = '_paw_ev_';
	private const KEYS      = array( 'start', 'end', 'tagline', 'emoji', 'venue', 'address', 'price', 'free', 'note', 'ticket_url', 'sections' );
	private const TEXT_MAX  = array(
		'title'   => 120,
		'tagline' => 160,
		'venue'   => 120,
		'address' => 200,
		'note'    => 200,
	);
	private const ROW_MAX   = 300;
	private const EMOJI_MAX = 64; // Bytes: room for the longest single emoji (joined, toned sequences).
	/** Row fields per section, and which of them a row must have. */
	private const ROWS = array(
		'sessions'     => array( array( 'from', 'to', 'name', 'who' ), array( 'from', 'to' ) ),
		'includes'     => array( array( 'emoji', 'text' ), array( 'text' ) ),
		'goodies'      => array( array( 'emoji', 'title', 'text' ), array( 'title' ) ),
		'good_to_know' => array( array( 'emoji', 'text' ), array( 'text' ) ),
	);

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'init', array( self::class, 'maybe_flush' ), 99 );
	}

	/** Refreshes the rewrite rules once per plugin version, so /event/<slug>/ works after an update. */
	public static function maybe_flush(): void {
		if ( get_option( 'paw_rewrite_version' ) !== PAW_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'paw_rewrite_version', PAW_VERSION );
		}
	}

	public static function register(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Events', 'pawfectly-reservations' ),
					'singular_name' => __( 'Event', 'pawfectly-reservations' ),
				),
				'public'       => true,
				'show_ui'      => false, // Staff edit events in the Pawfectly dashboard.
				'show_in_rest' => false,
				'has_archive'  => false,
				// Singular, so a site page at /events/ keeps working.
				'rewrite'      => array( 'slug' => 'event', 'with_front' => false ),
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
			)
		);
		register_taxonomy(
			self::TAX,
			self::TYPE,
			array(
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'hierarchical' => false,
			)
		);
	}

	/** An empty event, with the default section headings. */
	public static function defaults(): array {
		return array(
			'title'       => '',
			'tagline'     => '',
			'emoji'       => '',
			'category'    => '',
			'start'       => '',
			'end'         => '',
			'venue'       => '',
			'address'     => '',
			'price'       => '',
			'free'        => false,
			'note'        => '',
			'description' => '',
			'ticket_url'  => '',
			'status'      => 'draft',
			'photo_id'    => 0,
			'sections'    => array(
				'sessions'     => array(
					'heading' => __( "Pick Your Pup's Session", 'pawfectly-reservations' ),
					'intro'   => __( 'Two sessions keep playtime safe and fun for every size — choose yours when you register.', 'pawfectly-reservations' ),
					'rows'    => array(),
				),
				'includes'     => array(
					'heading' => __( 'Everything Your {price} Ticket Includes', 'pawfectly-reservations' ),
					'intro'   => __( 'One ticket, a whole morning of perks for you and your pup.', 'pawfectly-reservations' ),
					'rows'    => array(),
				),
				'goodies'      => array(
					'heading' => __( "Extra Goodies You'll Love", 'pawfectly-reservations' ),
					'rows'    => array(),
				),
				'good_to_know' => array(
					'heading' => __( 'Good to Know', 'pawfectly-reservations' ),
					'rows'    => array(),
				),
				'closing'      => array(
					'heading' => __( 'Reserve Your Spot Today!', 'pawfectly-reservations' ),
					'intro'   => __( "Register in seconds — we can't wait to meet your pup!", 'pawfectly-reservations' ),
				),
			),
		);
	}

	/**
	 * Checks and cleans an event as the dashboard posts it (dates as start_date + start_time).
	 *
	 * @return array|\WP_Error The clean event, or paw_invalid with fields keyed like
	 *                         "end" or "sections.sessions.1.from".
	 */
	public static function sanitize( array $in ) {
		$out    = self::defaults();
		$errors = array();
		$long   = __( 'Please keep this under %d characters.', 'pawfectly-reservations' );
		$need   = __( 'This field is required.', 'pawfectly-reservations' );

		foreach ( self::TEXT_MAX as $key => $max ) {
			$out[ $key ] = sanitize_text_field( (string) ( $in[ $key ] ?? '' ) );
			if ( mb_strlen( $out[ $key ] ) > $max ) {
				$errors[ $key ] = sprintf( $long, $max );
			}
		}
		if ( '' === $out['title'] ) {
			$errors['title'] = $need;
		}

		foreach ( array( 'start', 'end' ) as $key ) {
			$at = self::moment( (string) ( $in[ $key . '_date' ] ?? '' ), (string) ( $in[ $key . '_time' ] ?? '' ) );
			if ( null === $at ) {
				$errors[ $key ] = $need;
			} else {
				$out[ $key ] = $at;
			}
		}
		if ( ! isset( $errors['start'] ) && ! isset( $errors['end'] ) && $out['end'] <= $out['start'] ) {
			$errors['end'] = __( 'The end must be after the start.', 'pawfectly-reservations' );
		}
		if ( '' === $out['venue'] && ! isset( $errors['venue'] ) ) {
			$errors['venue'] = $need;
		}

		$out['emoji'] = self::emoji( (string) ( $in['emoji'] ?? '' ) );
		if ( strlen( $out['emoji'] ) > self::EMOJI_MAX ) {
			$errors['emoji'] = __( 'Please choose one emoji.', 'pawfectly-reservations' );
		}

		$out['free'] = ! empty( $in['free'] );
		if ( ! $out['free'] ) {
			$price = Services::clean_price( $in['price'] ?? '' );
			if ( null === $price ) {
				$errors['price'] = __( 'Enter an amount like 15 or 15.50, or leave it blank.', 'pawfectly-reservations' );
			} else {
				$out['price'] = $price;
			}
		}

		$out['ticket_url']  = esc_url_raw( (string) ( $in['ticket_url'] ?? '' ) );
		$out['description'] = trim( wp_kses( (string) ( $in['description'] ?? '' ), Settings::WAIVER_TAGS + array( 'a' => array( 'href' => true ) ) ) );
		$out['status']      = 'publish' === ( $in['status'] ?? '' ) ? 'publish' : 'draft';
		$out['category']    = self::category_slug( (string) ( $in['category'] ?? '' ) );
		$out['photo_id']    = absint( $in['photo_id'] ?? 0 );

		$sections = is_array( $in['sections'] ?? null ) ? $in['sections'] : array();
		foreach ( self::SECTIONS as $name ) {
			$sent = is_array( $sections[ $name ] ?? null ) ? $sections[ $name ] : array();
			foreach ( array( 'heading', 'intro' ) as $part ) {
				if ( array_key_exists( $part, $out['sections'][ $name ] ) && isset( $sent[ $part ] ) ) {
					$out['sections'][ $name ][ $part ] = mb_substr( sanitize_text_field( (string) $sent[ $part ] ), 0, self::ROW_MAX );
				}
			}
			if ( isset( self::ROWS[ $name ] ) ) {
				$out['sections'][ $name ]['rows'] = self::rows( $name, is_array( $sent['rows'] ?? null ) ? $sent['rows'] : array(), $errors );
			}
		}

		if ( $errors ) {
			return new \WP_Error( 'paw_invalid', __( 'Please check the highlighted fields.', 'pawfectly-reservations' ), array( 'status' => 400, 'fields' => $errors ) );
		}
		return $out;
	}

	/** Inserts or updates an event from sanitize() output; returns its post id. */
	public static function save( array $clean, int $id = 0 ): int {
		$post = array(
			'post_type'    => self::TYPE,
			'post_title'   => $clean['title'],
			'post_content' => $clean['description'],
			'post_status'  => $clean['status'],
		);
		$id = $id ? (int) wp_update_post( $post + array( 'ID' => $id ) ) : (int) wp_insert_post( $post );
		if ( ! $id ) {
			return 0;
		}
		foreach ( self::KEYS as $key ) {
			update_post_meta( $id, self::META . $key, $clean[ $key ] );
		}
		wp_set_object_terms( $id, '' !== $clean['category'] ? array( $clean['category'] ) : array(), self::TAX );
		if ( $clean['photo_id'] ) {
			set_post_thumbnail( $id, $clean['photo_id'] );
		} else {
			delete_post_thumbnail( $id );
		}
		return $id;
	}

	/** One event with everything the pages need, or null when $id is not an event. */
	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::TYPE !== $post->post_type ) {
			return null;
		}
		$ev = self::defaults();
		foreach ( self::KEYS as $key ) {
			$v = get_post_meta( $id, self::META . $key, true );
			if ( '' !== $v && null !== $v ) {
				$ev[ $key ] = 'sections' === $key ? array_replace_recursive( $ev['sections'], (array) $v ) : $v;
			}
		}
		// Rows come back as saved, never merged with defaults.
		foreach ( array_keys( self::ROWS ) as $name ) {
			$ev['sections'][ $name ]['rows'] = array_values( (array) ( ( (array) get_post_meta( $id, self::META . 'sections', true ) )[ $name ]['rows'] ?? array() ) );
		}
		$terms             = get_the_terms( $id, self::TAX ); // Cached, unlike wp_get_object_terms().
		$term              = is_array( $terms ) ? ( $terms[0] ?? null ) : null;
		$ev['free']        = (bool) $ev['free'];
		$ev['title']       = (string) $post->post_title;
		$ev['description'] = (string) $post->post_content;
		$ev['status']      = (string) $post->post_status;
		$ev['id']          = $id;
		$ev['url']         = (string) get_permalink( $post );
		$ev['photo_id']    = (int) get_post_thumbnail_id( $id );
		$ev['photo_url']   = $ev['photo_id'] ? (string) wp_get_attachment_image_url( $ev['photo_id'], 'large' ) : '';
		$ev['category']    = $term ? (string) $term->slug : '';
		$ev['category_name'] = $term ? (string) $term->name : '';
		$ev['color']       = $term ? self::color( (int) $term->term_id ) : self::PALETTE[0];
		return $ev;
	}

	/**
	 * Events overlapping [$from 00:00, $to 23:59:59], sorted by start.
	 *
	 * @param string[] $statuses
	 * @return list<array>
	 */
	public static function between( string $from, string $to, string $q = '', string $cat = '', array $statuses = array( 'publish' ) ): array {
		$args = array(
			'post_type'      => self::TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => -1,
			'meta_key'       => self::META . 'start', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'AND',
				array( 'key' => self::META . 'start', 'value' => $to . ' 23:59:59', 'compare' => '<=' ),
				array( 'key' => self::META . 'end', 'value' => $from . ' 00:00:00', 'compare' => '>=' ),
			),
		);
		if ( '' !== $q ) {
			$args['s'] = $q; // WP_Query escapes LIKE wildcards in searches.
		}
		if ( '' !== $cat ) {
			$args['tax_query'] = array( array( 'taxonomy' => self::TAX, 'field' => 'slug', 'terms' => array( $cat ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		// Whole posts, so WordPress primes their meta and term caches in one go.
		return array_values( array_filter( array_map( static fn( $p ): ?array => self::get( (int) $p->ID ), get_posts( $args ) ) ) );
	}

	/** upcoming (not started), current (in progress) or past (ended), in the site's timezone. */
	public static function status( array $ev, ?\DateTimeImmutable $now = null ): string {
		$now = $now ?? new \DateTimeImmutable( 'now', wp_timezone() );
		$at  = $now->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
		if ( $at < $ev['start'] ) {
			return 'upcoming';
		}
		return $at > $ev['end'] ? 'past' : 'current';
	}

	/** Copies an event as a draft titled "… (copy)"; returns the new id. */
	public static function duplicate( int $id ): int {
		$ev = self::get( $id );
		if ( ! $ev ) {
			return 0;
		}
		/* translators: %s: event title */
		$ev['title']  = sprintf( __( '%s (copy)', 'pawfectly-reservations' ), $ev['title'] );
		$ev['status'] = 'draft';
		return self::save( $ev );
	}

	/** @return list<array{slug:string,name:string,color:string}> */
	public static function categories(): array {
		$terms = get_terms( array( 'taxonomy' => self::TAX, 'hide_empty' => false ) );
		return is_array( $terms ) ? array_map(
			static fn( $t ): array => array( 'slug' => (string) $t->slug, 'name' => (string) $t->name, 'color' => self::color( (int) $t->term_id ) ),
			$terms
		) : array();
	}

	/**
	 * Replaces the category list: entries with a slug are renamed and recoloured, entries
	 * without one are created, and categories left out are deleted.
	 *
	 * @param array $list [{slug?, name, color}]
	 * @return list<array>|\WP_Error The saved categories, or paw_invalid with fields keyed "categories.N.name".
	 */
	public static function save_categories( array $list ) {
		$clean  = array();
		$errors = array();
		foreach ( array_values( $list ) as $n => $c ) {
			$c    = is_array( $c ) ? $c : array();
			$name = mb_substr( sanitize_text_field( (string) ( $c['name'] ?? '' ) ), 0, 40 );
			if ( '' === $name ) {
				$errors[ "categories.$n.name" ] = __( 'This field is required.', 'pawfectly-reservations' );
			}
			$clean[] = array(
				'slug'  => self::category_slug( (string) ( $c['slug'] ?? '' ) ),
				'name'  => $name,
				'color' => in_array( $c['color'] ?? '', self::PALETTE, true ) ? $c['color'] : self::PALETTE[0],
			);
		}
		if ( $errors ) {
			return new \WP_Error( 'paw_invalid', __( 'Please check the highlighted fields.', 'pawfectly-reservations' ), array( 'status' => 400, 'fields' => $errors ) );
		}
		$keep = array();
		foreach ( $clean as $c ) {
			$term = '' !== $c['slug'] ? get_term_by( 'slug', $c['slug'], self::TAX ) : false;
			if ( $term ) {
				wp_update_term( (int) $term->term_id, self::TAX, array( 'name' => $c['name'] ) );
				$id = (int) $term->term_id;
			} else {
				$made = wp_insert_term( $c['name'], self::TAX, array( 'slug' => self::new_slug( $c['name'] ) ) );
				if ( is_wp_error( $made ) ) {
					// A category with this name already exists: keep it rather than delete it below.
					if ( 'term_exists' !== $made->get_error_code() ) {
						continue;
					}
					$id = (int) $made->get_error_data();
				} else {
					$id = (int) $made['term_id'];
				}
			}
			update_term_meta( $id, '_paw_color', $c['color'] );
			$keep[] = $id;
		}
		foreach ( get_terms( array( 'taxonomy' => self::TAX, 'hide_empty' => false ) ) as $t ) {
			if ( ! in_array( (int) $t->term_id, $keep, true ) ) {
				wp_delete_term( (int) $t->term_id, self::TAX );
			}
		}
		return self::categories();
	}

	private static function color( int $term_id ): string {
		$c = (string) get_term_meta( $term_id, '_paw_color', true );
		return in_array( $c, self::PALETTE, true ) ? $c : self::PALETTE[0];
	}

	/** 'Y-m-d H:i:s' from a date and a time, or null when either is not valid. */
	private static function moment( string $date, string $time ): ?string {
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', trim( $date ) . ' ' . trim( $time ) );
		return $d && $d->format( 'Y-m-d H:i' ) === trim( $date ) . ' ' . trim( $time ) ? $d->format( 'Y-m-d H:i:s' ) : null;
	}

	/**
	 * Emoji only (joined sequences, skin tones, flags, keycaps); anything else becomes ''.
	 * Explicit code point ranges, not the Unicode emoji property: PCRE2 before 10.40 (PHP 8.0, 8.1)
	 * cannot compile that property, and the emoji would be dropped without a word.
	 */
	private static function emoji( string $v ): string {
		$v = trim( $v );
		$ok = preg_match(
			'/^(?:[#*0-9]\x{FE0F}?\x{20E3}|[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{2934}\x{2935}\x{3030}\x{303D}\x{3297}\x{3299}\x{00A9}\x{00AE}\x{2122}\x{2139}\x{203C}\x{2049}\x{200D}\x{FE0F}\x{20E3}\x{E0020}-\x{E007F}])+$/u',
			$v
		);
		return 1 === $ok ? $v : '';
	}

	/** An existing category's slug, or '' (never creates one). */
	private static function category_slug( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		$term = get_term_by( 'slug', $raw, self::TAX );
		return $term ? (string) $term->slug : '';
	}

	/** A plain ASCII slug for a new category ("🎄 Holiday" → "holiday"), unique in the taxonomy. */
	private static function new_slug( string $name ): string {
		$base = function_exists( 'remove_accents' ) ? remove_accents( $name ) : $name;
		$base = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $base ) ), '-' );
		$base = '' !== $base ? substr( $base, 0, 40 ) : 'category';
		$slug = $base;
		for ( $n = 2; get_term_by( 'slug', $slug, self::TAX ); $n++ ) {
			$slug = $base . '-' . $n;
		}
		return $slug;
	}

	/** The section's rows: blank rows dropped, each field checked; errors keyed by row. */
	private static function rows( string $name, array $rows, array &$errors ): array {
		[ $fields, $required ] = self::ROWS[ $name ];
		$out                   = array();
		foreach ( array_values( $rows ) as $row ) {
			$row   = is_array( $row ) ? $row : array();
			$clean = array();
			foreach ( $fields as $f ) {
				$clean[ $f ] = 'emoji' === $f ? self::emoji( (string) ( $row[ $f ] ?? '' ) ) : sanitize_text_field( (string) ( $row[ $f ] ?? '' ) );
			}
			if ( '' === implode( '', array_diff_key( $clean, array( 'emoji' => 1 ) ) ) ) {
				continue; // A row left blank is not a row.
			}
			$n = count( $out );
			foreach ( $fields as $f ) {
				$at = "sections.$name.$n.$f";
				if ( in_array( $f, $required, true ) && '' === $clean[ $f ] ) {
					$errors[ $at ] = __( 'This field is required.', 'pawfectly-reservations' );
				} elseif ( in_array( $f, array( 'from', 'to' ), true ) && '' !== $clean[ $f ] && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $clean[ $f ] ) ) {
					$errors[ $at ] = __( 'Please enter a valid time.', 'pawfectly-reservations' );
				} elseif ( mb_strlen( $clean[ $f ] ) > self::ROW_MAX ) {
					/* translators: %d: maximum number of characters */
					$errors[ $at ] = sprintf( __( 'Please keep this under %d characters.', 'pawfectly-reservations' ), self::ROW_MAX );
				} elseif ( 'emoji' === $f && strlen( $clean[ $f ] ) > self::EMOJI_MAX ) {
					$errors[ $at ] = __( 'Please choose one emoji.', 'pawfectly-reservations' );
				}
			}
			if ( 'sessions' === $name && ! isset( $errors[ "sections.$name.$n.from" ], $errors[ "sections.$name.$n.to" ] ) && $clean['to'] <= $clean['from'] ) {
				$errors[ "sections.$name.$n.to" ] = __( 'The end must be after the start.', 'pawfectly-reservations' );
			}
			$out[] = $clean;
		}
		if ( count( $out ) > self::LIMITS[ $name ] ) {
			/* translators: %d: maximum number of rows */
			$errors[ "sections.$name" ] = sprintf( __( 'Up to %d rows.', 'pawfectly-reservations' ), self::LIMITS[ $name ] );
		}
		return $out;
	}
}
