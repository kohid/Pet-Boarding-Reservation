<?php
/**
 * Owner accounts: a customer role, sign-up, sign-in (owners only: staff use the WordPress login,
 * so security plugins keep guarding them), email verification and form prefill.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Accounts {

	public const ROLE          = 'paw_customer';
	public const STAFF_ROLE    = 'paw_staff';
	public const META_PHONE    = 'paw_phone';
	public const META_VERIFIED = 'paw_email_verified';
	public const META_HASH     = 'paw_verify_hash';
	public const META_EXPIRES  = 'paw_verify_expires';

	private const TOKEN_TTL = 7 * DAY_IN_SECONDS;

	/** Form steps whose answers are filled in again for a returning owner. */
	private const PREFILL_STEPS = array( 'owner', 'pet', 'care', 'emergency' );

	public static function add_roles(): void {
		add_role( self::ROLE, __( 'Pawfectly customer', 'pawfectly-reservations' ), array( 'read' => true ) );
		add_role(
			self::STAFF_ROLE,
			__( 'Pawfectly staff', 'pawfectly-reservations' ),
			array(
				'read'                 => true,
				Activator::CAPABILITY  => true,
			)
		);
	}

	/** @return int|\WP_Error The new user id. */
	public static function register( string $first, string $last, string $email, string $phone, string $password ) {
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'paw_email_exists', __( 'An account already uses this email address.', 'pawfectly-reservations' ), array( 'status' => 409, 'fields' => array( 'email' => __( 'An account already uses this email address.', 'pawfectly-reservations' ) ) ) );
		}
		$base  = sanitize_user( (string) strstr( $email, '@', true ), true );
		$base  = '' !== $base ? $base : 'owner';
		$login = $base;
		while ( username_exists( $login ) ) {
			$login = $base . random_int( 1000, 9999 );
		}

		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => $password,
				'user_email'   => $email,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ),
				'role'         => self::ROLE,
			)
		);
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'paw_signup', __( 'We could not create your account. Please try again.', 'pawfectly-reservations' ), array( 'status' => 500 ) );
		}
		update_user_meta( $id, self::META_PHONE, $phone );
		return (int) $id;
	}

	/** @return \WP_User|\WP_Error */
	public static function authenticate( string $email, string $password ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			return new \WP_Error( 'paw_login', __( 'The email or password is not right.', 'pawfectly-reservations' ), array( 'status' => 400 ) );
		}
		if ( self::is_staff( $user ) ) {
			return new \WP_Error(
				'paw_staff_login',
				/* translators: %s: WordPress login page address */
				sprintf( __( 'Staff sign in on the WordPress login page: %s', 'pawfectly-reservations' ), wp_login_url() ),
				array( 'status' => 403, 'login_url' => wp_login_url() )
			);
		}
		return $user;
	}

	public static function is_staff( \WP_User $user ): bool {
		return (bool) array_intersect( array( 'administrator', self::STAFF_ROLE ), (array) $user->roles ) || $user->has_cap( Activator::CAPABILITY );
	}

	public static function start_session( int $user_id ): void {
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
	}

	/** A fresh verification token; only its hash is kept. */
	public static function new_token( int $user_id ): string {
		$token = bin2hex( random_bytes( 32 ) );
		update_user_meta( $user_id, self::META_HASH, hash( 'sha256', $token ) );
		update_user_meta( $user_id, self::META_EXPIRES, time() + self::TOKEN_TTL );
		return $token;
	}

	public static function verify( int $user_id, string $token ): bool {
		$hash    = (string) get_user_meta( $user_id, self::META_HASH, true );
		$expires = (int) get_user_meta( $user_id, self::META_EXPIRES, true );
		if ( '' === $hash || $expires < time() || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			return false;
		}
		update_user_meta( $user_id, self::META_VERIFIED, '1' );
		delete_user_meta( $user_id, self::META_HASH );
		delete_user_meta( $user_id, self::META_EXPIRES );
		return true;
	}

	public static function is_verified( int $user_id ): bool {
		return '1' === (string) get_user_meta( $user_id, self::META_VERIFIED, true );
	}

	public static function unverify( int $user_id ): void {
		delete_user_meta( $user_id, self::META_VERIFIED );
	}

	public static function verify_url( int $user_id, string $token ): string {
		return add_query_arg( array( 'u' => $user_id, 't' => $token ), rest_url( Rest::NS . '/account/verify' ) );
	}

	/** Starts verification: new token, emailed link. */
	public static function send_verification( int $user_id ): void {
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			Mailer::verify_email( $user, self::verify_url( $user_id, self::new_token( $user_id ) ) );
		}
	}

	/**
	 * Answers to fill in for a returning owner: their last reservation's owner, pet, care and
	 * emergency steps, with the account's current email. Without a reservation, the account details.
	 *
	 * @return array<string,string>
	 */
	public static function prefill( int $user_id ): array {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return array();
		}
		$last = Reservations::last_for_owner( $user_id, (string) $user->user_email, self::is_verified( $user_id ) );
		if ( ! $last ) {
			return array_filter(
				array(
					'owner_first_name' => (string) $user->first_name,
					'owner_last_name'  => (string) $user->last_name,
					'owner_email'      => (string) $user->user_email,
					'owner_phone'      => (string) get_user_meta( $user_id, self::META_PHONE, true ),
				),
				'strlen'
			);
		}

		$values = Reservations::values( $last );
		$keep   = array();
		foreach ( Fields::steps() as $step ) {
			if ( ! in_array( $step['id'], self::PREFILL_STEPS, true ) ) {
				continue;
			}
			foreach ( $step['fields'] as $field ) {
				if ( isset( $values[ $field['id'] ] ) && '' !== (string) $values[ $field['id'] ] ) {
					$keep[ $field['id'] ] = (string) $values[ $field['id'] ];
				}
			}
		}
		$keep['owner_email'] = (string) $user->user_email;
		return $keep;
	}
}
