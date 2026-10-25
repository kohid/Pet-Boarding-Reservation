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
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function submit( \WP_REST_Request $request ) {
		$params = (array) $request->get_body_params();
		$files  = (array) $request->get_file_params();

		// PHP silently empties the whole request when it is bigger than post_max_size.
		$length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( ! $params && ! $files && $length > self::ini_bytes( (string) ini_get( 'post_max_size' ) ) ) {
			return new \WP_Error( 'paw_too_large', __( 'Your files are too large to send. Please upload smaller files (10 MB each at most).', 'pawfectly-reservations' ), array( 'status' => 413 ) );
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
		$checked   = Uploads::check( (array) ( $files['vaccination_files'] ?? array() ) );
		$signature = Signature::decode( is_string( $params['signature'] ?? null ) ? $params['signature'] : '' );
		$errors    = array();
		foreach ( array( $clean, $checked, $signature ) as $result ) {
			if ( is_wp_error( $result ) ) {
				$errors += $result->get_error_data()['fields'];
			}
		}
		if ( $errors ) {
			return new \WP_Error( 'paw_invalid', __( 'Please check the highlighted fields.', 'pawfectly-reservations' ), array( 'status' => 400, 'fields' => $errors ) );
		}

		$reference = Reference::generate();
		for ( $i = 0; $i < 5 && Reservations::reference_exists( $reference ); $i++ ) {
			$reference = Reference::generate();
		}

		$stored = Uploads::store( $reference, $checked );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		$signature_path = Signature::store( $reference, $signature );
		if ( is_wp_error( $signature_path ) ) {
			Uploads::delete( $stored );
			return $signature_path;
		}

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$row = Reservations::build_row( $clean, $reference, $stored, $signature_path, $ip, current_time( 'mysql', true ) );
		$id  = Reservations::insert( $row );
		if ( ! $id ) {
			Uploads::delete( array_merge( $stored, array( array( 'path' => $signature_path ) ) ) );
			error_log( '[pawfectly] reservation insert failed' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return Uploads::storage_error();
		}

		if ( $sid_key ) {
			set_transient( $sid_key, $reference, 10 * MINUTE_IN_SECONDS );
		}
		$reservation = Reservations::find( $id );
		if ( $reservation ) {
			Mailer::reservation_received( $reservation );
		}

		return new \WP_REST_Response( array( 'reference' => $reference ), 201 );
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
