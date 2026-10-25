<?php
/**
 * Account endpoints: sign in (everyone), sign up, profile, password, verification link.
 * Run: php tests/account-rest-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';

use Pawfectly\AccountRest;
use Pawfectly\Accounts;
use Pawfectly\Activator;

function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
function get_post( $id ) { return null; }
function get_post_meta( $id, $k = '', $s = false ) { return ''; }
function get_permalink( $id = 0 ) { return ''; }

$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';
$_SERVER['REMOTE_ADDR']                = '203.0.113.9';

function req( array $json, array $url = array() ): WP_REST_Request {
	$r       = new WP_REST_Request();
	$r->json = $json;
	$r->url  = $url;
	return $r;
}
function code( $r ): string { return is_wp_error( $r ) ? $r->get_error_code() : 'ok'; }
function status( $r ): int { return is_wp_error( $r ) ? (int) $r->get_error_data()['status'] : $r->get_status(); }

// Sign up.
$res = AccountRest::signup( req( array( 'first_name' => 'Zoë', 'last_name' => "O'Brien", 'email' => 'zoe@example.com', 'phone' => '', 'password' => 'correct horse' ) ) );
t( 'sign-up succeeds and asks for a reload', 'ok' === code( $res ) && true === $res->get_data()['reload'] );
$zoe = get_user_by( 'email', 'zoe@example.com' );
t( 'sign-up signs the owner in', $zoe && $zoe->ID === get_current_user_id() );
t( 'sign-up sends one verification email', 1 === count( $GLOBALS['paw_mail'] ) && 'zoe@example.com' === $GLOBALS['paw_mail'][0]['to'] && str_contains( $GLOBALS['paw_mail'][0]['message'], 'paw_verify_t=' ) );
$dup = AccountRest::signup( req( array( 'first_name' => 'Z', 'last_name' => 'O', 'email' => 'zoe@example.com', 'password' => 'correct horse' ) ) );
t( 'existing email → 409 on the email field', 409 === status( $dup ) && isset( $dup->get_error_data()['fields']['email'] ) );
$bad = AccountRest::signup( req( array( 'first_name' => '', 'last_name' => 'O', 'email' => 'x@example.com', 'password' => 'short' ) ) );
t( 'invalid sign-up → 400 with field errors', 400 === status( $bad ) && isset( $bad->get_error_data()['fields']['password'], $bad->get_error_data()['fields']['first_name'] ) );
$bot = AccountRest::signup( req( array( 'first_name' => 'B', 'last_name' => 'O', 'email' => 'bot@example.com', 'password' => 'correct horse', 'website' => 'x' ) ) );
t( 'honeypot refused, no account', is_wp_error( $bot ) && ! email_exists( 'bot@example.com' ) );

// Sign in.
wp_set_current_user( 0 );
$ok = AccountRest::login( req( array( 'email' => 'zoe@example.com', 'password' => 'correct horse' ) ) );
t( 'sign-in succeeds and asks for a reload', 'ok' === code( $ok ) && true === $ok->get_data()['reload'] && $zoe->ID === get_current_user_id() );
t( 'owners are sent home: no My Bookings page, so the site home', 'https://example.test/' === $ok->get_data()['redirect'] );
t( 'sign-in fires wp_login for security plugins', in_array( 'wp_login', $GLOBALS['paw_actions'], true ) );
wp_set_current_user( 0 );
t( 'wrong password → 400 paw_login', 'paw_login' === code( AccountRest::login( req( array( 'email' => 'zoe@example.com', 'password' => 'nope nope' ) ) ) ) );
paw_test_user( 'desk@example.com', 'desk pass1', array( Accounts::STAFF_ROLE ), array( Activator::CAPABILITY => true ) );
wp_set_current_user( 0 );
$staff = AccountRest::login( req( array( 'email' => 'desk@example.com', 'password' => 'desk pass1', 'redirect_to' => 'https://example.test/book/' ) ) );
t( 'staff sign in here too and go where they were headed', 'ok' === code( $staff ) && 'https://example.test/book/' === $staff->get_data()['redirect'] );
wp_set_current_user( 0 );
$evil = AccountRest::login( req( array( 'email' => 'desk@example.com', 'password' => 'desk pass1', 'redirect_to' => 'https://evil.test/' ) ) );
t( 'another site in redirect_to is ignored', 'https://evil.test/' !== $evil->get_data()['redirect'] );
wp_set_current_user( 0 );
for ( $i = 0; $i < 8; $i++ ) {
	AccountRest::login( req( array( 'email' => 'zoe@example.com', 'password' => 'guess' . $i ) ) );
}
t( 'the 11th sign-in in 10 minutes → 429', 429 === status( AccountRest::login( req( array( 'email' => 'zoe@example.com', 'password' => 'correct horse' ) ) ) ) );

// Profile and password need a signed-in user.
wp_set_current_user( 0 );
t( 'profile without sign-in → 401', 401 === status( AccountRest::profile( req( array( 'first_name' => 'Z' ) ) ) ) );
wp_set_current_user( $zoe->ID );
$GLOBALS['paw_mail'] = array();
update_user_meta( $zoe->ID, Accounts::META_VERIFIED, '1' );
$p = AccountRest::profile( req( array( 'first_name' => 'Zoë', 'last_name' => 'Miralles', 'email' => 'zoe@example.com', 'phone' => '555 010 2030' ) ) );
t( 'profile saved', 'ok' === code( $p ) && 'Zoë Miralles' === $zoe->display_name && '555 010 2030' === get_user_meta( $zoe->ID, Accounts::META_PHONE, true ) );
t( 'same email stays verified, no email sent', Accounts::is_verified( $zoe->ID ) && array() === $GLOBALS['paw_mail'] );
$p = AccountRest::profile( req( array( 'first_name' => 'Zoë', 'last_name' => 'Miralles', 'email' => 'zoe.new@example.com', 'phone' => '' ) ) );
t( 'new email: saved, unverified, link sent', 'ok' === code( $p ) && 'zoe.new@example.com' === $zoe->user_email && ! Accounts::is_verified( $zoe->ID ) && 1 === count( $GLOBALS['paw_mail'] ) );
$boss = paw_test_user( 'boss@example.com', 'admin pass', array( 'administrator' ) );
wp_set_current_user( $boss->ID );
$b = AccountRest::profile( req( array( 'first_name' => 'B', 'last_name' => 'Oss', 'email' => 'attacker@example.com' ) ) );
t( 'staff/admin email cannot be changed here', is_wp_error( $b ) && isset( $b->get_error_data()['fields']['email'] ) && 'boss@example.com' === $boss->user_email );
$b2 = AccountRest::profile( req( array( 'first_name' => 'Big', 'last_name' => 'Boss', 'email' => 'boss@example.com' ) ) );
t( 'staff/admin can still change their name', 'ok' === code( $b2 ) && 'Big Boss' === $boss->display_name );
wp_set_current_user( $zoe->ID );
t( 'email used by someone else → 409', 409 === status( AccountRest::profile( req( array( 'first_name' => 'Z', 'last_name' => 'M', 'email' => 'desk@example.com' ) ) ) ) );

t( 'wrong current password', isset( AccountRest::password( req( array( 'current' => 'nope', 'password' => 'brand new pass', 'confirm' => 'brand new pass' ) ) )->get_error_data()['fields']['current'] ) );
t( 'mismatched confirmation', isset( AccountRest::password( req( array( 'current' => 'correct horse', 'password' => 'brand new pass', 'confirm' => 'brand new pas' ) ) )->get_error_data()['fields']['password'] ) );
$pw = AccountRest::password( req( array( 'current' => 'correct horse', 'password' => 'brand new pass', 'confirm' => 'brand new pass' ) ) );
t( 'password changed', 'ok' === code( $pw ) && wp_check_password( 'brand new pass', $zoe->user_pass ) );
t( 'password change reloads the page (the sign-in cookie and nonce are renewed)', true === ( $pw->get_data()['reload'] ?? null ) );

done();
