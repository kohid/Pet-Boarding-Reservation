<?php
/**
 * Data for the front-end staff dashboard. Every route checks the staff capability, so showing
 * the dashboard shortcode to the wrong person shows nothing.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class StaffRest {

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function can(): bool {
		return current_user_can( Activator::CAPABILITY );
	}

	public static function routes(): void {
		$id     = '/staff/reservations/(?P<id>\d+)';
		$routes = array(
			array( '/staff/stats', 'GET', 'stats' ),
			array( '/staff/reservations', 'GET', 'list' ),
			array( $id, 'GET', 'detail' ),
			array( $id, 'DELETE', 'delete' ),
			array( $id . '/status', 'POST', 'status' ),
			array( $id . '/notes', 'POST', 'notes' ),
		);
		foreach ( $routes as $r ) {
			register_rest_route(
				Rest::NS,
				$r[0],
				array(
					'methods'             => $r[1],
					'callback'            => array( self::class, $r[2] ),
					'permission_callback' => array( self::class, 'can' ),
				)
			);
		}
	}

	public static function stats( \WP_REST_Request $req ): \WP_REST_Response {
		$today = ( new \DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		return new \WP_REST_Response( Stats::overview( Reservations::for_stats( $today ), $today ) + array( 'today' => $today ) );
	}

	public static function list( \WP_REST_Request $req ): \WP_REST_Response {
		$args = array();
		foreach ( array( 'status', 'search', 'from', 'to', 'orderby', 'order' ) as $key ) {
			$args[ $key ] = Validator::read( $req->get_param( $key ) ?? '', 'text' );
		}
		$args['page']     = max( 1, absint( $req->get_param( 'page' ) ?? 1 ) );
		$args['per_page'] = 20;
		$result           = Reservations::query( $args );
		$types            = Fields::all()['pet_type']['options'];

		return new \WP_REST_Response(
			array(
				'rows'   => array_map(
					static fn( array $r ): array => array(
						'id'           => (int) $r['id'],
						'reference'    => $r['reference'],
						'pet_name'     => $r['pet_name'],
						'pet_type'     => $types[ $r['pet_type'] ] ?? $r['pet_type'],
						'owner'        => trim( $r['owner_first_name'] . ' ' . $r['owner_last_name'] ),
						'email'        => $r['owner_email'],
						'phone'        => $r['owner_phone'],
						'check_in'     => $r['check_in'],
						'check_out'    => $r['check_out'],
						'stay'         => Mailer::date( $r['check_in'] ) . ' → ' . Mailer::date( $r['check_out'] ),
						'nights'       => self::nights( $r ),
						'status'       => $r['status'],
						'status_label' => Reservations::label( $r['status'] ),
						'created_at'   => get_date_from_gmt( $r['created_at'], 'm/d/Y g:i a' ),
					),
					$result['rows']
				),
				'total'  => $result['total'],
				'pages'  => (int) ceil( $result['total'] / 20 ),
				'counts' => Reservations::counts(),
			)
		);
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function detail( \WP_REST_Request $req ) {
		$r = Reservations::find( absint( $req->get_param( 'id' ) ) );
		if ( ! $r ) {
			return self::not_found();
		}
		$steps  = Fields::steps();
		$values = Reservations::values( $r );
		ob_start();
		include PAW_DIR . 'templates/partials/answers.php';
		$answers = (string) ob_get_clean();

		return new \WP_REST_Response(
			array(
				'id'            => (int) $r['id'],
				'reference'     => $r['reference'],
				'pet_name'      => $r['pet_name'],
				'owner'         => trim( $r['owner_first_name'] . ' ' . $r['owner_last_name'] ),
				'stay'          => Mailer::date( $r['check_in'] ) . ' → ' . Mailer::date( $r['check_out'] ),
				'nights'        => self::nights( $r ),
				'status'        => $r['status'],
				'status_label'  => Reservations::label( $r['status'] ),
				'staff_notes'   => (string) ( $r['staff_notes'] ?? '' ),
				'answers_html'  => $answers,
				'files'         => array_map(
					static fn( array $f, int $n ): array => array( 'name' => $f['name'], 'size' => (int) $f['size'], 'url' => Uploads::url( (int) $r['id'], (string) $n ) ),
					(array) $r['files'],
					array_keys( (array) $r['files'] )
				),
				'signature_url' => Uploads::url( (int) $r['id'], 'sig' ),
				'history'       => self::history( $r ),
				'admin_url'     => Admin::detail_url( (int) $r['id'] ),
			)
		);
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function status( \WP_REST_Request $req ) {
		$id     = absint( $req->get_param( 'id' ) );
		$in     = (array) $req->get_json_params();
		$status = sanitize_key( (string) ( $in['status'] ?? '' ) );
		if ( ! Reservations::find( $id ) ) {
			return self::not_found();
		}
		if ( ! isset( Reservations::STATUSES[ $status ] ) || ! Reservations::set_status( $id, $status, get_current_user_id() ) ) {
			return new \WP_Error( 'paw_invalid', __( 'That status cannot be set.', 'pawfectly-reservations' ), array( 'status' => 400 ) );
		}
		$r = Reservations::find( $id );
		if ( ! empty( $in['notify'] ) ) {
			Mailer::status_changed( $r );
		}
		return new \WP_REST_Response( array( 'status' => $status, 'status_label' => Reservations::label( $status ), 'history' => self::history( $r ) ) );
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function notes( \WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		if ( ! Reservations::find( $id ) ) {
			return self::not_found();
		}
		$in = (array) $req->get_json_params();
		Reservations::set_notes( $id, is_string( $in['notes'] ?? null ) ? $in['notes'] : '' );
		return new \WP_REST_Response( array( 'saved' => true ) );
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function delete( \WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		if ( ! Reservations::delete( $id ) ) {
			return self::not_found();
		}
		return new \WP_REST_Response( array( 'deleted' => true ) );
	}

	private static function nights( array $r ): int {
		return ( new \DateTimeImmutable( $r['check_in'] ) )->diff( new \DateTimeImmutable( $r['check_out'] ) )->days;
	}

	private static function history( array $r ): array {
		return array_map(
			static function ( array $e ): array {
				$user = $e['user_id'] ? get_userdata( (int) $e['user_id'] ) : null;
				return array(
					'status' => $e['status'],
					'label'  => Reservations::label( $e['status'] ),
					'at'     => get_date_from_gmt( $e['at'], 'm/d/Y g:i a' ),
					'by'     => $user ? $user->display_name : __( 'Submitted by the owner', 'pawfectly-reservations' ),
				);
			},
			array_reverse( (array) $r['status_log'] )
		);
	}

	private static function not_found(): \WP_Error {
		return new \WP_Error( 'paw_not_found', __( 'Reservation not found.', 'pawfectly-reservations' ), array( 'status' => 404 ) );
	}
}
