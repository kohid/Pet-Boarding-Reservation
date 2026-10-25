<?php
/**
 * Reservation emails. Health details and files never go by email: staff read them in wp-admin.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Mailer {

	/** Confirmation to the owner and an alert to staff. */
	public static function reservation_received( array $r ): void {
		$settings = Settings::get();
		$staff    = Settings::staff_emails( $settings );
		$pet      = (string) $r['pet_name'];
		$stay     = self::date( $r['check_in'] ) . ' → ' . self::date( $r['check_out'] );
		$summary  = array(
			__( 'Reference', 'pawfectly-reservations' ) => $r['reference'],
			__( 'Pet', 'pawfectly-reservations' )       => $pet,
			__( 'Check-in', 'pawfectly-reservations' )  => self::date( $r['check_in'] ),
			__( 'Check-out', 'pawfectly-reservations' ) => self::date( $r['check_out'] ),
		);

		/* translators: %s: owner's first name */
		$owner_body = '<p>' . esc_html( sprintf( __( 'Hi %s,', 'pawfectly-reservations' ), $r['owner_first_name'] ) ) . '</p>'
			/* translators: %s: pet's name */
			. '<p>' . esc_html( sprintf( __( "Thank you for choosing us for %s's stay. Your request is pending: we'll email you again once it's confirmed.", 'pawfectly-reservations' ), $pet ) ) . '</p>'
			. EmailTemplate::rows( $summary )
			. '<p>' . esc_html__( 'If anything changes, just reply to this email.', 'pawfectly-reservations' ) . '</p>';
		self::send(
			(string) $r['owner_email'],
			/* translators: %s: reservation reference */
			sprintf( __( "We've received your reservation request — %s", 'pawfectly-reservations' ), $r['reference'] ),
			EmailTemplate::render( __( 'Reservation request received', 'pawfectly-reservations' ), $owner_body, $settings ),
			$staff[0] ?? ''
		);

		if ( ! $staff ) {
			return;
		}
		$link       = admin_url( 'admin.php?page=paw-reservations&view=' . (int) $r['id'] );
		$staff_body = EmailTemplate::rows(
			$summary + array(
				__( 'Owner', 'pawfectly-reservations' ) => trim( $r['owner_first_name'] . ' ' . $r['owner_last_name'] ),
				__( 'Email', 'pawfectly-reservations' ) => $r['owner_email'],
				__( 'Phone', 'pawfectly-reservations' ) => $r['owner_phone'],
			)
		) . '<p><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:10px 20px;border-radius:24px;background:#ff7f50;color:#ffffff;text-decoration:none;font-weight:600;">' . esc_html__( 'View in wp-admin', 'pawfectly-reservations' ) . '</a></p>';
		self::send(
			$staff,
			/* translators: 1: reference, 2: pet name, 3: stay dates */
			sprintf( __( 'New reservation request %1$s: %2$s (%3$s)', 'pawfectly-reservations' ), $r['reference'], $pet, $stay ),
			EmailTemplate::render( __( 'New reservation request', 'pawfectly-reservations' ), $staff_body, $settings ),
			(string) $r['owner_email']
		);
	}

	/** Tells the owner their reservation's new status. */
	public static function status_changed( array $r ): void {
		$settings = Settings::get();
		$label    = Reservations::label( (string) $r['status'] );
		/* translators: %s: owner's first name */
		$body = '<p>' . esc_html( sprintf( __( 'Hi %s,', 'pawfectly-reservations' ), $r['owner_first_name'] ) ) . '</p>'
			/* translators: 1: pet name, 2: new status */
			. '<p>' . esc_html( sprintf( __( "The reservation for %1\$s is now: %2\$s.", 'pawfectly-reservations' ), $r['pet_name'], $label ) ) . '</p>'
			. EmailTemplate::rows(
				array(
					__( 'Reference', 'pawfectly-reservations' ) => $r['reference'],
					__( 'Check-in', 'pawfectly-reservations' )  => self::date( $r['check_in'] ),
					__( 'Check-out', 'pawfectly-reservations' ) => self::date( $r['check_out'] ),
				)
			);
		$staff = Settings::staff_emails( $settings );
		self::send(
			(string) $r['owner_email'],
			/* translators: 1: reference, 2: new status */
			sprintf( __( 'Your reservation %1$s is now %2$s', 'pawfectly-reservations' ), $r['reference'], $label ),
			EmailTemplate::render( $label, $body, $settings ),
			$staff[0] ?? ''
		);
	}

	/** The link that confirms an owner's email address. */
	public static function verify_email( \WP_User $user, string $url ): void {
		$settings = Settings::get();
		/* translators: %s: owner's first name */
		$body = '<p>' . esc_html( sprintf( __( 'Hi %s,', 'pawfectly-reservations' ), $user->first_name ? $user->first_name : $user->display_name ) ) . '</p>'
			. '<p>' . esc_html__( 'Please confirm your email address so your reservations show in your account.', 'pawfectly-reservations' ) . '</p>'
			. '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:10px 20px;border-radius:24px;background:#ff7f50;color:#ffffff;text-decoration:none;font-weight:600;">' . esc_html__( 'Confirm my email', 'pawfectly-reservations' ) . '</a></p>'
			. '<p style="color:#6b7280;font-size:13px;">' . esc_html__( 'The link works for 7 days. If you did not create an account, you can ignore this email.', 'pawfectly-reservations' ) . '</p>';
		self::send(
			(string) $user->user_email,
			/* translators: %s: business name */
			sprintf( __( 'Confirm your email for %s', 'pawfectly-reservations' ), $settings['business_name'] ),
			EmailTemplate::render( __( 'Confirm your email', 'pawfectly-reservations' ), $body, $settings ),
			''
		);
	}

	/** Tells staff an owner cancelled online. */
	public static function owner_cancelled( array $r ): void {
		$settings = Settings::get();
		$staff    = Settings::staff_emails( $settings );
		if ( ! $staff ) {
			return;
		}
		$body = EmailTemplate::rows(
			array(
				__( 'Reference', 'pawfectly-reservations' ) => $r['reference'],
				__( 'Pet', 'pawfectly-reservations' )       => $r['pet_name'],
				__( 'Owner', 'pawfectly-reservations' )     => trim( $r['owner_first_name'] . ' ' . $r['owner_last_name'] ),
				__( 'Check-in', 'pawfectly-reservations' )  => self::date( $r['check_in'] ),
				__( 'Check-out', 'pawfectly-reservations' ) => self::date( $r['check_out'] ),
			)
		) . '<p><a href="' . esc_url( admin_url( 'admin.php?page=paw-reservations&view=' . (int) $r['id'] ) ) . '">' . esc_html__( 'View in wp-admin', 'pawfectly-reservations' ) . '</a></p>';
		self::send(
			$staff,
			/* translators: 1: reference, 2: pet name */
			sprintf( __( 'Reservation %1$s cancelled by the owner: %2$s', 'pawfectly-reservations' ), $r['reference'], $r['pet_name'] ),
			EmailTemplate::render( __( 'Cancelled by the owner', 'pawfectly-reservations' ), $body, $settings ),
			(string) $r['owner_email']
		);
	}

	/** m/d/Y for an ISO date, as shown on the form. */
	public static function date( string $iso ): string {
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $iso );
		return $d ? $d->format( 'm/d/Y' ) : $iso;
	}

	/** Subjects are a header: no line breaks. */
	public static function subject_safe( string $subject ): string {
		return trim( preg_replace( '/[\r\n]+/', ' ', $subject ) );
	}

	/** @param string|string[] $to */
	private static function send( $to, string $subject, string $html, string $reply_to ): void {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		if ( ! wp_mail( $to, self::subject_safe( $subject ), $html, $headers ) ) {
			error_log( '[pawfectly] email failed: ' . self::subject_safe( $subject ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}
