<?php
/**
 * An in-memory stand-in for WordPress posts, post meta, terms and term meta: just what the
 * events code calls. Load after bootstrap.php.
 */

class WP_Post {
	public $ID;
	public $post_type    = 'post';
	public $post_status  = 'publish';
	public $post_title   = '';
	public $post_content = '';
	public $post_name    = '';
	public function __construct( array $f ) {
		foreach ( $f as $k => $v ) {
			$this->$k = $v;
		}
	}
}

$GLOBALS['paw_posts']      = array();
$GLOBALS['paw_post_meta']  = array();
$GLOBALS['paw_terms']      = array();
$GLOBALS['paw_term_meta']  = array();
$GLOBALS['paw_post_terms'] = array();
$GLOBALS['paw_thumbs']     = array();
$GLOBALS['paw_next_id']    = 100;

function register_post_type( $t, $a = array() ) { $GLOBALS['paw_registered_types'][ $t ] = $a; }
function register_taxonomy( $t, $o, $a = array() ) { $GLOBALS['paw_registered_tax'][ $t ] = $a; }
function register_post_meta( $t, $k, $a = array() ) { return true; }
function esc_url_raw( $u ) { $u = trim( (string) $u ); return preg_match( '#^https?://[^\s<>"]+$#i', $u ) ? $u : ''; }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }

function wp_insert_post( $a, $wp_error = false ) {
	$id = ++$GLOBALS['paw_next_id'];
	$GLOBALS['paw_posts'][ $id ] = new WP_Post(
		array(
			'ID'           => $id,
			'post_type'    => $a['post_type'] ?? 'post',
			'post_status'  => $a['post_status'] ?? 'draft',
			'post_title'   => $a['post_title'] ?? '',
			'post_content' => $a['post_content'] ?? '',
			'post_name'    => sanitize_title( $a['post_title'] ?? '' ),
		)
	);
	return $id;
}
function wp_update_post( $a, $wp_error = false ) {
	$p = $GLOBALS['paw_posts'][ (int) $a['ID'] ] ?? null;
	if ( ! $p ) {
		return 0;
	}
	foreach ( array( 'post_status', 'post_title', 'post_content' ) as $k ) {
		if ( isset( $a[ $k ] ) ) {
			$p->$k = $a[ $k ];
		}
	}
	return $p->ID;
}
function get_post( $id ) { return $GLOBALS['paw_posts'][ (int) $id ] ?? null; }
function wp_trash_post( $id ) { $p = get_post( $id ); if ( $p ) { $p->post_status = 'trash'; } return $p; }
function get_post_meta( $id, $k = '', $single = false ) {
	$v = $GLOBALS['paw_post_meta'][ (int) $id ][ $k ] ?? null;
	return $single ? ( $v ?? '' ) : ( null === $v ? array() : array( $v ) );
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['paw_post_meta'][ (int) $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['paw_post_meta'][ (int) $id ][ $k ] ); return true; }
if ( ! function_exists( 'get_permalink' ) ) { // Page tests define their own.
	function get_permalink( $p = 0 ) {
		$p = is_object( $p ) ? $p : get_post( $p );
		return $p ? 'https://example.test/event/' . $p->post_name . '/' : '';
	}
}
function get_post_thumbnail_id( $id ) { return $GLOBALS['paw_thumbs'][ (int) $id ] ?? 0; }
function set_post_thumbnail( $id, $att ) { $GLOBALS['paw_thumbs'][ (int) $id ] = (int) $att; return true; }
function delete_post_thumbnail( $id ) { unset( $GLOBALS['paw_thumbs'][ (int) $id ] ); return true; }

function get_posts( $a = array() ) {
	$GLOBALS['paw_last_get_posts'] = $a;
	$types    = (array) ( $a['post_type'] ?? 'post' );
	$statuses = (array) ( $a['post_status'] ?? 'publish' );
	$out      = array_filter(
		$GLOBALS['paw_posts'],
		static function ( $p ) use ( $a, $types, $statuses ) {
			if ( ! in_array( $p->post_type, $types, true ) || ( ! in_array( 'any', $statuses, true ) && ! in_array( $p->post_status, $statuses, true ) ) ) {
				return false;
			}
			if ( '' !== (string) ( $a['s'] ?? '' ) && false === stripos( $p->post_title, (string) $a['s'] ) ) {
				return false;
			}
			foreach ( (array) ( $a['tax_query'] ?? array() ) as $tq ) {
				$slugs = array_column( wp_get_object_terms( $p->ID, $tq['taxonomy'] ), 'slug' );
				if ( ! array_intersect( (array) $tq['terms'], $slugs ) ) {
					return false;
				}
			}
			foreach ( (array) ( $a['meta_query'] ?? array() ) as $mq ) {
				if ( ! is_array( $mq ) ) {
					continue; // 'relation'.
				}
				$v = (string) get_post_meta( $p->ID, $mq['key'], true );
				$c = strcmp( $v, (string) $mq['value'] );
				$ok = array( '<=' => $c <= 0, '>=' => $c >= 0, '<' => $c < 0, '>' => $c > 0, '=' => 0 === $c )[ $mq['compare'] ?? '=' ];
				if ( ! $ok ) {
					return false;
				}
			}
			return true;
		}
	);
	if ( isset( $a['meta_key'] ) ) {
		$key = $a['meta_key'];
		uasort( $out, static fn( $x, $y ) => strcmp( (string) get_post_meta( $x->ID, $key, true ), (string) get_post_meta( $y->ID, $key, true ) ) );
	}
	if ( 'DESC' === strtoupper( (string) ( $a['order'] ?? 'ASC' ) ) ) {
		$out = array_reverse( $out );
	}
	$out = array_values( $out );
	$n   = (int) ( $a['posts_per_page'] ?? 5 );
	$out = array_slice( $out, (int) ( $a['offset'] ?? 0 ), $n < 0 ? null : $n );
	return 'ids' === ( $a['fields'] ?? '' ) ? array_map( static fn( $p ) => $p->ID, $out ) : $out;
}

function paw_term( array $t ) { return (object) $t; }
function wp_insert_term( $name, $tax, $args = array() ) {
	foreach ( get_terms( array( 'taxonomy' => $tax ) ) as $t ) {
		if ( $t->name === $name ) { // Like WordPress: a name already in use is refused.
			return new WP_Error( 'term_exists', 'A term with the name provided already exists.', $t->term_id );
		}
	}
	$id   = ++$GLOBALS['paw_next_id'];
	$slug = $args['slug'] ?? sanitize_title( $name );
	$GLOBALS['paw_terms'][ $id ] = paw_term( array( 'term_id' => $id, 'name' => $name, 'slug' => $slug, 'taxonomy' => $tax ) );
	return array( 'term_id' => $id );
}
function wp_update_term( $id, $tax, $args = array() ) {
	if ( isset( $GLOBALS['paw_terms'][ $id ], $args['name'] ) ) {
		$GLOBALS['paw_terms'][ $id ]->name = $args['name'];
	}
	return array( 'term_id' => $id );
}
function wp_delete_term( $id, $tax ) {
	unset( $GLOBALS['paw_terms'][ $id ] );
	foreach ( $GLOBALS['paw_post_terms'] as &$by_tax ) {
		$by_tax[ $tax ] = array_values( array_diff( $by_tax[ $tax ] ?? array(), array( $id ) ) );
	}
	return true;
}
function get_terms( $a = array() ) {
	$tax = is_array( $a ) ? ( $a['taxonomy'] ?? '' ) : $a;
	return array_values( array_filter( $GLOBALS['paw_terms'], static fn( $t ) => $t->taxonomy === $tax ) );
}
/** Like core: the term, or null when there is none. */
function get_term( $id, $tax = '' ) {
	foreach ( get_terms( array( 'taxonomy' => $tax ) ) as $t ) {
		if ( (int) $t->term_id === (int) $id ) {
			return $t;
		}
	}
	return null;
}
function get_term_by( $field, $value, $tax ) {
	foreach ( get_terms( array( 'taxonomy' => $tax ) ) as $t ) {
		if ( (string) $t->$field === (string) $value ) {
			return $t;
		}
	}
	return false;
}
function wp_set_object_terms( $id, $terms, $tax ) {
	$ids = array();
	foreach ( (array) $terms as $t ) {
		$term = is_int( $t ) ? ( $GLOBALS['paw_terms'][ $t ] ?? null ) : get_term_by( 'slug', $t, $tax );
		if ( $term ) {
			$ids[] = $term->term_id;
		}
	}
	$GLOBALS['paw_post_terms'][ (int) $id ][ $tax ] = $ids;
	return $ids;
}
function wp_get_object_terms( $id, $tax ) {
	return array_values( array_filter( array_map( static fn( $tid ) => $GLOBALS['paw_terms'][ $tid ] ?? null, $GLOBALS['paw_post_terms'][ (int) $id ][ $tax ] ?? array() ) ) );
}
function get_term_meta( $id, $k, $single = false ) { return $GLOBALS['paw_term_meta'][ $id ][ $k ] ?? ''; }
function update_term_meta( $id, $k, $v ) { $GLOBALS['paw_term_meta'][ $id ][ $k ] = $v; return true; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }

function get_the_terms( $id, $tax ) { $t = wp_get_object_terms( $id, $tax ); return $t ? $t : false; }
