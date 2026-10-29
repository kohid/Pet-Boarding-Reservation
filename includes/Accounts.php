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
	public const META_PENDING_EMAIL = 'paw_pending_email';

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

	/** Roles that count as plain customers (their reservations link to their account). */
	private const CUSTOMER_ROLES = array( self::ROLE, 'subscriber', 'customer' );

	/**
	 * Sign-in for everyone, by email or username. wp_signon runs the `authenticate` filter, so
	 * lockout and security plugins still apply, sets the cookie and fires `wp_login`.
	 *
	 * @return \WP_User|\WP_Error
	 */
	public static function authenticate( string $login, string $password, bool $remember = false ) {
		$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
		$done = $user ? wp_signon( array( 'user_login' => $user->user_login, 'user_password' => $password, 'remember' => $remember ), is_ssl() ) : null;
		if ( ! $done instanceof \WP_User ) {
			return new \WP_Error( 'paw_login', __( 'The email or password is not right.', 'pawfectly-reservations' ), array( 'status' => 400 ) );
		}
		wp_set_current_user( (int) $done->ID );
		return $done;
	}

	/** Plain customer accounts: no staff capability, nothing beyond reading. */
	public static function may_use_customer_login( \WP_User $user ): bool {
		return ! array_diff( (array) $user->roles, self::CUSTOMER_ROLES ) && ! $user->has_cap( Activator::CAPABILITY ) && ! $user->has_cap( 'edit_posts' );
	}

	public static function is_staff( \WP_User $user ): bool {
		return ! self::may_use_customer_login( $user );
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

	/** The site's Administration Email Address (any letter case). It belongs to the site owner. */
	public static function is_admin_email( string $email ): bool {
		$admin = strtolower( trim( (string) get_option( 'admin_email' ) ) );
		return '' !== $admin && strtolower( trim( $email ) ) === $admin;
	}

	public static function verify( int $user_id, string $token ): bool {
		$hash    = (string) get_user_meta( $user_id, self::META_HASH, true );
		$expires = (int) get_user_meta( $user_id, self::META_EXPIRES, true );
		if ( '' === $hash || $expires < time() || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) {
			return false;
		}
		// A staff email change waits for this click on the new address.
		$pending = (string) get_user_meta( $user_id, self::META_PENDING_EMAIL, true );
		if ( '' !== $pending ) {
			delete_user_meta( $user_id, self::META_PENDING_EMAIL );
			$other = email_exists( $pending );
			if ( ( $other && (int) $other !== $user_id ) || self::is_admin_email( $pending ) ) {
				return false;
			}
			wp_update_user( array( 'ID' => $user_id, 'user_email' => $pending ) );
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

	/**
	 * The emailed link opens a normal site page (not REST), where the sign-in cookie counts, so
	 * confirming can require the account's own session.
	 */
	public static function verify_url( int $user_id, string $token ): string {
		return add_query_arg( array( 'paw_verify_u' => $user_id, 'paw_verify_t' => $token ), home_url( '/' ) );
	}

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'handle_verify_link' ) );
	}

	/**
	 * Confirms an email only for the signed-in owner of the account: otherwise someone who signed
	 * up with your address could have you confirm it by clicking, and then see your reservations.
	 *
	 * @return string ok | signin | wrong_user | bad
	 */
	public static function verify_request( int $user_id, string $token, int $current_user_id ): string {
		if ( 0 === $current_user_id ) {
			return 'signin';
		}
		if ( $current_user_id !== $user_id ) {
			return 'wrong_user';
		}
		return self::verify( $user_id, $token ) ? 'ok' : 'bad';
	}

	/** Where each outcome sends the visitor. "signin" keeps the link so it completes after signing in. */
	public static function verify_redirect( string $outcome, int $user_id, string $token ): string {
		$profile  = Pages::url( 'pawfectly_my_profile' );
		$bookings = Pages::url( 'pawfectly_my_bookings' );
		$home     = home_url( '/' );
		switch ( $outcome ) {
			case 'ok':
				return add_query_arg( array( 'paw_verified' => '1' ), $bookings ?: ( $profile ?: $home ) );
			case 'signin':
				$args = array( 'paw_verify_u' => $user_id, 'paw_verify_t' => $token, 'paw_verified' => 'signin' );
				return '' !== $profile ? add_query_arg( $args, $profile ) : wp_login_url( add_query_arg( $args, $home ) );
			case 'wrong_user':
				return add_query_arg( array( 'paw_verified' => 'wrong' ), $profile ?: $home );
		}
		return add_query_arg( array( 'paw_verified' => '0' ), $profile ?: ( $bookings ?: $home ) );
	}

	/** template_redirect: handles ?paw_verify_u=&paw_verify_t= from the email. */
	public static function handle_verify_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- the token is the proof.
		if ( ! isset( $_GET['paw_verify_u'], $_GET['paw_verify_t'] ) || ! is_string( $_GET['paw_verify_t'] ) ) {
			return;
		}
		$current = get_current_user_id();
		if ( 0 === $current && isset( $_GET['paw_verified'] ) ) {
			return; // Already showing "sign in to confirm"; the link completes after signing in.
		}
		$user_id = absint( $_GET['paw_verify_u'] );
		$token   = sanitize_key( wp_unslash( $_GET['paw_verify_t'] ) );
		// phpcs:enable
		$outcome = self::verify_request( $user_id, $token, $current );
		wp_safe_redirect( self::verify_redirect( $outcome, $user_id, $token ) );
		exit;
	}

	/** Starts verification: new token, emailed link (to $to when given, e.g. a pending new address). */
	public static function send_verification( int $user_id, string $to = '' ): void {
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			$recipient = clone $user;
			if ( '' !== $to ) {
				$recipient->user_email = $to;
			}
			Mailer::verify_email( $recipient, self::verify_url( $user_id, self::new_token( $user_id ) ) );
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
		$keep = array_filter(
			array(
				'owner_first_name' => (string) $user->first_name,
				'owner_last_name'  => (string) $user->last_name,
				'owner_phone'      => (string) get_user_meta( $user_id, self::META_PHONE, true ),
			),
			'strlen'
		);
		if ( $last ) {
			// Booking-level answers from the owner and emergency steps of the last booking.
			$values  = Reservations::values( $last );
			$per_pet = Fields::pet_fields();
			foreach ( Fields::steps() as $step ) {
				if ( ! in_array( $step['id'], self::PREFILL_STEPS, true ) ) {
					continue;
				}
				foreach ( $step['fields'] as $field ) {
					if ( ! isset( $per_pet[ $field['id'] ] ) && isset( $values[ $field['id'] ] ) && '' !== (string) $values[ $field['id'] ] ) {
						$keep[ $field['id'] ] = (string) $values[ $field['id'] ];
					}
				}
			}
		}
		// My Profile's details for bookings win over the last booking.
		$keep = array_merge( $keep, SavedPets::info( $user_id, $last ) );

		// The saved pets to choose from; the form starts with those that came last time.
		$came  = $last ? array_map( static fn( array $p ): string => mb_strtolower( trim( (string) ( $p['pet_name'] ?? '' ) ) ), Reservations::pets( $last ) ) : array();
		$saved = array();
		$start = array();
		foreach ( SavedPets::get( $user_id, $last ) as $pet ) {
			$name    = (string) ( $pet['values']['pet_name'] ?? '' );
			$is_last = ! $came || in_array( mb_strtolower( trim( $name ) ), $came, true );
			$saved[] = array(
				'uid'    => $pet['uid'],
				'name'   => $name,
				'type'   => (string) ( $pet['values']['pet_type'] ?? '' ),
				'last'   => $is_last,
				'values' => $pet['values'],
			);
			if ( $is_last ) {
				$start[] = $pet['values'];
			}
		}
		if ( $saved ) {
			$keep['pets']       = $start ? $start : array_column( $saved, 'values' );
			$keep['saved_pets'] = $saved;
		}
		$keep['owner_email'] = (string) $user->user_email;
		return $keep;
	}
}
