<?php
/**
 * Finds the published page holding a shortcode, so the account menu, emails and redirects can
 * link to it without anyone typing URLs. Ported from Sprint-Booking.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Pages {

	/** Every shortcode the plugin offers: label (also the "Create page" title) and what it shows. */
	public const TAGS = array(
		'pawfectly_reservation_form' => array( 'Pet Boarding Reservation', 'The reservation form, for everyone.' ),
		'pawfectly_user_menu'        => array( 'Account menu', 'The avatar and dropdown for your site header (an Elementor Shortcode widget in the header template).' ),
		'pawfectly_login'            => array( 'Login', 'Sign in and create account for everyone: owners, staff and admins. WordPress logins go here.' ),
		'pawfectly_my_profile'       => array( 'My Profile', 'Name, email, phone and password once signed in.' ),
		'pawfectly_my_bookings'      => array( 'My Bookings', "An owner's own reservations, with Cancel while allowed." ),
		'pawfectly_dashboard'        => array( 'Dashboard', 'The staff dashboard: Overview and Reservations. Fills the browser; add fullscreen="no" to keep it in the page column.' ),
	);

	private const TRANSIENT = 'paw_pg_';

	public static function init(): void {
		add_action( 'save_post_page', array( self::class, 'forget' ) );
		add_action( 'deleted_post', array( self::class, 'forget' ) );
		add_action( 'trashed_post', array( self::class, 'forget' ) );
		add_action( 'transition_post_status', array( self::class, 'forget' ) );
		// Elementor writes its page data after save_post has fired.
		add_action(
			'updated_post_meta',
			static function ( $meta_id, $post_id, $key ): void {
				if ( '_elementor_data' === $key ) {
					self::forget();
				}
			},
			10,
			3
		);
		add_action( 'admin_post_paw_create_page', array( self::class, 'handle_create' ) );
	}

	/** Permalink of the first published page containing [$tag], or '' when there is none. */
	public static function url( string $tag ): string {
		$key    = self::TRANSIENT . md5( $tag );
		$cached = get_transient( $key );
		if ( is_string( $cached ) ) {
			return $cached; // '' is remembered too, so a missing page is not looked up on every request.
		}

		global $wpdb;
		$like = '%' . $wpdb->esc_like( '[' . $tag ) . '%';
		// Plain SQL on purpose: site search plugins hook WP_Query's "s" and can change what it finds.
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
				 WHERE p.post_type = 'page' AND p.post_status = 'publish'
				   AND ( p.post_content LIKE %s OR m.meta_value LIKE %s )
				 ORDER BY p.ID ASC LIMIT 30",
				$like,
				$like
			)
		);

		$url = '';
		foreach ( (array) $ids as $id ) {
			$post = get_post( (int) $id );
			$text = $post ? (string) $post->post_content . ' ' . (string) get_post_meta( (int) $id, '_elementor_data', true ) : '';
			if ( 1 === preg_match( '/\[' . preg_quote( $tag, '/' ) . '(?![_a-z0-9])/', $text ) ) {
				$url = (string) get_permalink( (int) $id );
				break;
			}
		}
		set_transient( $key, $url, 5 * MINUTE_IN_SECONDS );
		return $url;
	}

	public static function forget(): void {
		foreach ( array_keys( self::TAGS ) as $tag ) {
			delete_transient( self::TRANSIENT . md5( $tag ) );
		}
	}

	/** admin-post.php?action=paw_create_page: a draft page holding one shortcode, then its editor. */
	public static function handle_create(): void {
		$tag = isset( $_POST['tag'] ) ? sanitize_key( wp_unslash( $_POST['tag'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'paw_create_page' ) || ! isset( self::TAGS[ $tag ] ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'pawfectly-reservations' ), '', array( 'response' => 403 ) );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => self::TAGS[ $tag ][0],
				'post_content' => '<!-- wp:shortcode -->[' . $tag . ']<!-- /wp:shortcode -->',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html__( 'The page could not be created.', 'pawfectly-reservations' ) );
		}
		wp_safe_redirect( get_edit_post_link( (int) $id, 'url' ) );
		exit;
	}
}
