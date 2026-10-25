<?php
/**
 * Reading and writing reservation rows.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Reservations {

	public const STATUSES = array(
		'pending'    => 'Pending',
		'confirmed'  => 'Confirmed',
		'declined'   => 'Declined',
		'checked_in' => 'Checked in',
		'completed'  => 'Completed',
		'cancelled'  => 'Cancelled',
	);

	private const ORDERBY = array( 'check_in', 'created_at', 'reference' );
	private const JSON    = array( 'details', 'files', 'status_log' );

	/** Translated label for a status key. */
	public static function label( string $status ): string {
		$labels = array(
			'pending'    => __( 'Pending', 'pawfectly-reservations' ),
			'confirmed'  => __( 'Confirmed', 'pawfectly-reservations' ),
			'declined'   => __( 'Declined', 'pawfectly-reservations' ),
			'checked_in' => __( 'Checked in', 'pawfectly-reservations' ),
			'completed'  => __( 'Completed', 'pawfectly-reservations' ),
			'cancelled'  => __( 'Cancelled', 'pawfectly-reservations' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/** @param array{columns: array, details: array} $clean Output of Validator::validate(). */
	public static function build_row( array $clean, string $reference, array $files, string $signature_path, string $ip, string $now_utc ): array {
		return array_merge(
			array(
				'reference' => $reference,
				'status'    => 'pending',
			),
			$clean['columns'],
			array(
				'details'        => wp_json_encode( $clean['details'], JSON_UNESCAPED_UNICODE ),
				'files'          => wp_json_encode( $files, JSON_UNESCAPED_UNICODE ),
				'signature_path' => $signature_path,
				'signed_at'      => $now_utc,
				'signed_ip'      => $ip,
				'status_log'     => wp_json_encode( array( array( 'status' => 'pending', 'user_id' => 0, 'at' => $now_utc ) ) ),
				'created_at'     => $now_utc,
				'updated_at'     => $now_utc,
			)
		);
	}

	/** @return int|false The new row id. */
	public static function insert( array $row ) {
		global $wpdb;
		return $wpdb->insert( Activator::table(), $row ) ? (int) $wpdb->insert_id : false;
	}

	public static function reference_exists( string $reference ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . Activator::table() . ' WHERE reference = %s LIMIT 1', $reference ) );
	}

	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Activator::table() . ' WHERE id = %d', $id ), ARRAY_A );
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Filtered, paged list for wp-admin and the CSV export.
	 *
	 * @param array $args status, search, from, to (Y-m-d, on check-in), orderby, order, page, per_page (0 = all).
	 * @return array{rows: array<int, array>, total: int}
	 */
	public static function query( array $args ): array {
		global $wpdb;
		$table = Activator::table();
		$where = array( '1=1' );
		$vals  = array();

		if ( ! empty( $args['status'] ) && isset( self::STATUSES[ $args['status'] ] ) ) {
			$where[] = 'status = %s';
			$vals[]  = $args['status'];
		}
		if ( isset( $args['search'] ) && '' !== trim( (string) $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( trim( (string) $args['search'] ) ) . '%';
			$where[] = "(reference LIKE %s OR owner_email LIKE %s OR owner_phone LIKE %s OR pet_name LIKE %s OR CONCAT(owner_first_name, ' ', owner_last_name) LIKE %s)";
			array_push( $vals, $like, $like, $like, $like, $like );
		}
		foreach ( array( 'from' => '>=', 'to' => '<=' ) as $key => $op ) {
			if ( ! empty( $args[ $key ] ) && self::is_date( (string) $args[ $key ] ) ) {
				$where[] = "check_in {$op} %s";
				$vals[]  = $args[ $key ];
			}
		}

		$sql_where = implode( ' AND ', $where );
		$orderby   = in_array( $args['orderby'] ?? '', self::ORDERBY, true ) ? $args['orderby'] : 'check_in';
		$order     = 'desc' === strtolower( (string) ( $args['order'] ?? '' ) ) ? 'DESC' : 'ASC';
		$per_page  = max( 0, (int) ( $args['per_page'] ?? 20 ) );
		$page      = max( 1, (int) ( $args['page'] ?? 1 ) );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY {$orderby} {$order}, id DESC";
		if ( $per_page > 0 ) {
			$list_sql .= ' LIMIT ' . $per_page . ' OFFSET ' . ( ( $page - 1 ) * $per_page );
		}

		$total = (int) $wpdb->get_var( $vals ? $wpdb->prepare( $count_sql, $vals ) : $count_sql );
		$rows  = $wpdb->get_results( $vals ? $wpdb->prepare( $list_sql, $vals ) : $list_sql, ARRAY_A );

		return array(
			'rows'  => array_map( array( self::class, 'decode' ), (array) $rows ),
			'total' => $total,
		);
	}

	/** @return array<string, int> Count per status, every status present. */
	public static function counts(): array {
		global $wpdb;
		$counts = array_fill_keys( array_keys( self::STATUSES ), 0 );
		foreach ( (array) $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . Activator::table() . ' GROUP BY status', ARRAY_A ) as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['n'];
			}
		}
		return $counts;
	}

	public static function set_status( int $id, string $status, int $user_id ): bool {
		global $wpdb;
		$reservation = self::find( $id );
		if ( ! $reservation || ! isset( self::STATUSES[ $status ] ) ) {
			return false;
		}
		$now   = current_time( 'mysql', true );
		$log   = $reservation['status_log'];
		$log[] = array( 'status' => $status, 'user_id' => $user_id, 'at' => $now );
		return false !== $wpdb->update(
			Activator::table(),
			array( 'status' => $status, 'status_log' => wp_json_encode( $log ), 'updated_at' => $now ),
			array( 'id' => $id )
		);
	}

	public static function set_notes( int $id, string $notes ): bool {
		global $wpdb;
		return (bool) $wpdb->update(
			Activator::table(),
			array( 'staff_notes' => sanitize_textarea_field( $notes ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id )
		);
	}

	/** Permanently removes a reservation and its private files (vaccination records and signature). */
	public static function delete( int $id ): bool {
		global $wpdb;
		$reservation = self::find( $id );
		if ( ! $reservation ) {
			return false;
		}
		$files = (array) $reservation['files'];
		if ( '' !== (string) $reservation['signature_path'] ) {
			$files[] = array( 'path' => $reservation['signature_path'] );
		}
		Uploads::delete( $files );
		return (bool) $wpdb->delete( Activator::table(), array( 'id' => $id ) );
	}

	private static function decode( array $row ): array {
		$row['id'] = (int) $row['id'];
		foreach ( self::JSON as $col ) {
			$row[ $col ] = json_decode( (string) ( $row[ $col ] ?? '' ), true ) ?: array();
		}
		return $row;
	}

	private static function is_date( string $value ): bool {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}
}
