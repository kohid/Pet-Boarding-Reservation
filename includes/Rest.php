<?php
/**
 * The public submit endpoint. Everything the form checks is checked again here.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Rest {

	public const NS = 'pawfectly/v1';

	private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			self::NS,
			'/reservations',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/nonce',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'nonce' ),
				'permission_callback' => '__return_true',
			)
		);
		Calendar::routes();
		EventsList::routes();
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function submit( \WP_REST_Request $request ) {
		$params = (array) $request->get_body_params();
		$files  = (array) $request->get_file_params();

		// PHP silently empties the whole request when it is bigger than post_max_size.
		$length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( ! $params && ! $files && $length > self::ini_bytes( (string) ini_get( 'post_max_size' ) ) ) {
			return new \WP_Error( 'paw_too_large', Uploads::total_message(), array( 'status' => 413 ) );
		}

		if ( ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_Error( 'paw_nonce', __( 'Your session has expired. Please refresh the page and try again.', 'pawfectly-reservations' ), array( 'status' => 403 ) );
		}

		// A retried or double-clicked submission gets the reference of the first one.
		$sid      = is_string( $params['submission_id'] ?? null ) ? strtolower( $params['submission_id'] ) : '';
		$sid_key  = preg_match( self::UUID_V4, $sid ) ? 'paw_sub_' . md5( $sid ) : '';
		$previous = $sid_key ? get_transient( $sid_key ) : false;
		if ( $previous ) {
			return new \WP_REST_Response( array( 'reference' => $previous ), 201 );
		}

		if ( ! RateLimit::allow( 'submit', 5, 10 * MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'paw_rate', __( 'Too many attempts, please try again in a few minutes.', 'pawfectly-reservations' ), array( 'status' => 429 ) );
		}

		// Bots fill the hidden field; tell them it worked and keep nothing.
		if ( ! empty( $params['website'] ) ) {
			return new \WP_REST_Response( array( 'reference' => Reference::generate() ), 201 );
		}

		$clean     = Validator::validate( $params, new \DateTimeImmutable( 'today', wp_timezone() ) );
		$signature = Signature::decode( is_string( $params['signature'] ?? null ) ? $params['signature'] : '' );
		$errors    = array();
		foreach ( array( $clean, $signature ) as $result ) {
			if ( is_wp_error( $result ) ) {
				$errors += $result->get_error_data()['fields'];
			}
		}
		// Each pet brings its own vaccination files: vaccination_files_N.
		$pet_count = is_array( $params['pets'] ?? null ) ? min( count( $params['pets'] ), Fields::MAX_PETS ) : 0;
		$checked   = array();
		$settings       = Settings::get();
		$files_optional = 'grooming' === ( $params['booking_type'] ?? '' ) && ! $settings['grooming_needs_vaccines'];
		for ( $n = 0; $n < $pet_count; $n++ ) {
			$field = (array) ( $files[ 'vaccination_files_' . $n ] ?? array() );
			if ( $files_optional && ! array_filter( (array) ( $field['name'] ?? array() ), 'strlen' ) ) {
				continue; // Grooming without records, as Settings allow.
			}
			$result = Uploads::check( $field );
			if ( is_wp_error( $result ) ) {
				$errors[ 'vaccination_files_' . $n ] = $result->get_error_data()['fields']['vaccination_files'];
			} else {
				$checked[ $n ] = $result;
			}
		}
		$account = self::account_request( $params, is_array( $clean ) ? $clean['columns']['owner_email'] : '' );
		$errors += $account['errors'];
		if ( $errors ) {
			return new \WP_Error( 'paw_invalid', __( 'Please check the highlighted fields.', 'pawfectly-reservations' ), array( 'status' => 400, 'fields' => $errors ) );
		}

		$reference = Reference::generate();
		for ( $i = 0; $i < 5 && Reservations::reference_exists( $reference ); $i++ ) {
			$reference = Reference::generate();
		}

		$stored = array();
		foreach ( $checked as $n => $pet_files ) {
			$result = Uploads::store( $reference, $pet_files, $n );
			if ( is_wp_error( $result ) ) {
				Uploads::delete( $stored );
				return $result;
			}
			$stored = array_merge( $stored, $result );
		}
		$signature_path = Signature::store( $reference, $signature );
		if ( is_wp_error( $signature_path ) ) {
			Uploads::delete( $stored );
			return $signature_path;
		}

		// Keep the exact waiver wording that was signed: Settings can change later.
		$type                            = $clean['columns']['booking_type'];
		$clean['details']['waiver_text'] = 'grooming' === $type ? $settings['grooming_waiver']
			: ( 'both' === $type ? $settings['waiver'] . '<hr>' . $settings['grooming_waiver'] : $settings['waiver'] );
		// And the prices the owner saw: Settings can change later too.
		$clean['details']['pricing'] = Services::estimate( $clean['columns'] + $clean['details'] );

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$row = Reservations::build_row( $clean, $reference, $stored, $signature_path, $ip, current_time( 'mysql', true ) );
		$row['user_id'] = is_user_logged_in() && Accounts::may_use_customer_login( wp_get_current_user() ) ? get_current_user_id() : null;
		$id             = Reservations::insert( $row );
		if ( ! $id ) {
			Uploads::delete( array_merge( $stored, array( array( 'path' => $signature_path ) ) ) );
			error_log( '[pawfectly] reservation insert failed' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return Uploads::storage_error();
		}

		if ( $sid_key ) {
			set_transient( $sid_key, $reference, 10 * MINUTE_IN_SECONDS );
		}
		$made_account = $account['create'] && self::create_account( $id, $clean['columns'], $account['password'] );
		$reservation = Reservations::find( $id );
		if ( $reservation ) {
			Mailer::reservation_received( $reservation );
		}

		return new \WP_REST_Response( array( 'reference' => $reference, 'account' => $made_account ), 201 );
	}

	/**
	 * "Create an account with these details" on the owner step: only for visitors, only when ticked.
	 *
	 * @return array{create: bool, password: string, errors: array<string,string>}
	 */
	private static function account_request( array $params, string $email ): array {
		$none = array( 'create' => false, 'password' => '', 'errors' => array() );
		if ( is_user_logged_in() || '1' !== (string) ( $params['create_account'] ?? '' ) ) {
			return $none;
		}
		$password = is_string( $params['account_password'] ?? null ) ? $params['account_password'] : '';
		$errors   = array();
		$confirm  = is_string( $params['account_password_confirm'] ?? null ) ? $params['account_password_confirm'] : '';
		$problem  = AccountRules::password( $password, null );
		if ( null !== $problem ) {
			$errors['account_password'] = $problem;
		} elseif ( null !== AccountRules::password( $password, $confirm ) ) {
			$errors['account_password_confirm'] = __( "The two passwords don't match.", 'pawfectly-reservations' );
		}
		if ( '' !== $email && Accounts::is_admin_email( $email ) ) {
			$errors['owner_email'] = __( "This email address belongs to the site's administrator. Book without an account, or use another email.", 'pawfectly-reservations' );
		} elseif ( '' !== $email && email_exists( $email ) ) {
			$errors['owner_email'] = __( "An account already uses this email. Sign in above, or untick 'Create an account'.", 'pawfectly-reservations' );
		}
		return array( 'create' => true, 'password' => $password, 'errors' => $errors );
	}

	/** Makes the account after the reservation is safely stored, links them, signs the owner in. */
	private static function create_account( int $reservation_id, array $owner, string $password ): bool {
		$user_id = Accounts::register( $owner['owner_first_name'], $owner['owner_last_name'], $owner['owner_email'], $owner['owner_phone'], $password );
		if ( is_wp_error( $user_id ) ) {
			error_log( '[pawfectly] account from reservation failed: ' . $user_id->get_error_code() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return false;
		}
		Reservations::link_user( $reservation_id, $user_id );
		Accounts::start_session( $user_id );
		Accounts::send_verification( $user_id );
		return true;
	}

	/** A fresh nonce, for pages served from a cache with an expired one. */
	public static function nonce(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
	}

	/** Bytes in a php.ini size such as 8M. */
	public static function ini_bytes( string $value ): int {
		$value = trim( $value );
		$num   = (int) $value;
		switch ( strtolower( substr( $value, -1 ) ) ) {
			case 'g':
				$num *= 1024;
				// Fall through.
			case 'm':
				$num *= 1024;
				// Fall through.
			case 'k':
				$num *= 1024;
		}
		return $num;
	}
}
