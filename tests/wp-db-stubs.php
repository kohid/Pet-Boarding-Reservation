<?php
/**
 * Stand-ins for $wpdb and the REST request/response classes. The fake database
 * keeps rows in memory and records every SQL string so tests can inspect them.
 */

define( 'ARRAY_A', 'ARRAY_A' );

class Fake_WPDB {
	public $prefix     = 'wp_';
	public $insert_id  = 0;
	public $last_error = '';
	public $rows       = array();
	public $queries    = array();
	public $fail_insert = false;
	/** @var array<int, mixed> Values handed back by get_var(), in order; then null. */
	public $var_queue   = array();
	/** @var array<int, array|null> Rows handed back by get_row() before any id lookup. */
	public $row_queue   = array();

	public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }

	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$query = str_replace( array( "'%s'", '%s', '%d' ), array( '%s', "'%s'", '%d' ), $query );
		return vsprintf( $query, array_map( fn( $a ) => is_int( $a ) ? $a : addslashes( (string) $a ), $args ) );
	}

	public function insert( $table, $data, $format = null ) {
		$this->queries[] = "INSERT $table";
		if ( $this->fail_insert ) {
			$this->last_error = 'insert failed';
			return false;
		}
		$this->insert_id                = count( $this->rows ) + 1;
		$this->rows[ $this->insert_id ] = array( 'id' => (string) $this->insert_id ) + array_map( 'strval', $data );
		return 1;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$this->queries[] = "UPDATE $table";
		$id              = $where['id'];
		if ( ! isset( $this->rows[ $id ] ) ) {
			return 0;
		}
		$this->rows[ $id ] = array_merge( $this->rows[ $id ], array_map( 'strval', $data ) );
		return 1;
	}

	public function delete( $table, $where, $format = null ) {
		$this->queries[] = "DELETE $table";
		if ( ! isset( $this->rows[ $where['id'] ] ) ) {
			return 0;
		}
		unset( $this->rows[ $where['id'] ] );
		return 1;
	}

	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	/** @var array<int, array> Columns handed back by get_col(), in order. */
	public $col_queue = array();

	public function get_col( $sql ) {
		$this->queries[] = $sql;
		return array_shift( $this->col_queue ) ?? array();
	}

	public function get_var( $sql ) {
		$this->queries[] = $sql;
		return array_shift( $this->var_queue );
	}

	public function get_row( $sql, $output = null ) {
		$this->queries[] = $sql;
		if ( $this->row_queue ) {
			return array_shift( $this->row_queue );
		}
		return preg_match( '/WHERE id = (\d+)/', $sql, $m ) ? ( $this->rows[ (int) $m[1] ] ?? null ) : null;
	}

	public function get_results( $sql, $output = null ) {
		$this->queries[] = $sql;
		return array_values( $this->rows );
	}
}

$GLOBALS['wpdb'] = new Fake_WPDB();

class WP_REST_Request {
	public $params  = array();
	public $files   = array();
	public $headers = array();
	public function get_body_params() { return $this->params; }
	public function get_file_params() { return $this->files; }
	public function get_header( $key ) { return $this->headers[ strtolower( str_replace( '-', '_', $key ) ) ] ?? null; }
}

class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}

