<?php
/**
 * My Bookings and My Profile: what an owner sees, and cancelling online.
 * Run: php tests/my-bookings-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';

use Pawfectly\AccountRest;
use Pawfectly\Accounts;
use Pawfectly\MyBookings;
use Pawfectly\Profile;
use Pawfectly\Reservations;

function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
function get_post( $id ) { return null; }
function get_post_meta( $id, $k = '', $s = false ) { return ''; }
function get_permalink( $id = 0 ) { return 'https://example.test/account/'; }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_add_inline_script() {}

$GLOBALS['paw_options']['admin_email'] = 'desk@example.com';

$today = '2026-10-25';
t( 'pending, future check-in: cancellable', MyBookings::can_cancel( array( 'status' => 'pending', 'check_in' => '2026-10-26' ), $today ) );
t( 'confirmed, future check-in: cancellable', MyBookings::can_cancel( array( 'status' => 'confirmed', 'check_in' => '2026-11-02' ), $today ) );
t( 'check-in today: not cancellable online', ! MyBookings::can_cancel( array( 'status' => 'confirmed', 'check_in' => '2026-10-25' ), $today ) );
t( 'checked in: not cancellable', ! MyBookings::can_cancel( array( 'status' => 'checked_in', 'check_in' => '2026-11-02' ), $today ) );
t( 'declined: not cancellable', ! MyBookings::can_cancel( array( 'status' => 'declined', 'check_in' => '2026-11-02' ), $today ) );

/** Inserts a reservation row for the fake database. */
function add_reservation( string $ref, string $email, ?int $user_id, string $status = 'pending', string $check_in = '2030-01-10' ): int {
	$row = Reservations::build_row(
		array(
			'columns' => array( 'owner_first_name' => 'Zoë', 'owner_last_name' => "O'Brien", 'owner_email' => $email, 'owner_phone' => '555', 'pet_name' => 'Pet ' . $ref, 'pet_type' => 'dog', 'check_in' => $check_in, 'check_out' => '2030-01-12' ),
			'details' => array(),
		),
		$ref, array(), '', '203.0.113.9', '2026-10-25 10:00:00'
	);
	$row['status']  = $status;
	$row['user_id'] = $user_id;
	return Reservations::insert( $row );
}

$zoe   = paw_test_user( 'zoe@example.com', 'some pass1', array( Accounts::ROLE ) );
$other = paw_test_user( 'other@example.com', 'some pass1', array( Accounts::ROLE ) );
$mine  = add_reservation( 'PAW-AAAAAA', 'zoe@example.com', $zoe->ID );
$guest = add_reservation( 'PAW-BBBBBB', 'zoe@example.com', null );
$theirs = add_reservation( 'PAW-CCCCCC', 'other@example.com', $other->ID );
$past  = add_reservation( 'PAW-DDDDDD', 'zoe@example.com', $zoe->ID, 'confirmed', '2020-01-01' );

function cancel( int $id ) {
	$r      = new WP_REST_Request();
	$r->url = array( 'id' => (string) $id );
	return AccountRest::cancel( $r );
}
function status_of( $r ): int { return is_wp_error( $r ) ? (int) $r->get_error_data()['status'] : $r->get_status(); }

t( 'cancel needs sign-in', 401 === status_of( cancel( $mine ) ) );
wp_set_current_user( $zoe->ID );
t( "someone else's reservation → 404", 404 === status_of( cancel( $theirs ) ) );
t( 'same email but unverified → 404', 404 === status_of( cancel( $guest ) ) );
t( 'past check-in → 409', 409 === status_of( cancel( $past ) ) );
$GLOBALS['paw_mail'] = array();
$res = cancel( $mine );
$row = Reservations::find( $mine );
t( 'own reservation cancelled', 200 === status_of( $res ) && 'cancelled' === $row['status'] );
t( 'history records the owner', $zoe->ID === end( $row['status_log'] )['user_id'] );
t( 'staff emailed about it', 1 === count( $GLOBALS['paw_mail'] ) && 'desk@example.com' === $GLOBALS['paw_mail'][0]['to'][0] && str_contains( $GLOBALS['paw_mail'][0]['subject'], 'cancelled by the owner' ) );
update_user_meta( $zoe->ID, Accounts::META_VERIFIED, '1' );
t( 'once verified, the email-matched reservation can be cancelled', 200 === status_of( cancel( $guest ) ) );

// My Bookings page.
wp_set_current_user( 0 );
$html = MyBookings::render();
t( 'signed out: asked to sign in', str_contains( $html, 'Sign in' ) && ! str_contains( $html, 'PAW-AAAAAA' ) );
wp_set_current_user( $zoe->ID );
$html = MyBookings::render();
t( 'lists own and email-matched reservations', str_contains( $html, 'PAW-AAAAAA' ) && str_contains( $html, 'PAW-BBBBBB' ) && ! str_contains( $html, 'PAW-CCCCCC' ) );
t( 'cancel button only where allowed', 0 === substr_count( $html, 'data-paw-cancel' ) );
$fresh = add_reservation( 'PAW-EEEEEE', 'zoe@example.com', $zoe->ID, 'confirmed' );
$html  = MyBookings::render();
t( 'confirmed future reservation offers cancel', 1 === substr_count( $html, 'data-paw-cancel="' . $fresh . '"' ) );
t( 'names escaped', str_contains( $html, 'Pet PAW-EEEEEE' ) );
$groom_row = Reservations::build_row(
	array(
		'columns' => array( 'owner_first_name' => 'Zoë', 'owner_last_name' => 'O', 'owner_email' => 'zoe@example.com', 'owner_phone' => '555', 'pet_name' => 'Rex', 'pet_type' => 'dog', 'booking_type' => 'grooming', 'check_in' => '2030-02-03', 'check_out' => '2030-02-03', 'appointment_date' => '2030-02-03', 'service' => 'nail_trim' ),
		'details' => array( 'appointment_time' => 'morning' ),
	),
	'PAW-GGGGGG', array(), '', '203.0.113.9', '2026-10-25 10:00:00'
);
$groom_row['user_id'] = $zoe->ID;
Reservations::insert( $groom_row );
$html = MyBookings::render();
t( 'grooming booking shows the service and its day', str_contains( $html, 'Nail Trimming' ) && str_contains( $html, '02/03/2030 · Morning (8–12)' ) );
wp_set_current_user( $other->ID );
t( 'an owner with nothing sees an invitation to book', str_contains( MyBookings::render(), 'No reservations yet' ) || str_contains( MyBookings::render(), 'PAW-CCCCCC' ) );

// My Profile page.
wp_set_current_user( 0 );
$html = Profile::render();
t( 'signed out: sign in and create account tabs', str_contains( $html, 'data-paw-tab="signin"' ) && str_contains( $html, 'data-paw-tab="signup"' ) && str_contains( $html, 'autocomplete="new-password"' ) );
t( 'no separate staff sign-in line', ! str_contains( $html, 'Pawfectly staff' ) );
$zoe->first_name   = 'Zoë';
$zoe->last_name    = '<O>';
$zoe->display_name = 'Zoë <O>';
update_user_meta( $zoe->ID, Accounts::META_PHONE, '555 010 2030' );
delete_user_meta( $zoe->ID, Accounts::META_VERIFIED );
wp_set_current_user( $zoe->ID );
$html = Profile::render();
t( 'signed in: details form filled in and escaped', str_contains( $html, 'value="&lt;O&gt;"' ) && str_contains( $html, 'value="555 010 2030"' ) && str_contains( $html, 'value="zoe@example.com"' ) );
t( 'signed in: password form asks for the current one', str_contains( $html, 'name="current"' ) && str_contains( $html, 'name="confirm"' ) );
t( 'unverified: reminder with a resend button', str_contains( $html, 'data-paw-resend' ) );
$_GET['paw_verified'] = '1';
t( 'verified link notice', str_contains( Profile::render(), 'Thanks — your email is confirmed.' ) );
$_GET['paw_verified'] = 'signin';
wp_set_current_user( 0 );
t( 'signed out on My Bookings: told to sign in to confirm', str_contains( MyBookings::render(), 'Sign in to confirm your email address.' ) );
$_GET['paw_verified'] = 'wrong';
t( 'wrong account notice', str_contains( Profile::render(), 'belongs to a different account' ) );
wp_set_current_user( $zoe->ID );
$_GET['paw_verified'] = '0';
t( 'expired link notice', str_contains( MyBookings::render(), 'expired or was already used' ) );

done();
