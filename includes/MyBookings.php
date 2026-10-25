<?php
/**
 * [pawfectly_my_bookings]: an owner's own reservations, with Cancel while allowed.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class MyBookings {

	public const TAG = 'pawfectly_my_bookings';

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/** Owners may cancel online while a reservation is pending or confirmed and check-in is still ahead. */
	public static function can_cancel( array $r, string $today ): bool {
		return in_array( $r['status'], array( 'pending', 'confirmed' ), true ) && $r['check_in'] > $today;
	}

	public static function render(): string {
		Profile::enqueue();
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			$sign_in = Login::url_for( (string) ( get_permalink() ?: home_url( '/' ) ) );
			return '<div class="paw-acct">' . Profile::notices( 0 ) . '<div class="paw-acct__card"><p>' . esc_html__( 'Sign in to see your reservations.', 'pawfectly-reservations' ) . '</p><p><a class="paw-acct__btn" href="' . esc_url( $sign_in ) . '">' . esc_html__( 'Sign in', 'pawfectly-reservations' ) . '</a></p></div></div>';
		}

		$verified = Accounts::is_verified( (int) $user->ID );
		$rows     = Reservations::for_owner( (int) $user->ID, (string) $user->user_email, $verified );
		$today    = ( new \DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		$book_url = Pages::url( Shortcode::TAG );

		ob_start();
		include PAW_DIR . 'templates/my-bookings.php';
		return (string) ob_get_clean();
	}
}
