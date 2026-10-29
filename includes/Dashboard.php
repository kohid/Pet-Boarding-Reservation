<?php
/**
 * [pawfectly_dashboard view="overview|reservations" fullscreen="yes|no"]: the staff dashboard.
 * The HTML is a shell; all data arrives over StaffRest, which checks the capability on every request.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Dashboard {

	public const TAG   = 'pawfectly_dashboard';
	public const VIEWS = array( 'overview', 'reservations', 'events', 'settings' );

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/** @param array<string,string>|string $atts */
	public static function render( $atts = array() ): string {
		$a    = shortcode_atts( array( 'view' => 'overview', 'fullscreen' => 'yes' ), (array) $atts, self::TAG );
		$user = wp_get_current_user();
		$here = (string) ( get_permalink() ?: home_url( '/' ) );

		if ( ! $user->exists() ) {
			return '<div class="paw-dash-gate"><p>' . esc_html__( 'Staff only. Please sign in.', 'pawfectly-reservations' ) . '</p><p><a href="' . esc_url( Login::url_for( $here ) ) . '">' . esc_html__( 'Sign in', 'pawfectly-reservations' ) . '</a></p></div>';
		}
		if ( ! current_user_can( Activator::CAPABILITY ) ) {
			return '<div class="paw-dash-gate"><p>' . esc_html__( 'No access. This page is for Pawfectly staff.', 'pawfectly-reservations' ) . '</p></div>';
		}

		// The view in the address wins, so links and the browser's back button keep working.
		$asked = isset( $_GET['paw_view'] ) ? sanitize_key( wp_unslash( $_GET['paw_view'] ) ) : $a['view']; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
		$view  = in_array( $asked, self::VIEWS, true ) ? $asked : 'overview';
		$can_settings = Access::can_manage_settings( $user );
		if ( 'settings' === $view && ! $can_settings ) {
			$view = 'overview';
		}
		if ( current_user_can( 'upload_files' ) ) {
			wp_enqueue_media(); // The logo picker and event cover photos.
		}
		$lockout = $can_settings && StaffRest::lockout_risk();

		$config = self::config( $view );
		wp_enqueue_style( 'paw-dashboard', PAW_URL . 'assets/css/dashboard.css', array(), PAW_VERSION );
		wp_enqueue_script( 'paw-dashboard', PAW_URL . 'assets/js/dashboard.js', array(), PAW_VERSION, true );
		wp_enqueue_style( 'paw-emoji', PAW_URL . 'assets/css/emoji-picker.css', array(), PAW_VERSION );
		wp_enqueue_script( 'paw-emoji', PAW_URL . 'assets/js/emoji-picker.js', array(), PAW_VERSION, true );
		wp_enqueue_script( 'paw-events-admin', PAW_URL . 'assets/js/events-admin.js', array( 'paw-dashboard', 'paw-emoji' ), PAW_VERSION, true );
		wp_add_inline_script( 'paw-dashboard', 'window.PAW_DASH = ' . wp_json_encode( $config ) . ';', 'before' );

		$full       = 'no' !== $a['fullscreen'];
		$settings   = Settings::get();
		$logout_url = wp_logout_url( $here );

		ob_start();
		include PAW_DIR . 'templates/dashboard.php';
		return (string) ob_get_clean();
	}

	/** Everything dashboard.js needs. */
	public static function config( string $view ): array {
		$here     = (string) ( get_permalink() ?: home_url( '/' ) );
		$settings = Settings::get();
		$logo     = $settings['logo_id'] ? wp_get_attachment_image_url( (int) $settings['logo_id'], 'thumbnail' ) : '';
		$statuses = array();
		foreach ( array_keys( Reservations::STATUSES ) as $key ) {
			$statuses[ $key ] = Reservations::label( $key );
		}
		return array(
			'rest'     => rest_url( Rest::NS . '/' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'view'     => $view,
			'brand'    => array( 'name' => $settings['business_name'], 'logo' => $logo ? $logo : PAW_URL . 'assets/img/paw.svg' ),
			'statuses' => $statuses,
			'urls'     => array(
				'overview'     => add_query_arg( array( 'paw_view' => 'overview' ), $here ),
				'reservations' => add_query_arg( array( 'paw_view' => 'reservations' ), $here ),
				'events'       => add_query_arg( array( 'paw_view' => 'events' ), $here ),
				'settings'     => Access::can_manage_settings( wp_get_current_user() ) ? add_query_arg( array( 'paw_view' => 'settings' ), $here ) : '',
				// admin-post.php is open to staff (Access lets it through); the export checks the capability and nonce.
				// A plain URL (wp_nonce_url() would HTML-escape it, which breaks it as an href set from JavaScript).
				'export'       => add_query_arg( array( 'action' => 'paw_export', '_wpnonce' => wp_create_nonce( 'paw_export' ) ), admin_url( 'admin-post.php' ) ),
			),
			'events'   => array(
				'limits'     => Events::LIMITS,
				'palette'    => Events::PALETTE,
				'defaults'   => Events::defaults()['sections'],
				'categories' => Events::categories(),
				'i18n'       => array(
					'addEvent'      => __( 'Add Event', 'pawfectly-reservations' ),
					'editEvent'     => __( 'Edit event', 'pawfectly-reservations' ),
					'empty'         => __( 'No events here yet. Add one to get started.', 'pawfectly-reservations' ),
					'draft'         => __( 'Draft', 'pawfectly-reservations' ),
					'published'     => __( 'Published', 'pawfectly-reservations' ),
					'publish'       => __( 'Publish', 'pawfectly-reservations' ),
					'saveDraft'     => __( 'Save draft', 'pawfectly-reservations' ),
					'toDraft'       => __( 'Switch to draft', 'pawfectly-reservations' ),
					'update'        => __( 'Update', 'pawfectly-reservations' ),
					'saved'         => __( 'Saved.', 'pawfectly-reservations' ),
					'view'          => __( 'View', 'pawfectly-reservations' ),
					'duplicate'     => __( 'Duplicate', 'pawfectly-reservations' ),
					'confirmDelete' => __( 'Delete this event? It goes to the WordPress trash.', 'pawfectly-reservations' ),
					'unsaved'       => __( 'You have unsaved changes. Leave anyway?', 'pawfectly-reservations' ),
					'choosePhoto'   => __( 'Choose cover photo', 'pawfectly-reservations' ),
					'noCategory'    => __( 'No category', 'pawfectly-reservations' ),
					'categoriesSaved' => __( 'Categories saved.', 'pawfectly-reservations' ),
				),
			),
			'i18n'     => array(
				'overview'      => __( 'Overview', 'pawfectly-reservations' ),
				'reservations'  => __( 'Reservations', 'pawfectly-reservations' ),
				'events'        => __( 'Events', 'pawfectly-reservations' ),
				'settings'      => __( 'Settings', 'pawfectly-reservations' ),
				'settingsSaved' => __( 'Settings saved.', 'pawfectly-reservations' ),
				'chooseLogo'    => __( 'Choose logo', 'pawfectly-reservations' ),
				'all'           => __( 'All', 'pawfectly-reservations' ),
				'loading'       => __( 'Loading…', 'pawfectly-reservations' ),
				'noFiles'       => __( 'No files.', 'pawfectly-reservations' ),
				'pdf'           => __( 'PDF', 'pawfectly-reservations' ),
				/* translators: 1: image number, 2: number of images */
				'imageOf'       => __( '%1$d of %2$d', 'pawfectly-reservations' ),
				'failed'        => __( 'Something went wrong. Please try again.', 'pawfectly-reservations' ),
				'empty'         => __( 'No reservations match.', 'pawfectly-reservations' ),
				'nights'        => __( 'nights', 'pawfectly-reservations' ),
				'night'         => __( 'night', 'pawfectly-reservations' ),
				'saved'         => __( 'Saved', 'pawfectly-reservations' ),
				'confirmDelete' => __( 'Delete this reservation and its files permanently? This cannot be undone.', 'pawfectly-reservations' ),
				/* translators: 1: first row number, 2: last row number, 3: total */
				'showing'       => __( '%1$d–%2$d of %3$d', 'pawfectly-reservations' ),
				/* translators: %d: number of pets */
				'petsNight'     => __( '%d pets', 'pawfectly-reservations' ),
			),
		);
	}
}
