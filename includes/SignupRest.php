<?php
/**
 * POST /events/<id>/signup: the public event sign-up. The same protections as the booking
 * form: the REST nonce, a rate limit, a honeypot and a submission id for double clicks.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class SignupRest {

	private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/events/(?P<id>\d+)/signup',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** An event that takes sign-ups here: published, not over, and without an outside ticket link. */
	public static function open_event( int $id ): ?array {
		$ev = Events::get( $id );
		if ( ! $ev || 'publish' !== $ev['status'] || '' !== $ev['ticket_url'] || 'past' === Events::status( $ev ) ) {
			return null;
		}
		return $ev;
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function submit( \WP_REST_Request $req ) {
		if ( ! wp_verify_nonce( (string) $req->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_Error( 'paw_nonce', __( 'Your session has expired. Please refresh the page and try again.', 'pawfectly-reservations' ), array( 'status' => 403 ) );
		}
		$in = (array) $req->get_json_params();

		// A retried or double-clicked submission gets the first reference.
		$sid     = is_string( $in['submission_id'] ?? null ) ? strtolower( $in['submission_id'] ) : '';
		$sid_key = preg_match( self::UUID_V4, $sid ) ? 'paw_sus_' . md5( $sid ) : '';
		$earlier = $sid_key ? get_transient( $sid_key ) : false;
		if ( is_array( $earlier ) ) {
			return new \WP_REST_Response( $earlier, 201 );
		}

		$event = self::open_event( absint( $req->get_param( 'id' ) ) );
		if ( ! $event ) {
			return new \WP_Error( 'paw_not_found', __( 'This event is not taking sign-ups.', 'pawfectly-reservations' ), array( 'status' => 404 ) );
		}
		if ( ! RateLimit::allow( 'signup', 5, 10 * MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'paw_rate', __( 'Too many attempts, please try again in a few minutes.', 'pawfectly-reservations' ), array( 'status' => 429 ) );
		}
		// Bots fill the hidden field; tell them it worked and keep nothing.
		if ( ! empty( $in['website'] ) ) {
			return new \WP_REST_Response( array( 'reference' => Reference::generate( 'EVT' ) ), 201 );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		// The spots and duplicate-email checks and the insert run as one, so two people sending
		// at the same moment can't both take the last spot.
		$su = Signups::locked(
			(int) $event['id'],
			static function () use ( $event, $in, $ip ) {
				$clean = Signups::sanitize( $event, $in );
				return is_wp_error( $clean ) ? $clean : Signups::create( $event, $clean, is_user_logged_in() ? get_current_user_id() : 0, $ip );
			}
		);
		if ( is_wp_error( $su ) ) {
			return $su;
		}
		$out = array(
			'reference' => $su['reference'],
			'summary'   => self::summary( $su, $event ),
			'calendar'  => EventDates::links( $event ),
		);
		if ( $sid_key ) {
			set_transient( $sid_key, $out, DAY_IN_SECONDS );
		}
		Mailer::signup_received( $su, Events::public_view( $event ) );
		return new \WP_REST_Response( $out, 201 );
	}

	/** What the thank-you panel and the emails show. */
	public static function summary( array $su, array $event ): array {
		return array(
			'event'      => trim( $event['emoji'] . ' ' . $event['title'] ),
			'when'       => EventDates::day( $event ) . ' · ' . EventDates::time_range( $event ),
			'where'      => EventDates::where( $event ),
			'session'    => $su['session_label'],
			'dogs'       => implode( ', ', array_column( $su['dogs'], 'name' ) ),
			'total_text' => Signups::total_text( $su ),
		);
	}
}
