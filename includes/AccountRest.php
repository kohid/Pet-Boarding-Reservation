<?php
/**
 * Owner account endpoints. Sign-in and sign-up answer `reload: true`: the REST nonce a page was
 * served with belongs to the visitor, so the page reloads to get one for the signed-in owner.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class AccountRest {

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		$routes = array(
			array( '/account/login', 'POST', 'login' ),
			array( '/account/signup', 'POST', 'signup' ),
			array( '/account/profile', 'POST', 'profile' ),
			array( '/account/password', 'POST', 'password' ),
			array( '/account/resend', 'POST', 'resend' ),
			array( '/account/reservations/(?P<id>\d+)/cancel', 'POST', 'cancel' ),
			array( '/account/signups/(?P<id>\d+)/cancel', 'POST', 'cancel_signup' ),
		);
		foreach ( $routes as $r ) {
			register_rest_route(
				Rest::NS,
				$r[0],
				array(
					'methods'             => $r[1],
					'callback'            => array( self::class, $r[2] ),
					'permission_callback' => '__return_true', // Each callback checks what it needs.
				)
			);
		}
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function login( \WP_REST_Request $req ) {
		if ( ! RateLimit::allow( 'login', 10, 10 * MINUTE_IN_SECONDS ) ) {
			return self::too_many();
		}
		$in   = (array) $req->get_json_params();
		$user = Accounts::authenticate( self::text( $in, 'email' ), self::raw( $in, 'password' ), ! empty( $in['remember'] ) );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		return new \WP_REST_Response( array( 'ok' => true, 'reload' => true, 'redirect' => Login::target( self::raw( $in, 'redirect_to' ), $user ) ) );
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function signup( \WP_REST_Request $req ) {
		$in = (array) $req->get_json_params();
		if ( ! empty( $in['website'] ) || ! RateLimit::allow( 'signup', 5, 30 * MINUTE_IN_SECONDS ) ) {
			return self::too_many();
		}
		$v = AccountRules::signup( $in );
		if ( ! isset( $v['errors']['email'] ) && Accounts::is_admin_email( $v['clean']['email'] ) ) {
			$v['errors']['email'] = self::reserved();
		}
		if ( $v['errors'] ) {
			return self::invalid( $v['errors'] );
		}
		$c  = $v['clean'];
		$id = Accounts::register( $c['first_name'], $c['last_name'], $c['email'], $c['phone'], self::raw( $in, 'password' ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		Accounts::start_session( $id );
		Accounts::send_verification( $id );
		return new \WP_REST_Response( array( 'ok' => true, 'reload' => true, 'redirect' => Login::target( self::raw( $in, 'redirect_to' ), get_user_by( 'id', $id ) ) ) );
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function profile( \WP_REST_Request $req ) {
		$user = self::signed_in();
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		if ( ! RateLimit::allow( 'profile', 20, 10 * MINUTE_IN_SECONDS ) ) {
			return self::too_many();
		}
		$v = AccountRules::details( (array) $req->get_json_params() );
		if ( $v['errors'] ) {
			return self::invalid( $v['errors'] );
		}
		$c     = $v['clean'];
		$other = email_exists( $c['email'] );
		if ( $other && (int) $other !== (int) $user->ID ) {
			$e = array( 'email' => __( 'Another account already uses this email address.', 'pawfectly-reservations' ) );
			return new \WP_Error( 'paw_email_exists', $e['email'], array( 'status' => 409, 'fields' => $e ) );
		}
		$changed = strtolower( $c['email'] ) !== strtolower( (string) $user->user_email );
		if ( $changed && Accounts::is_admin_email( $c['email'] ) ) {
			return self::invalid( array( 'email' => self::reserved() ) );
		}
		if ( $changed && Access::is_admin_user( $user ) ) {
			// Moving the site owner's account off the admin email would end its wp-admin access.
			$e = array( 'email' => __( 'Change this email on the WordPress profile screen.', 'pawfectly-reservations' ) );
			return new \WP_Error( 'paw_staff_email', $e['email'], array( 'status' => 403, 'fields' => $e, 'profile_url' => get_edit_profile_url() ) );
		}
		// Staff keep their current address until the new one is confirmed; owners switch at once and re-confirm.
		$pending = $changed && ! Accounts::may_use_customer_login( $user );
		$res     = wp_update_user(
			array(
				'ID'           => $user->ID,
				'first_name'   => $c['first_name'],
				'last_name'    => $c['last_name'],
				'display_name' => trim( $c['first_name'] . ' ' . $c['last_name'] ),
				'user_email'   => $pending ? (string) $user->user_email : $c['email'],
			)
		);
		if ( is_wp_error( $res ) ) {
			return new \WP_Error( 'paw_save', __( 'We could not save your details. Please try again.', 'pawfectly-reservations' ), array( 'status' => 500 ) );
		}
		update_user_meta( $user->ID, Accounts::META_PHONE, $c['phone'] );
		if ( $pending ) {
			update_user_meta( $user->ID, Accounts::META_PENDING_EMAIL, $c['email'] );
			Accounts::send_verification( (int) $user->ID, $c['email'] );
		} elseif ( $changed ) {
			Accounts::unverify( (int) $user->ID );
			Accounts::send_verification( (int) $user->ID );
		}
		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'message' => $pending
					? __( 'Your details are saved. Your email changes once you click the link we sent to the new address.', 'pawfectly-reservations' )
					: ( $changed
						? __( 'Your details are saved. Please confirm your new email address with the link we just sent.', 'pawfectly-reservations' )
						: __( 'Your details are saved.', 'pawfectly-reservations' ) ),
				'name'    => trim( $c['first_name'] . ' ' . $c['last_name'] ),
				'email'   => $pending ? (string) $user->user_email : $c['email'],
			)
		);
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function password( \WP_REST_Request $req ) {
		$user = self::signed_in();
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		if ( ! RateLimit::allow( 'profile', 20, 10 * MINUTE_IN_SECONDS ) ) {
			return self::too_many();
		}
		$in    = (array) $req->get_json_params();
		$error = AccountRules::password( self::raw( $in, 'password' ), self::raw( $in, 'confirm' ) );
		if ( null !== $error ) {
			return self::invalid( array( 'password' => $error ) );
		}
		if ( ! wp_check_password( self::raw( $in, 'current' ), $user->user_pass, $user->ID ) ) {
			return self::invalid( array( 'current' => __( 'That is not your current password.', 'pawfectly-reservations' ) ) );
		}
		if ( is_wp_error( wp_update_user( array( 'ID' => $user->ID, 'user_pass' => self::raw( $in, 'password' ) ) ) ) ) { // Core keeps this session signed in.
			return new \WP_Error( 'paw_save', __( 'We could not change your password. Please try again.', 'pawfectly-reservations' ), array( 'status' => 500 ) );
		}
		// Changing the password renews the sign-in cookie, which retires this page's nonce: reload.
		return new \WP_REST_Response( array( 'ok' => true, 'reload' => true, 'message' => __( 'Your password is changed.', 'pawfectly-reservations' ) ) );
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function resend( \WP_REST_Request $req ) {
		$user = self::signed_in();
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		if ( ! RateLimit::allow( 'resend', 3, HOUR_IN_SECONDS ) ) {
			return self::too_many();
		}
		Accounts::send_verification( (int) $user->ID );
		return new \WP_REST_Response( array( 'ok' => true, 'message' => __( 'We sent a new link. Please check your inbox.', 'pawfectly-reservations' ) ) );
	}

	/** Owner cancels one of their own reservations (see MyBookings::can_cancel). */
	public static function cancel( \WP_REST_Request $req ) {
		$user = self::signed_in();
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$id      = absint( $req->get_param( 'id' ) );
		$visible = array_column( Reservations::for_owner( (int) $user->ID, (string) $user->user_email, Accounts::is_verified( (int) $user->ID ), 500 ), null, 'id' );
		if ( ! isset( $visible[ $id ] ) ) {
			return new \WP_Error( 'paw_not_found', __( 'Reservation not found.', 'pawfectly-reservations' ), array( 'status' => 404 ) );
		}
		$today = ( new \DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		if ( ! MyBookings::can_cancel( $visible[ $id ], $today ) ) {
			return new \WP_Error( 'paw_not_cancellable', __( 'This reservation can no longer be cancelled online — please call us.', 'pawfectly-reservations' ), array( 'status' => 409 ) );
		}
		Reservations::set_status( $id, 'cancelled', (int) $user->ID );
		$reservation = Reservations::find( $id );
		if ( $reservation ) {
			Mailer::owner_cancelled( $reservation );
		}
		return new \WP_REST_Response( array( 'ok' => true, 'status' => 'cancelled', 'label' => Reservations::label( 'cancelled' ) ) );
	}

	/** Owner cancels one of their own event sign-ups, until the event starts (Signups::can_cancel). */
	public static function cancel_signup( \WP_REST_Request $req ) {
		$user = self::signed_in();
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$su    = Signups::get( absint( $req->get_param( 'id' ) ) );
		$event = $su ? Events::get( $su['event'] ) : null;
		if ( ! $su || ! $event || $su['user_id'] !== (int) $user->ID ) {
			return new \WP_Error( 'paw_not_found', __( 'Sign-up not found.', 'pawfectly-reservations' ), array( 'status' => 404 ) );
		}
		if ( ! Signups::can_cancel( $su, $event ) ) {
			return new \WP_Error( 'paw_not_cancellable', __( 'This sign-up can no longer be cancelled online — please call us.', 'pawfectly-reservations' ), array( 'status' => 409 ) );
		}
		Signups::set_status( $su['id'], 'cancelled' );
		Mailer::signup_cancelled( Signups::get( $su['id'] ), $event );
		return new \WP_REST_Response( array( 'ok' => true, 'status' => 'cancelled', 'label' => Signups::label( 'cancelled' ) ) );
	}

	/** @return \WP_User|\WP_Error */
	private static function signed_in() {
		$user = wp_get_current_user();
		return $user->exists() ? $user : new \WP_Error( 'paw_auth', __( 'Please sign in again.', 'pawfectly-reservations' ), array( 'status' => 401 ) );
	}

	private static function text( array $in, string $key ): string {
		return Validator::read( $in[ $key ] ?? '', 'text' );
	}

	/** Passwords are used exactly as typed. */
	private static function raw( array $in, string $key ): string {
		return is_string( $in[ $key ] ?? null ) ? $in[ $key ] : '';
	}

	private static function invalid( array $fields ): \WP_Error {
		return new \WP_Error( 'paw_invalid', (string) reset( $fields ), array( 'status' => 400, 'fields' => $fields ) );
	}

	private static function reserved(): string {
		return __( "This email address belongs to the site's administrator. Please use another one.", 'pawfectly-reservations' );
	}

	private static function too_many(): \WP_Error {
		return new \WP_Error( 'paw_rate', __( 'Too many attempts, please try again in a few minutes.', 'pawfectly-reservations' ), array( 'status' => 429 ) );
	}
}
