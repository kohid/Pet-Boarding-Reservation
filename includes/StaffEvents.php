<?php
/**
 * Staff routes for events (the dashboard's Events view) and event categories. Registered by
 * StaffRest::routes(); every route needs the staff capability, categories need settings access.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class StaffEvents {

	private const PER_PAGE = 20;
	private const TABS     = array( 'upcoming', 'drafts', 'past', 'all' );

	public static function routes(): void {
		$id     = '/staff/events/(?P<id>\d+)';
		$routes = array(
			array( '/staff/events', 'GET', 'list', 'can' ),
			array( '/staff/events', 'POST', 'save', 'can' ),
			array( $id, 'GET', 'one', 'can' ),
			array( $id, 'POST', 'save', 'can' ),
			array( $id, 'DELETE', 'delete', 'can' ),
			array( $id . '/duplicate', 'POST', 'duplicate', 'can' ),
			array( '/staff/event-categories', 'POST', 'categories', 'can_settings' ),
		);
		foreach ( $routes as $r ) {
			register_rest_route(
				Rest::NS,
				$r[0],
				array(
					'methods'             => $r[1],
					'callback'            => array( self::class, $r[2] ),
					'permission_callback' => array( StaffRest::class, $r[3] ),
				)
			);
		}
	}

	/** GET /staff/events?tab=upcoming|drafts|past|all&q=&page= */
	public static function list( \WP_REST_Request $req ): \WP_REST_Response {
		$tab  = sanitize_key( (string) $req->get_param( 'tab' ) );
		$tab  = in_array( $tab, self::TABS, true ) ? $tab : 'upcoming';
		$q    = mb_substr( sanitize_text_field( (string) $req->get_param( 'q' ) ), 0, 80 );
		$page = max( 1, absint( $req->get_param( 'page' ) ?? 1 ) );

		$statuses = array( 'drafts' => array( 'draft' ), 'all' => array( 'publish', 'draft' ) )[ $tab ] ?? array( 'publish' );
		$events   = Events::between( '1970-01-01', '9999-12-31', $q, '', $statuses );
		$now      = new \DateTimeImmutable( 'now', wp_timezone() );
		if ( 'upcoming' === $tab ) {
			$events = array_filter( $events, static fn( array $ev ): bool => 'past' !== Events::status( $ev, $now ) );
		} elseif ( 'past' === $tab ) {
			$events = array_reverse( array_filter( $events, static fn( array $ev ): bool => 'past' === Events::status( $ev, $now ) ) );
		} elseif ( 'all' === $tab ) {
			$events = array_reverse( $events );
		}
		$events = array_values( $events );

		return new \WP_REST_Response(
			array(
				'rows'  => array_map( array( self::class, 'row' ), array_slice( $events, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ) ),
				'pages' => max( 1, (int) ceil( count( $events ) / self::PER_PAGE ) ),
			)
		);
	}

	/** @return \WP_REST_Response|\WP_Error GET /staff/events/<id>: the event as the editor fills it. */
	public static function one( \WP_REST_Request $req ) {
		$ev = Events::get( absint( $req->get_param( 'id' ) ) );
		return $ev ? new \WP_REST_Response( self::for_editor( $ev ) ) : self::not_found();
	}

	/** @return \WP_REST_Response|\WP_Error POST /staff/events and /staff/events/<id>. */
	public static function save( \WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		if ( $id && ! Events::get( $id ) ) {
			return self::not_found();
		}
		$clean = Events::sanitize( (array) $req->get_json_params() );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$saved = Events::save( $clean, $id );
		if ( ! $saved ) {
			return new \WP_Error( 'paw_storage', __( 'Something went wrong. Please try again.', 'pawfectly-reservations' ), array( 'status' => 500 ) );
		}
		return new \WP_REST_Response(
			array(
				'ok'    => true,
				'id'    => $saved,
				'event' => self::for_editor( Events::get( $saved ) ),
			)
		);
	}

	/** @return \WP_REST_Response|\WP_Error DELETE /staff/events/<id>: to the WordPress trash. */
	public static function delete( \WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		if ( ! Events::get( $id ) ) {
			return self::not_found();
		}
		wp_trash_post( $id );
		return new \WP_REST_Response( array( 'deleted' => true ) );
	}

	/** @return \WP_REST_Response|\WP_Error POST /staff/events/<id>/duplicate */
	public static function duplicate( \WP_REST_Request $req ) {
		$copy = Events::duplicate( absint( $req->get_param( 'id' ) ) );
		return $copy ? new \WP_REST_Response( array( 'id' => $copy ) ) : self::not_found();
	}

	/** @return \WP_REST_Response|\WP_Error POST /staff/event-categories {categories:[{slug?, name, color}]} */
	public static function categories( \WP_REST_Request $req ) {
		$saved = Events::save_categories( (array) ( $req->get_json_params()['categories'] ?? array() ) );
		return is_wp_error( $saved ) ? $saved : new \WP_REST_Response( array( 'categories' => $saved ) );
	}

	/** One list row. */
	private static function row( array $ev ): array {
		$one = EventDates::at( $ev['start'] )->format( 'Y-m-d' ) === EventDates::at( $ev['end'] )->format( 'Y-m-d' );
		return array(
			'id'            => (int) $ev['id'],
			'title'         => $ev['title'],
			'emoji'         => $ev['emoji'],
			'when'          => $one ? EventDates::day( $ev ) . ' · ' . EventDates::time_range( $ev ) : EventDates::date_line( $ev ),
			'category_name' => $ev['category_name'],
			'color'         => $ev['color'],
			'venue'         => $ev['venue'],
			'price_text'    => EventDates::price_text( $ev ),
			'status'        => $ev['status'],
			'url'           => 'publish' === $ev['status'] ? $ev['url'] : EventPage::preview_url( (int) $ev['id'] ),
		);
	}

	/** The event with its dates split as the form's date and time fields, plus the preview link. */
	private static function for_editor( array $ev ): array {
		foreach ( array( 'start', 'end' ) as $key ) {
			$at                   = EventDates::at( $ev[ $key ] );
			$ev[ $key . '_date' ] = $at->format( 'Y-m-d' );
			$ev[ $key . '_time' ] = $at->format( 'H:i' );
		}
		$ev['preview_url'] = EventPage::preview_url( (int) $ev['id'] );
		return $ev;
	}

	private static function not_found(): \WP_Error {
		return new \WP_Error( 'paw_not_found', __( 'Event not found.', 'pawfectly-reservations' ), array( 'status' => 404 ) );
	}
}
