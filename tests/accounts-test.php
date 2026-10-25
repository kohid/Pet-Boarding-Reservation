<?php
/**
 * Owner accounts: register, sign in (never staff), verification tokens, prefill, and what an owner may see.
 * Run: php tests/accounts-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

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
$desk  = Accounts::authenticate( 'desk@example.com', 'desk pass1', true );
t( 'administrators sign in here too', $admin instanceof WP_User );
t( 'staff sign in here too, and remember me reaches WordPress', $desk instanceof WP_User && true === $GLOBALS['paw_remember'] );
t( 'staff with the wrong password get the usual answer', 'paw_login' === Accounts::authenticate( 'desk@example.com', 'wrong password' )->get_error_code() );
$boss = get_user_by( 'email', 'boss@example.com' );
$boss->user_login = 'boss';
t( 'a username works as well as an email', Accounts::authenticate( 'boss', 'admin pass' ) instanceof WP_User );
$ed = paw_test_user( 'editor@example.com', 'editor pass', array( 'editor' ) );
$ed->caps = array( 'edit_posts' => true );
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
t( 'verify link opens a site page (where the sign-in cookie counts)', str_contains( Accounts::verify_url( $id2, 'abc' ), 'paw_verify_u=' . $id2 . '&paw_verify_t=abc' ) && ! str_contains( Accounts::verify_url( $id2, 'abc' ), 'wp-json' ) );

// Confirming needs the account's own session: someone who signed up with your email can't have you confirm it.
$t2 = Accounts::new_token( $id2 );
t( 'signed out: asked to sign in, token kept', 'signin' === Accounts::verify_request( $id2, $t2, 0 ) && ! Accounts::is_verified( $id2 ) );
t( 'signed in as someone else: refused, token kept', 'wrong_user' === Accounts::verify_request( $id2, $t2, $id ) && ! Accounts::is_verified( $id2 ) );
t( 'signed in as the account: confirmed', 'ok' === Accounts::verify_request( $id2, $t2, $id2 ) && Accounts::is_verified( $id2 ) );
t( 'used link: bad', 'bad' === Accounts::verify_request( $id2, $t2, $id2 ) );
t( 'after confirming, sent on with paw_verified=1', str_contains( Accounts::verify_redirect( 'ok', $id2, $t2 ), 'paw_verified=1' ) );
$to_signin = rawurldecode( Accounts::verify_redirect( 'signin', $id2, $t2 ) ); // No My Profile page here: wp-login.php?redirect_to=…
t( 'sign-in redirect keeps the link so it completes after signing in', str_contains( $to_signin, 'paw_verify_u=' . $id2 ) && str_contains( $to_signin, 'paw_verify_t=' . $t2 ) && str_contains( $to_signin, 'paw_verified=signin' ) );
t( 'wrong account redirect says so', str_contains( Accounts::verify_redirect( 'wrong_user', $id2, $t2 ), 'paw_verified=wrong' ) );

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
t( 'prefill pet from the last reservation (an older one-pet booking)', 'Biscuit' === ( $p['pets'][0]['pet_name'] ?? '' ) && 'Beagle' === $p['pets'][0]['pet_breed'] && 'Twice' === $p['pets'][0]['feeding_schedule'] && 'Dr. Who' === $p['vet_name'] );
t( 'prefill never includes health answers', ! array_key_exists( 'vaccinations_current', $p['pets'][0] ) );
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

// Prefill from a two-pet booking.
$two = $last;
$two['details'] = json_encode( array( 'vet_name' => 'Dr. Who', 'pets' => array( array( 'pet_name' => 'Rex', 'pet_type' => 'dog', 'feeding_schedule' => 'Once', 'vaccinations_current' => 'yes', 'service' => 'nail_trim', 'groomer' => 'Ana' ), array( 'pet_name' => 'Tom', 'pet_type' => 'cat', 'feeding_schedule' => 'Twice' ) ) ) );
$wpdb->row_queue = array( $two );
$p2 = Accounts::prefill( $id );
t( 'prefill: every pet', 2 === count( $p2['pets'] ) && 'Rex' === $p2['pets'][0]['pet_name'] && 'Twice' === $p2['pets'][1]['feeding_schedule'] );
t( 'prefill: no health or grooming choices', ! isset( $p2['pets'][0]['vaccinations_current'] ) && ! isset( $p2['pets'][0]['service'] ) && ! isset( $p2['pets'][0]['groomer'] ) );

done();
