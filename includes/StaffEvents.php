<?php
/**
 * Staff routes for events (the dashboard's Events view), their sign-ups and event categories.
 * Registered by StaffRest::routes(); every route needs the staff capability, categories need
 * settings access. The sign-ups CSV is an admin-post handler (a download, not JSON).
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
			array( $id . '/signups', 'GET', 'signups', 'can' ),
			array( '/staff/signups/(?P<id>\d+)/status', 'POST', 'signup_status', 'can' ),
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
		$now      = new \DateTimeImmutable( 'now', wp_timezone() );
		$today    = $now->format( 'Y-m-d' );
		$range    = array(
			'upcoming' => array( $today, '9999-12-31' ),
			'past'     => array( '1970-01-01', $today ),
		)[ $tab ] ?? array( '1970-01-01', '9999-12-31' );
		$events   = Events::between( $range[0], $range[1], $q, '', $statuses );
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
		if ( $id && ! self::sessions_kept( Events::get( $id ), $clean ) ) {
			return new \WP_Error(
				'paw_invalid',
				__( 'Please check the highlighted fields.', 'pawfectly-reservations' ),
				array(
					'status' => 400,
					'fields' => array( 'sections.sessions' => __( "Sessions that have sign-ups can't be removed, moved or given new times. You can rename them, or add new sessions at the end.", 'pawfectly-reservations' ) ),
				)
			);
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

	/** @return \WP_REST_Response|\WP_Error GET /staff/events/<id>/signups: session meters, counts, rows and the CSV link. */
	public static function signups( \WP_REST_Request $req ) {
		$ev = Events::get( absint( $req->get_param( 'id' ) ) );
		if ( ! $ev ) {
			return self::not_found();
		}
		$all      = Signups::for_event( (int) $ev['id'] );
		$active   = array_filter( $all, static fn( array $su ): bool => 'cancelled' !== $su['status'] );
		$sessions = array();
		foreach ( Signups::session_labels( $ev ) as $n => $label ) {
			$sessions[] = array(
				'label' => $label,
				'taken' => Signups::taken( (int) $ev['id'], (int) $n ),
				'spots' => (int) $ev['spots'],
			);
		}
		usort( $all, static fn( array $a, array $b ): int => array( $b['created'], $b['id'] ) <=> array( $a['created'], $a['id'] ) );
		return new \WP_REST_Response(
			array(
				'sessions' => $sessions,
				'counts'   => array(
					'signups' => count( $active ),
					'dogs'    => (int) array_sum( array_column( $active, 'dog_count' ) ),
				),
				'rows'     => array_map( array( self::class, 'signup_row' ), $all ),
				// A plain URL: the script sets it as an href (wp_nonce_url() would escape the &).
				'csv_url'  => add_query_arg(
					array(
						'action'   => 'paw_signups_csv',
						'event'    => (int) $ev['id'],
						'_wpnonce' => wp_create_nonce( 'paw_signups_csv_' . $ev['id'] ),
					),
					admin_url( 'admin-post.php' )
				),
			)
		);
	}

	/** @return \WP_REST_Response|\WP_Error POST /staff/signups/<id>/status {status: confirmed|attended|cancelled} */
	public static function signup_status( \WP_REST_Request $req ) {
		$id = absint( $req->get_param( 'id' ) );
		if ( ! Signups::get( $id ) ) {
			return new \WP_Error( 'paw_not_found', __( 'Sign-up not found.', 'pawfectly-reservations' ), array( 'status' => 404 ) );
		}
		$status = sanitize_key( (string) ( $req->get_json_params()['status'] ?? '' ) );
		if ( ! Signups::set_status( $id, $status ) ) {
			return new \WP_Error( 'paw_invalid', __( 'Unknown status.', 'pawfectly-reservations' ), array( 'status' => 400 ) );
		}
		return new \WP_REST_Response( array( 'row' => self::signup_row( Signups::get( $id ) ) ) );
	}

	/** True when the current user may download this event's sign-ups with this nonce. */
	public static function csv_allowed( int $event, string $nonce ): bool {
		return current_user_can( Activator::CAPABILITY ) && (bool) wp_verify_nonce( $nonce, 'paw_signups_csv_' . $event );
	}

	/** The CSV rows, with every cell made safe for spreadsheet apps. */
	public static function csv_lines( int $event ): array {
		return array_map( static fn( array $row ): array => array_map( static fn( $cell ): string => Admin::csv_cell( (string) $cell ), $row ), Signups::csv_rows( $event ) );
	}

	/** admin-post.php?action=paw_signups_csv&event=<id>&_wpnonce=…: streams the event's sign-ups. */
	public static function handle_csv(): void {
		$event = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$ev    = Events::get( $event );
		if ( ! $ev || ! self::csv_allowed( $event, $nonce ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'pawfectly-reservations' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="signups-' . sanitize_file_name( (string) get_post_field( 'post_name', $event ) ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads UTF-8 names correctly.
		foreach ( self::csv_lines( $event ) as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}

	/** One sign-up as the dashboard shows it (never the IP or the member code itself). */
	private static function signup_row( array $su ): array {
		return array(
			'id'         => $su['id'],
			'reference'  => $su['reference'],
			'status'     => $su['status'],
			'name'       => trim( $su['first_name'] . ' ' . $su['last_name'] ),
			'email'      => $su['email'],
			'phone'      => $su['phone'],
			'session'    => $su['session_label'],
			'dogs'       => $su['dogs'],
			'dog_count'  => $su['dog_count'],
			'coupon'     => $su['coupon'],
			'total_text' => Signups::total_text( $su ),
			'notes'      => $su['notes'],
			'created'    => $su['created'],
		);
	}

	/**
	 * Sign-ups point at their session by its place in the list, so a session with active
	 * sign-ups must keep its place and its times.
	 */
	private static function sessions_kept( array $old, array $clean ): bool {
		$before = $old['sections']['sessions']['rows'];
		$after  = $clean['sections']['sessions']['rows'] ?? array();
		foreach ( array_unique( array_column( Signups::for_event( (int) $old['id'], true ), 'session' ) ) as $n ) {
			if ( $n < 0 || ! isset( $before[ $n ] ) ) {
				continue;
			}
			if ( ! isset( $after[ $n ] ) || $after[ $n ]['from'] !== $before[ $n ]['from'] || $after[ $n ]['to'] !== $before[ $n ]['to'] ) {
				return false;
			}
		}
		return true;
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
			'dogs'          => (int) array_sum( array_column( Signups::for_event( (int) $ev['id'], true ), 'dog_count' ) ),
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
