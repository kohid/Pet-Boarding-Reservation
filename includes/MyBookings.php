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

	/**
	 * The owner's event sign-ups as My Bookings lists them, the soonest event first; sign-ups
	 * whose event is gone are left out.
	 */
	public static function signup_rows( int $user_id, ?\DateTimeImmutable $now = null ): array {
		$rows = array();
		foreach ( Signups::for_user( $user_id ) as $su ) {
			$event = Events::get( $su['event'] );
			if ( ! $event ) {
				continue;
			}
			$rows[] = array(
				'id'         => $su['id'],
				'reference'  => $su['reference'],
				'status'     => $su['status'],
				'label'      => Signups::label( $su['status'] ),
				'event'      => $event['title'],
				'emoji'      => $event['emoji'],
				'url'        => 'publish' === $event['status'] ? $event['url'] : '',
				'when'       => EventDates::day( $event ) . ( '' === $su['session_label'] ? ', ' . EventDates::time_range( $event ) : '' ), // The session carries its own times.
				'session'    => $su['session_label'],
				'dogs'       => implode( ', ', array_column( $su['dogs'], 'name' ) ),
				'total_text' => Signups::total_text( $su ),
				'can_cancel' => Signups::can_cancel( $su, $event, $now ),
				'start'      => $event['start'],
			);
		}
		usort( $rows, static fn( array $a, array $b ): int => strcmp( $b['start'], $a['start'] ) );
		return $rows;
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
		$signups  = self::signup_rows( (int) $user->ID );

		ob_start();
		include PAW_DIR . 'templates/my-bookings.php';
		return (string) ob_get_clean();
	}
}
