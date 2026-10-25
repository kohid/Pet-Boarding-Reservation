<?php
/**
 * [pawfectly_reservation_form]: renders the form and loads its assets only where it is used.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Shortcode {

	public const TAG = 'pawfectly_reservation_form';

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	public static function render( $atts = array() ): string {
		$v = PAW_VERSION;
		wp_enqueue_style( 'paw-flatpickr', PAW_URL . 'assets/vendor/flatpickr/flatpickr.min.css', array(), '4.6.13' );
		wp_enqueue_style( 'paw-form', PAW_URL . 'assets/css/form.css', array( 'paw-flatpickr' ), $v );
		wp_enqueue_script( 'paw-flatpickr', PAW_URL . 'assets/vendor/flatpickr/flatpickr.min.js', array(), '4.6.13', true );
		wp_enqueue_script( 'paw-signature-pad', PAW_URL . 'assets/js/signature-pad.js', array(), $v, true );
		wp_enqueue_script( 'paw-form', PAW_URL . 'assets/js/form.js', array( 'paw-flatpickr', 'paw-signature-pad' ), $v, true );
		wp_add_inline_script( 'paw-form', 'window.PAW_FORM = ' . wp_json_encode( self::config() ) . ';', 'before' );

		return self::markup( Settings::get() );
	}

	/** The form's HTML for the given settings. */
	public static function markup( array $settings ): string {
		$account   = self::account();
		$steps     = Fields::steps();
		$countries = Fields::countries();
		$logo      = $settings['logo_id'] ? wp_get_attachment_image_url( (int) $settings['logo_id'], 'medium' ) : '';
		$logo      = $logo ? $logo : PAW_URL . 'assets/img/paw.svg';

		ob_start();
		include PAW_DIR . 'templates/form.php';
		return (string) ob_get_clean();
	}

	/** Who is filling in the form, and the links the owner step offers. */
	public static function account(): array {
		$user     = wp_get_current_user();
		$in       = $user->exists();
		$customer = $in && Accounts::may_use_customer_login( $user );
		$here     = (string) ( get_permalink() ?: home_url( '/' ) );
		return array(
			'signedIn'  => $in,
			// Staff using the form are booking for a customer: nothing of theirs is filled in or linked.
			'staff'     => $in && ! $customer,
			'name'      => $in ? (string) $user->display_name : '',
			'prefill'   => $customer ? Accounts::prefill( (int) $user->ID ) : array(),
			'loginRest' => rest_url( Rest::NS . '/account/login' ),
			'lostUrl'   => wp_lostpassword_url( $here ),
			'logoutUrl' => wp_logout_url( $here ),
		);
	}

	/** Everything form.js needs, passed as window.PAW_FORM. */
	public static function config(): array {
		$fields = array();
		foreach ( Fields::all() as $id => $field ) {
			$fields[ $id ] = array(
				'type'     => $field['type'],
				'required' => $field['required'],
				'max'      => $field['max'],
				'step'     => $field['step'],
				'showIf'   => (object) $field['show_if'],
				'warn'     => (object) $field['warn'],
			);
		}

		return array(
			'restUrl'  => rest_url( Rest::NS . '/reservations' ),
			'nonceUrl' => rest_url( Rest::NS . '/nonce' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'today'    => ( new \DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' ),
			'steps'    => count( Fields::steps() ),
			'fields'   => $fields,
			'maxFiles' => Uploads::MAX_FILES,
			'maxBytes' => Uploads::max_file_bytes(),
			'maxTotal' => Uploads::max_request_bytes(),
			'maxNights' => Validator::MAX_NIGHTS,
			'account'  => self::account(),
			'i18n'     => array(
				'required'     => __( 'This field is required.', 'pawfectly-reservations' ),
				'email'        => __( 'Please enter a valid email address.', 'pawfectly-reservations' ),
				'phone'        => __( 'Please enter a valid phone number.', 'pawfectly-reservations' ),
				'choice'       => __( 'Please choose one of the options.', 'pawfectly-reservations' ),
				/* translators: %d: maximum number of characters */
				'tooLong'      => __( 'Please keep this under %d characters.', 'pawfectly-reservations' ),
				'date'         => __( 'Please enter a valid date.', 'pawfectly-reservations' ),
				'pastCheckIn'  => __( "Check-in can't be in the past.", 'pawfectly-reservations' ),
				'checkOut'     => __( 'Check-out must be after check-in.', 'pawfectly-reservations' ),
				/* translators: %d: maximum number of nights */
				'tooLongStay'  => __( 'Stays are limited to %d nights.', 'pawfectly-reservations' ),
				'agree'        => __( 'Please agree to the terms to continue.', 'pawfectly-reservations' ),
				/* translators: %d: maximum number of files */
				'fileCount'    => __( 'Please upload between 1 and %d files.', 'pawfectly-reservations' ),
				'fileSize'     => Uploads::size_message(),
				'tooLarge'     => Uploads::total_message(),
				'fileType'     => __( 'Only PDF, JPG or PNG files are accepted.', 'pawfectly-reservations' ),
				'signature'    => __( 'Please sign in the box above.', 'pawfectly-reservations' ),
				'passwordShort' => sprintf( /* translators: %d: minimum number of characters */ __( 'Use at least %d characters.', 'pawfectly-reservations' ), AccountRules::MIN_PASSWORD ),
				'signinFailed'  => __( 'The email or password is not right.', 'pawfectly-reservations' ),
				'signingIn'     => __( 'Signing in…', 'pawfectly-reservations' ),
				'accountReady'  => __( 'Your account is ready. Please confirm your email with the link we sent, then your reservations will show under My Bookings.', 'pawfectly-reservations' ),
				'remove'       => __( 'Remove', 'pawfectly-reservations' ),
				'sending'      => __( 'Sending…', 'pawfectly-reservations' ),
				'tooMany'      => __( 'Too many attempts, please try again in a few minutes.', 'pawfectly-reservations' ),
				'expired'      => __( 'Your session has expired. Please refresh the page and try again.', 'pawfectly-reservations' ),
				'network'      => __( "We couldn't reach the server. Your answers are still here — please try again.", 'pawfectly-reservations' ),
				'failed'       => __( 'Something went wrong, your details are still here — please try again.', 'pawfectly-reservations' ),
				'fixErrors'    => __( 'Please check the highlighted fields.', 'pawfectly-reservations' ),
				/* translators: 1: current step number, 2: number of steps */
				'stepOf'       => __( 'Step %1$d of %2$d', 'pawfectly-reservations' ),
			),
		);
	}
}
