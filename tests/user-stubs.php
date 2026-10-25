<?php
/**
 * In-memory stand-ins for WordPress users, user meta and auth used by the account tests.
 */

class WP_User {
	public $ID           = 0;
	public $user_login   = '';
	public $user_email   = '';
	public $user_pass    = '';
	public $display_name = '';
	public $first_name   = '';
	public $last_name    = '';
	public $roles        = array();
	public $caps         = array();
	public function exists() { return $this->ID > 0; }
	public function has_cap( $cap ) { return ! empty( $this->caps[ $cap ] ) || in_array( 'administrator', $this->roles, true ); }
}

$GLOBALS['paw_users']        = array();
$GLOBALS['paw_usermeta']     = array();
$GLOBALS['paw_current_user'] = 0;
$GLOBALS['paw_auth_cookie']  = null;

/** Adds a user directly (for staff/admin fixtures). */
function paw_test_user( string $email, string $password, array $roles, array $caps = array() ): WP_User {
	$u               = new WP_User();
	$u->ID           = count( $GLOBALS['paw_users'] ) + 1;
	$u->user_login   = $email;
	$u->user_email   = $email;
	$u->user_pass    = 'hash:' . $password;
	$u->display_name = $email;
	$u->roles        = $roles;
	$u->caps         = $caps;
	$GLOBALS['paw_users'][ $u->ID ] = $u;
	return $u;
}

function wp_insert_user( $data ) {
	if ( email_exists( $data['user_email'] ) ) {
		return new WP_Error( 'existing_user_email', 'exists' );
	}
	$u = paw_test_user( $data['user_email'], $data['user_pass'], array( $data['role'] ?? 'subscriber' ) );
	$u->user_login   = $data['user_login'];
	$u->first_name   = $data['first_name'] ?? '';
	$u->last_name    = $data['last_name'] ?? '';
	$u->display_name = $data['display_name'] ?? $u->user_login;
	return $u->ID;
}
function wp_update_user( $data ) {
	$u = $GLOBALS['paw_users'][ $data['ID'] ] ?? null;
	if ( ! $u ) {
		return new WP_Error( 'invalid_user_id', 'no' );
	}
	foreach ( array( 'first_name', 'last_name', 'display_name', 'user_email' ) as $k ) {
		if ( isset( $data[ $k ] ) ) {
			$u->$k = $data[ $k ];
		}
	}
	if ( isset( $data['user_pass'] ) ) {
		$u->user_pass = 'hash:' . $data['user_pass'];
	}
	return $u->ID;
}
function email_exists( $email ) {
	foreach ( $GLOBALS['paw_users'] as $u ) {
		if ( strtolower( $u->user_email ) === strtolower( (string) $email ) ) {
			return $u->ID;
		}
	}
	return false;
}
function username_exists( $login ) {
	foreach ( $GLOBALS['paw_users'] as $u ) {
		if ( $u->user_login === $login ) {
			return $u->ID;
		}
	}
	return false;
}
function sanitize_user( $s, $strict = false ) { return preg_replace( '/[^a-z0-9_.\-@]/i', '', (string) $s ); }
function get_user_by( $field, $value ) {
	if ( 'id' === $field ) {
		return $GLOBALS['paw_users'][ (int) $value ] ?? false;
	}
	$id = 'email' === $field ? email_exists( $value ) : false;
	return $id ? $GLOBALS['paw_users'][ $id ] : false;
}
function get_userdata( $id ) { return get_user_by( 'id', $id ); }
function wp_check_password( $password, $hash, $user_id = '' ) { return 'hash:' . $password === $hash; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['paw_usermeta'][ $id ][ $key ] = $value; return true; }
function get_user_meta( $id, $key = '', $single = false ) { return $GLOBALS['paw_usermeta'][ $id ][ $key ] ?? ''; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['paw_usermeta'][ $id ][ $key ] ); return true; }
function wp_set_current_user( $id ) { $GLOBALS['paw_current_user'] = (int) $id; return get_user_by( 'id', $id ); }
function wp_set_auth_cookie( $id, $remember = false ) { $GLOBALS['paw_auth_cookie'] = (int) $id; }
function get_current_user_id() { return (int) $GLOBALS['paw_current_user']; }
function wp_get_current_user() { return get_user_by( 'id', get_current_user_id() ) ?: new WP_User(); }
function is_user_logged_in() { return get_current_user_id() > 0; }
function current_user_can( $cap ) { $u = wp_get_current_user(); return $u->exists() && $u->has_cap( $cap ); }
function wp_login_url( $redirect = '' ) { return 'https://example.test/wp-login.php' . ( $redirect ? '?redirect_to=' . rawurlencode( $redirect ) : '' ); }
function wp_lostpassword_url( $redirect = '' ) { return 'https://example.test/wp-login.php?action=lostpassword'; }
function wp_logout_url( $redirect = '' ) { return 'https://example.test/wp-login.php?action=logout'; }
function add_query_arg( $args, $url ) { return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args ); }
function add_role( $role, $name, $caps ) { $GLOBALS['paw_added_roles'][ $role ] = $caps; return true; }
$GLOBALS['paw_actions'] = array();
function do_action( $hook, ...$args ) { $GLOBALS['paw_actions'][] = $hook; }
/** Core's wp_authenticate runs the `authenticate` filter (lockout and 2FA plugins); the stand-in records the call. */
function wp_authenticate( $login, $password ) {
	$GLOBALS['paw_actions'][] = 'wp_authenticate';
	$u = get_user_by( 'id', username_exists( $login ) ?: 0 );
	return $u && wp_check_password( $password, $u->user_pass ) ? $u : new WP_Error( 'incorrect_password', 'no' );
}
function get_edit_profile_url( $id = 0 ) { return 'https://example.test/wp-admin/profile.php'; }
