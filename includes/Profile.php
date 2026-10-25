<?php
/**
 * [pawfectly_my_profile]: signed out, Sign in / Create account tabs for owners (staff are pointed
 * to the WordPress login); signed in, details and password forms for everyone.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Profile {

	public const TAG = 'pawfectly_my_profile';

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/** Styles, script and config shared by My Profile and My Bookings. */
	public static function enqueue(): void {
		wp_enqueue_style( 'paw-account', PAW_URL . 'assets/css/account.css', array(), PAW_VERSION );
		wp_enqueue_script( 'paw-account', PAW_URL . 'assets/js/account.js', array(), PAW_VERSION, true );
		wp_add_inline_script( 'paw-account', 'window.PAW_ACCOUNT = ' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	public static function config(): array {
		return array(
			'rest'  => rest_url( Rest::NS . '/' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'  => array(
				'failed'        => __( 'Something went wrong. Please try again.', 'pawfectly-reservations' ),
				'network'       => __( "We couldn't reach the server. Please try again.", 'pawfectly-reservations' ),
				'saving'        => __( 'Saving…', 'pawfectly-reservations' ),
				'confirmCancel' => __( 'Cancel this reservation? This cannot be undone online.', 'pawfectly-reservations' ),
			),
		);
	}

	/** The verification notices shown on both account pages. */
	public static function notices( int $user_id ): string {
		$html = '';
		$flag = isset( $_GET['paw_verified'] ) ? sanitize_key( wp_unslash( $_GET['paw_verified'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		if ( '1' === $flag ) {
			$html .= '<p class="paw-acct__notice">' . esc_html__( 'Thanks — your email is confirmed.', 'pawfectly-reservations' ) . '</p>';
		} elseif ( 'signin' === $flag ) {
			$html .= '<p class="paw-acct__notice">' . esc_html__( 'Sign in to confirm your email address.', 'pawfectly-reservations' ) . '</p>';
		} elseif ( 'wrong' === $flag ) {
			$html .= '<p class="paw-acct__notice paw-acct__notice--error">' . esc_html__( 'This confirmation link belongs to a different account. Log out, then open the link again.', 'pawfectly-reservations' ) . '</p>';
		} elseif ( '0' === $flag ) {
			$html .= '<p class="paw-acct__notice paw-acct__notice--error">' . esc_html__( 'That confirmation link has expired or was already used.', 'pawfectly-reservations' ) . '</p>';
		}
		if ( $user_id && ! Accounts::is_verified( $user_id ) ) {
			$html .= '<p class="paw-acct__notice paw-acct__notice--error">' . esc_html__( 'Please confirm your email address with the link we sent you.', 'pawfectly-reservations' )
				. ' <button type="button" class="paw-acct__link" data-paw-resend>' . esc_html__( 'Send a new link', 'pawfectly-reservations' ) . '</button></p>';
		}
		return $html;
	}

	public static function render(): string {
		self::enqueue();
		$user  = wp_get_current_user();
		$here  = (string) ( get_permalink() ?: home_url( '/' ) );
		$links = array(
			'lost'     => wp_lostpassword_url( $here ),
			'staff'    => wp_login_url( $here ),
			'bookings' => Pages::url( MyBookings::TAG ),
		);
		$phone = $user->exists() ? (string) get_user_meta( (int) $user->ID, Accounts::META_PHONE, true ) : '';

		ob_start();
		include PAW_DIR . 'templates/profile.php';
		return (string) ob_get_clean();
	}
}
