<?php
/**
 * [pawfectly_login]: the one sign-in and create-account page for everyone (owners, staff and
 * admins). WordPress's own wp-login.php hands plain logins and registrations over to it, while
 * logout, password reset and the other wp-login actions keep working there.
 *
 * Turn the hand-over off with the `paw_redirect_wp_login` filter (for example for a two-factor
 * plugin that needs wp-login.php) or with PAW_WP_ADMIN_OPEN.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Login {

	public const TAG = 'pawfectly_login';

	/** wp-login.php actions that move to the login page; every other action stays on wp-login.php. */
	private const TAKEN = array( 'login', 'register' );

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
		add_action( 'login_init', array( self::class, 'handle_wp_login' ) );
		add_filter( 'login_url', array( self::class, 'login_url' ), 10, 2 );
	}

	public static function url(): string {
		return Pages::url( self::TAG );
	}

	/** The login page (or wp-login.php when there is none), returning to $redirect_to afterwards. */
	public static function url_for( string $redirect_to ): string {
		$page = self::url();
		if ( '' === $page ) {
			return wp_login_url( $redirect_to );
		}
		return '' !== $redirect_to ? add_query_arg( array( 'redirect_to' => $redirect_to ), $page ) : $page;
	}

	/** Where someone goes after signing in when nothing else was asked for. */
	public static function home_for( \WP_User $u ): string {
		$dashboard = user_can( $u, Activator::CAPABILITY ) ? Pages::url( Dashboard::TAG ) : '';
		if ( '' !== $dashboard ) {
			return $dashboard;
		}
		$bookings = Pages::url( MyBookings::TAG );
		return '' !== $bookings ? $bookings : home_url( '/' );
	}

	/** A same-site redirect_to, else the user's home. */
	public static function target( string $redirect_to, \WP_User $u ): string {
		$safe = '' !== $redirect_to ? (string) wp_validate_redirect( $redirect_to, '' ) : '';
		return '' !== $safe ? $safe : self::home_for( $u );
	}

	private static function takes_over(): bool {
		return ! Access::open() && '' !== self::url() && (bool) apply_filters( 'paw_redirect_wp_login', true );
	}

	/** The login-page address for a wp-login.php request, or null to leave wp-login.php alone. */
	public static function wp_login_redirect( array $get, string $method ): ?string {
		$action = is_string( $get['action'] ?? null ) ? $get['action'] : 'login';
		if ( 'GET' !== strtoupper( $method ) || ! in_array( $action, self::TAKEN, true ) || isset( $get['interim-login'] ) || ! self::takes_over() ) {
			return null;
		}
		$args = array();
		if ( is_string( $get['redirect_to'] ?? null ) && '' !== $get['redirect_to'] ) {
			$args['redirect_to'] = $get['redirect_to'];
		}
		if ( 'register' === $action ) {
			$args['tab'] = 'signup';
		}
		return $args ? add_query_arg( $args, self::url() ) : self::url();
	}

	public static function handle_wp_login(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$to     = self::wp_login_redirect( wp_unslash( $_GET ), $method ); // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
		if ( null !== $to ) {
			wp_safe_redirect( $to );
			exit;
		}
	}

	/** wp_login_url() — used by WordPress and by plugins — points to the login page. */
	public static function login_url( $url, $redirect = '' ): string {
		return self::takes_over() ? self::url_for( (string) $redirect ) : (string) $url;
	}

	/** The Show/Hide button on password boxes (booking form and account pages). */
	public static function password_toggle(): void {
		wp_enqueue_script( 'paw-password', PAW_URL . 'assets/js/password.js', array(), PAW_VERSION, true );
		wp_add_inline_script(
			'paw-password',
			'window.PAW_PW = ' . wp_json_encode(
				array(
					'show' => __( 'Show password', 'pawfectly-reservations' ),
					'hide' => __( 'Hide password', 'pawfectly-reservations' ),
				)
			) . ';',
			'before'
		);
	}

	/** The sign-in / create-account tabs. */
	public static function panel( string $redirect_to = '' ): string {
		Profile::enqueue();
		$asked       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		$start       = 'signup' === $asked ? 'signup' : 'signin';
		$redirect_to = (string) wp_validate_redirect( $redirect_to, '' );
		$lost_url    = wp_lostpassword_url( '' !== $redirect_to ? $redirect_to : (string) ( get_permalink() ?: home_url( '/' ) ) );

		ob_start();
		include PAW_DIR . 'templates/login.php';
		return (string) ob_get_clean();
	}

	/** @param array<string,string>|string $atts */
	public static function render( $atts = array() ): string {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			$asked = isset( $_GET['redirect_to'] ) ? (string) wp_unslash( $_GET['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- checked by wp_validate_redirect.
			return self::panel( $asked );
		}
		Profile::enqueue();
		return '<div class="paw-acct"><div class="paw-acct__card"><p>'
			/* translators: %s: the signed-in user's name */
			. esc_html( sprintf( __( "You're signed in as %s.", 'pawfectly-reservations' ), $user->display_name ) )
			. '</p><div class="paw-acct__actions"><a class="paw-acct__btn" href="' . esc_url( self::home_for( $user ) ) . '">' . esc_html__( 'Continue', 'pawfectly-reservations' ) . '</a>'
			. '<a class="paw-acct__link" href="' . esc_url( wp_logout_url( self::url_for( '' ) ) ) . '">' . esc_html__( 'Log Out', 'pawfectly-reservations' ) . '</a></div></div></div>';
	}
}
