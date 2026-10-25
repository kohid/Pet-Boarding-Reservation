<?php
/**
 * Owner accounts: register, sign in (never staff), verification tokens, prefill, and what an owner may see.
 * Run: php tests/accounts-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';

use Pawfectly\Accounts;
use Pawfectly\Activator;
use Pawfectly\Reservations;

$id = Accounts::register( 'Zoë', "O'Brien", 'zoe+dogs@example.com', '555 010 2030', 'correct horse' );
$u  = get_user_by( 'id', $id );
t( 'register returns a user id', is_int( $id ) && $id > 0 );
t( 'customer role', array( Accounts::ROLE ) === $u->roles );
t( 'names and display name', 'Zoë' === $u->first_name && "Zoë O'Brien" === $u->display_name );
t( 'phone stored', '555 010 2030' === get_user_meta( $id, Accounts::META_PHONE, true ) );
t( 'starts unverified', ! Accounts::is_verified( $id ) );
$dup = Accounts::register( 'Z', 'O', 'ZOE+dogs@example.com', '', 'correct horse' );
t( 'existing email refused', is_wp_error( $dup ) && 'paw_email_exists' === $dup->get_error_code() );
$id2 = Accounts::register( 'Zed', 'Two', 'zoe+dogs@example.org', '', 'correct horse' );
t( 'login names stay unique', get_user_by( 'id', $id2 )->user_login !== $u->user_login );

$ok = Accounts::authenticate( 'zoe+dogs@example.com', 'correct horse' );
t( 'right password signs in', $ok instanceof WP_User && $id === $ok->ID );
$bad = Accounts::authenticate( 'zoe+dogs@example.com', 'wrong' );
t( 'wrong password refused', is_wp_error( $bad ) && 'paw_login' === $bad->get_error_code() );
$none = Accounts::authenticate( 'nobody@example.com', 'whatever1' );
t( 'unknown email gives the same answer', is_wp_error( $none ) && 'paw_login' === $none->get_error_code() );
paw_test_user( 'boss@example.com', 'admin pass', array( 'administrator' ) );
paw_test_user( 'desk@example.com', 'desk pass1', array( Accounts::STAFF_ROLE ), array( Activator::CAPABILITY => true ) );
$admin = Accounts::authenticate( 'boss@example.com', 'admin pass' );
$desk  = Accounts::authenticate( 'desk@example.com', 'desk pass1' );
t( 'administrators sent to the WordPress login', is_wp_error( $admin ) && 'paw_staff_login' === $admin->get_error_code() && str_contains( $admin->get_error_message(), 'wp-login.php' ) );
t( 'staff sent to the WordPress login', is_wp_error( $desk ) && 'paw_staff_login' === $desk->get_error_code() );
t( 'staff refusal is a 403', 403 === $desk->get_error_data()['status'] );
$admin_wrong = Accounts::authenticate( 'boss@example.com', 'wrong password' );
t( 'staff refusal does not depend on the password (no password oracle)', is_wp_error( $admin_wrong ) && 'paw_staff_login' === $admin_wrong->get_error_code() );
$ed = paw_test_user( 'editor@example.com', 'editor pass', array( 'editor' ) );
$ed->caps = array( 'edit_posts' => true );
t( 'editors and other privileged roles sent to the WordPress login', 'paw_staff_login' === Accounts::authenticate( 'editor@example.com', 'editor pass' )->get_error_code() );
$sub = paw_test_user( 'sub@example.com', 'subscriber1', array( 'subscriber' ) );
t( 'subscribers may use the customer sign-in', Accounts::authenticate( 'sub@example.com', 'subscriber1' ) instanceof WP_User );
$GLOBALS['paw_actions'] = array();
Accounts::authenticate( 'zoe+dogs@example.com', 'correct horse' );
t( "sign-in goes through WordPress's authenticate checks", in_array( 'wp_authenticate', $GLOBALS['paw_actions'], true ) );
t( 'customer-login rule', Accounts::may_use_customer_login( get_user_by( 'id', $id ) ) && ! Accounts::may_use_customer_login( $ed ) );

Accounts::start_session( $id );
t( 'session started', $id === get_current_user_id() && $id === $GLOBALS['paw_auth_cookie'] );

$token = Accounts::new_token( $id );
t( 'token is 64 hex characters', (bool) preg_match( '/^[0-9a-f]{64}$/', $token ) );
t( 'only the hash is stored', hash( 'sha256', $token ) === get_user_meta( $id, Accounts::META_HASH, true ) && ! in_array( $token, $GLOBALS['paw_usermeta'][ $id ], true ) );
t( 'wrong token refused', ! Accounts::verify( $id, str_repeat( 'a', 64 ) ) && ! Accounts::is_verified( $id ) );
t( 'right token verifies', Accounts::verify( $id, $token ) && Accounts::is_verified( $id ) );
t( 'token works once', ! Accounts::verify( $id, $token ) );
$old = Accounts::new_token( $id2 );
update_user_meta( $id2, Accounts::META_EXPIRES, time() - 1 );
t( 'expired token refused', ! Accounts::verify( $id2, $old ) && ! Accounts::is_verified( $id2 ) );
t( 'verify link points at the REST route', str_contains( Accounts::verify_url( $id, 'abc' ), 'wp-json/pawfectly/v1/account/verify?u=' . $id . '&t=abc' ) );

// Prefill: from the account when there is no reservation yet.
$p = Accounts::prefill( $id );
t( 'prefill from the account', 'Zoë' === $p['owner_first_name'] && 'zoe+dogs@example.com' === $p['owner_email'] && '555 010 2030' === $p['owner_phone'] );

// Prefill: from the last reservation, never stay, health or waiver answers.
$last = array(
	'id' => 9, 'owner_first_name' => 'Zoë', 'owner_last_name' => "O'Brien", 'owner_email' => 'old@example.com', 'owner_phone' => '555 999 0000',
	'pet_name' => 'Biscuit', 'pet_type' => 'dog', 'check_in' => '2026-11-01', 'check_out' => '2026-11-03',
	'details' => json_encode( array( 'pet_breed' => 'Beagle', 'vet_name' => 'Dr. Who', 'vaccinations_current' => 'yes', 'feeding_schedule' => 'Twice', 'signed_date' => '2026-10-25', 'waiver_text' => '<p>x</p>' ) ),
	'files' => '[]', 'status_log' => '[]',
);
$wpdb->row_queue = array( $last );
$p = Accounts::prefill( $id );
t( 'prefill pet from the last reservation', 'Biscuit' === $p['pet_name'] && 'Beagle' === $p['pet_breed'] && 'Twice' === $p['feeding_schedule'] && 'Dr. Who' === $p['vet_name'] );
t( 'prefill uses the current account email', 'zoe+dogs@example.com' === $p['owner_email'] );
t( 'prefill keeps the reservation phone', '555 999 0000' === $p['owner_phone'] );
foreach ( array( 'check_in', 'check_out', 'vaccinations_current', 'signature', 'waiver_agree', 'signed_date', 'waiver_text', 'vaccination_files' ) as $never ) {
	t( "prefill never includes $never", ! array_key_exists( $never, $p ) );
}

// Which reservations an owner sees.
$wpdb->queries = array();
Reservations::for_owner( 5, 'zoe@example.com', false );
$sql = implode( "\n", $wpdb->queries );
t( 'unverified: own reservations only', str_contains( $sql, 'user_id = 5' ) && ! str_contains( $sql, 'owner_email' ) );
$wpdb->queries = array();
Reservations::for_owner( 5, 'zoe@example.com', true );
$sql = implode( "\n", $wpdb->queries );
t( 'verified: also reservations made with that email', str_contains( $sql, 'user_id = 5' ) && str_contains( $sql, "owner_email = 'zoe@example.com'" ) );

Accounts::add_roles();
t( 'customer role can only read', array( 'read' => true ) === $GLOBALS['paw_added_roles'][ Accounts::ROLE ] );
t( 'staff role manages reservations', ! empty( $GLOBALS['paw_added_roles'][ Accounts::STAFF_ROLE ][ Activator::CAPABILITY ] ) );

done();
