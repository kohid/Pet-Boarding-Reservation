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
		$stay     = Reservations::when( $r );
		$type     = (string) ( $r['booking_type'] ?? 'boarding' );
		$summary  = array(
			__( 'Reference', 'pawfectly-reservations' ) => $r['reference'],
			__( 'Pet', 'pawfectly-reservations' )       => $pet,
		);
		if ( 'grooming' === $type ) {
			$summary[ __( 'Appointment', 'pawfectly-reservations' ) ] = $stay;
		} else {
			$summary[ __( 'Check-in', 'pawfectly-reservations' ) ]  = self::date( $r['check_in'] );
			$summary[ __( 'Check-out', 'pawfectly-reservations' ) ] = self::date( $r['check_out'] );
		}
		$pets = Reservations::pets( $r );
		if ( count( $pets ) > 1 ) {
			// One line per pet: "Rex: Nail Trimming, groomer Ana".
			$catalog = Services::catalog();
			foreach ( $pets as $n => $p ) {
				$line = (string) ( $p['pet_name'] ?? '' );
				if ( isset( $catalog[ $p['service'] ?? '' ] ) ) {
					$line .= ': ' . $catalog[ $p['service'] ]['name'];
				}
				if ( '' !== (string) ( $p['groomer'] ?? '' ) && 'any' !== $p['groomer'] ) {
					/* translators: %s: groomer's name */
					$line .= ', ' . sprintf( __( 'groomer %s', 'pawfectly-reservations' ), $p['groomer'] );
				}
				/* translators: %d: pet number */
				$summary[ sprintf( __( 'Pet %d', 'pawfectly-reservations' ), $n + 1 ) ] = $line;
			}
		}
		if ( '' !== Reservations::service_names( $r ) ) {
			if ( count( $pets ) < 2 ) {
				$groomer = (string) ( $pets[0]['groomer'] ?? '' );
				$summary[ __( 'Service', 'pawfectly-reservations' ) ] = Reservations::service_names( $r )
					/* translators: %s: groomer's name */
					. ( '' !== $groomer && 'any' !== $groomer ? ', ' . sprintf( __( 'groomer %s', 'pawfectly-reservations' ), $groomer ) : '' );
			}
			if ( 'both' === $type && ! empty( $r['appointment_date'] ) ) {
				$summary[ __( 'Grooming day', 'pawfectly-reservations' ) ] = self::date( (string) $r['appointment_date'] );
			}
		}
		$estimate = Services::total_text( (array) ( $r['details']['pricing'] ?? array() ) );
		if ( '' !== $estimate ) {
			$summary[ __( 'Estimated total', 'pawfectly-reservations' ) ] = $estimate;
		}

		/* translators: %s: owner's first name */
		$owner_body = '<p>' . esc_html( sprintf( __( 'Hi %s,', 'pawfectly-reservations' ), $r['owner_first_name'] ) ) . '</p>'
			/* translators: %s: pet's name */
			. '<p>' . esc_html( sprintf( __( "Thank you for choosing us for %s's stay. Your request is pending: we'll email you again once it's confirmed.", 'pawfectly-reservations' ), $pet ) ) . '</p>'
			. EmailTemplate::rows( $summary )
			. ( '' !== $estimate ? '<p>' . esc_html__( 'Estimate. Payment is taken at Pawfectly; staff confirm the final price.', 'pawfectly-reservations' ) . '</p>' : '' )
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
		$dashboard  = Pages::url( Dashboard::TAG );
		$link       = '' !== $dashboard
			? add_query_arg( array( 'paw_view' => 'reservations', 'open' => (int) $r['id'] ), $dashboard )
			: admin_url( 'admin.php?page=paw-reservations&view=' . (int) $r['id'] );
		$staff_body = EmailTemplate::rows(
			$summary + array(
				__( 'Owner', 'pawfectly-reservations' ) => trim( $r['owner_first_name'] . ' ' . $r['owner_last_name'] ),
				__( 'Email', 'pawfectly-reservations' ) => $r['owner_email'],
				__( 'Phone', 'pawfectly-reservations' ) => $r['owner_phone'],
			)
		) . '<p><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:10px 20px;border-radius:24px;background:#ff7f50;color:#ffffff;text-decoration:none;font-weight:600;">' . esc_html__( 'View reservation', 'pawfectly-reservations' ) . '</a></p>';
		self::send(
			$staff,
			/* translators: 1: reference, 2: pet name, 3: stay dates */
			sprintf( __( 'New reservation request %1$s: %2$s (%3$s)', 'pawfectly-reservations' ), $r['reference'], $pet, $stay ),
			EmailTemplate::render( __( 'New reservation request', 'pawfectly-reservations' ), $staff_body, $settings ),
			(string) $r['owner_email']
		);
	}

	/** An event sign-up: a confirmation to the owner and an alert to staff. */
	public static function signup_received( array $su, array $event ): void {
		$settings = Settings::get();
		$summary  = SignupRest::summary( $su, $event );
		$links    = EventDates::links( $event );
		$rows     = array_filter(
			array(
				__( 'Reference', 'pawfectly-reservations' ) => $su['reference'],
				__( 'Event', 'pawfectly-reservations' )     => $summary['event'],
				__( 'When', 'pawfectly-reservations' )      => $summary['when'],
				__( 'Where', 'pawfectly-reservations' )     => $summary['where'],
				__( 'Session', 'pawfectly-reservations' )   => $summary['session'],
				__( 'Dogs', 'pawfectly-reservations' )      => $summary['dogs'],
				__( 'Total', 'pawfectly-reservations' )     => $summary['total_text'],
			),
			'strlen'
		);

		/* translators: %s: owner's first name */
		$owner_body = '<p>' . esc_html( sprintf( __( 'Hi %s,', 'pawfectly-reservations' ), $su['first_name'] ) ) . '</p>'
			/* translators: %s: event title */
			. '<p>' . esc_html( sprintf( __( "You're signed up for %s. We can't wait to meet your pup!", 'pawfectly-reservations' ), $event['title'] ) ) . '</p>'
			. EmailTemplate::rows( $rows )
			. '<p>' . esc_html__( 'Add it to your calendar:', 'pawfectly-reservations' ) . ' <a href="' . esc_url( $links['google'] ) . '">Google</a> · <a href="' . esc_url( $links['outlook'] ) . '">Outlook</a> · <a href="' . esc_url( $links['ics'] ) . '">Apple / iCal</a></p>'
			. '<p>' . esc_html__( 'Need to change something? Just reply to this email.', 'pawfectly-reservations' ) . '</p>';
		$staff = Settings::staff_emails( $settings );
		self::send(
			$su['email'],
			/* translators: 1: event title, 2: reference */
			sprintf( __( "You're signed up: %1\$s — %2\$s", 'pawfectly-reservations' ), $event['title'], $su['reference'] ),
			EmailTemplate::render( __( "You're signed up!", 'pawfectly-reservations' ), $owner_body, $settings ),
			$staff[0] ?? ''
		);

		if ( ! $staff ) {
			return;
		}
		$dashboard  = Pages::url( Dashboard::TAG );
		$link       = '' !== $dashboard ? add_query_arg( array( 'paw_view' => 'events', 'event' => (int) $event['id'] ), $dashboard ) : '';
		$staff_body = EmailTemplate::rows(
			$rows + array(
				__( 'Name', 'pawfectly-reservations' )  => trim( $su['first_name'] . ' ' . $su['last_name'] ),
				__( 'Email', 'pawfectly-reservations' ) => $su['email'],
				__( 'Phone', 'pawfectly-reservations' ) => $su['phone'],
				__( 'Notes', 'pawfectly-reservations' ) => $su['notes'],
			)
		) . ( '' !== $link ? '<p><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:10px 20px;border-radius:24px;background:#ff7f50;color:#ffffff;text-decoration:none;font-weight:600;">' . esc_html__( 'See sign-ups', 'pawfectly-reservations' ) . '</a></p>' : '' );
		self::send(
			$staff,
			/* translators: 1: event title, 2: owner's name, 3: number of dogs */
			sprintf( _n( 'New sign-up for %1$s: %2$s (%3$d dog)', 'New sign-up for %1$s: %2$s (%3$d dogs)', $su['dog_count'], 'pawfectly-reservations' ), $event['title'], trim( $su['first_name'] . ' ' . $su['last_name'] ), $su['dog_count'] ),
			EmailTemplate::render( __( 'New event sign-up', 'pawfectly-reservations' ), $staff_body, $settings ),
			$su['email']
		);
	}

	/** Tells staff an owner cancelled their event sign-up. */
	public static function signup_cancelled( array $su, array $event ): void {
		$settings = Settings::get();
		$staff    = Settings::staff_emails( $settings );
		if ( ! $staff ) {
			return;
		}
		$body = EmailTemplate::rows(
			array_filter(
				array(
					__( 'Reference', 'pawfectly-reservations' ) => $su['reference'],
					__( 'Event', 'pawfectly-reservations' )     => $event['title'],
					__( 'Session', 'pawfectly-reservations' )   => $su['session_label'],
					__( 'Name', 'pawfectly-reservations' )      => trim( $su['first_name'] . ' ' . $su['last_name'] ),
					__( 'Dogs', 'pawfectly-reservations' )      => implode( ', ', array_column( $su['dogs'], 'name' ) ),
				),
				'strlen'
			)
		);
		self::send(
			$staff,
			/* translators: 1: event title, 2: owner's name, 3: reference */
			sprintf( __( 'Sign-up cancelled for %1$s: %2$s (%3$s)', 'pawfectly-reservations' ), $event['title'], trim( $su['first_name'] . ' ' . $su['last_name'] ), $su['reference'] ),
			EmailTemplate::render( __( 'Sign-up cancelled by the owner', 'pawfectly-reservations' ), $body, $settings ),
			$su['email']
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
		) . '<p><a href="' . esc_url( admin_url( 'admin.php?page=paw-reservations&view=' . (int) $r['id'] ) ) . '">' . esc_html__( 'View reservation', 'pawfectly-reservations' ) . '</a></p>';
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
