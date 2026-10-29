<?php
/**
 * My Bookings → Event sign-ups: the owner's list, and cancelling their own before the event starts.
 * Run: php tests/my-signups-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

use Pawfectly\AccountRest;
use Pawfectly\Accounts;
use Pawfectly\Events;
use Pawfectly\MyBookings;
use Pawfectly\Signups;

function party( string $title, string $date ): array {
	return Events::get( Events::save( Events::sanitize( array( 'title' => $title, 'emoji' => '🎄', 'start_date' => $date, 'start_time' => '10:00', 'end_date' => $date, 'end_time' => '14:00', 'venue' => 'Paw-radise', 'price' => '15', 'status' => 'publish', 'sections' => array( 'sessions' => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs' ) ) ) ) ) ) ) );
}
function sign( array $ev, string $email, int $user ): array {
	return Signups::create( $ev, Signups::sanitize( $ev, array( 'first_name' => 'Zoe', 'last_name' => 'O', 'email' => $email, 'phone' => '239-420-7418', 'session' => '0', 'dogs' => array( array( 'name' => 'Rex' ), array( 'name' => 'Ivy' ) ), 'agree' => '1' ) ), $user, '' );
}
function cancel( int $id ) {
	$r      = new WP_REST_Request();
	$r->url = array( 'id' => (string) $id );
	return AccountRest::cancel_signup( $r );
}
function status_of( $r ): int { return is_wp_error( $r ) ? (int) $r->get_error_data()['status'] : $r->get_status(); }

$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'staff_emails' => 'desk@example.com' );
$zoe   = paw_test_user( 'zoe@example.com', 'some pass1', array( Accounts::ROLE ) );
$other = paw_test_user( 'other@example.com', 'some pass1', array( Accounts::ROLE ) );
$xmas  = party( 'X-Mas Party', '2030-12-19' );
$old   = party( 'Summer splash', '2020-07-01' );
$mine  = sign( $xmas, 'zoe@example.com', $zoe->ID );
$done  = sign( $old, 'zoe@example.com', $zoe->ID );
$their = sign( $xmas, 'other@example.com', $other->ID );

$rows = MyBookings::signup_rows( $zoe->ID );
t( 'the list: only my sign-ups, the soonest event first', array( $mine['reference'], $done['reference'] ) === array_column( $rows, 'reference' ) );
t( 'a row: event, link, when, session, dogs, total, status', 'X-Mas Party' === $rows[0]['event'] && 'https://example.test/event/x-mas-party/' === $rows[0]['url'] && str_contains( $rows[0]['when'], 'December 19th, 2030' ) && str_contains( $rows[0]['session'], 'Session 1: Small dogs' ) && 'Rex, Ivy' === $rows[0]['dogs'] && '2 × $15 = $30, paid at the event' === $rows[0]['total_text'] && 'Confirmed' === $rows[0]['label'] );
t( 'cancel is offered before the start only', true === $rows[0]['can_cancel'] && false === $rows[1]['can_cancel'] );
t( 'nobody signed in: no rows', array() === MyBookings::signup_rows( 0 ) );

$paw_signups = $rows;
ob_start();
include PAW_DIR . 'templates/partials/my-signups.php';
$html = ob_get_clean();
t( 'the card: title, the event link, reference and a Cancel button for the upcoming one', str_contains( $html, 'Event sign-ups' ) && str_contains( $html, 'href="https://example.test/event/x-mas-party/"' ) && str_contains( $html, $mine['reference'] ) && str_contains( $html, 'data-paw-cancel-signup="' . $mine['id'] . '"' ) && ! str_contains( $html, 'data-paw-cancel-signup="' . $done['id'] . '"' ) );
$paw_signups = array();
ob_start();
include PAW_DIR . 'templates/partials/my-signups.php';
t( 'no sign-ups: no card', '' === trim( ob_get_clean() ) );

t( 'cancel: signed out is refused', 401 === status_of( cancel( $mine['id'] ) ) );
wp_set_current_user( $zoe->ID );
t( "cancel: someone else's sign-up is not found", 404 === status_of( cancel( $their['id'] ) ) && 'confirmed' === Signups::get( $their['id'] )['status'] );
t( 'cancel: not a sign-up is not found', 404 === status_of( cancel( $xmas['id'] ) ) );
t( 'cancel: after the event started is refused', 409 === status_of( cancel( $done['id'] ) ) && 'confirmed' === Signups::get( $done['id'] )['status'] );
$ok = cancel( $mine['id'] );
t( 'cancel: my own, before the start', 200 === $ok->get_status() && 'cancelled' === $ok->get_data()['status'] && 'Cancelled' === $ok->get_data()['label'] && 'cancelled' === Signups::get( $mine['id'] )['status'] );
t( 'cancel: frees the spots', 2 === Signups::taken( $xmas['id'], 0 ) );
t( 'cancel: only once', 409 === status_of( cancel( $mine['id'] ) ) );
t( 'staff hear about it', str_contains( end( $GLOBALS['paw_mail'] )['subject'] ?? '', 'cancelled' ) );

done();
