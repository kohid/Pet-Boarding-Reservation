<?php
/**
 * The public sign-up route: nonce, rate limit, honeypot, double clicks, which events take
 * sign-ups, success, and the two emails.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

use Pawfectly\Events;
use Pawfectly\Settings;
use Pawfectly\SignupRest;
use Pawfectly\Signups;

$GLOBALS['paw_options'][ Settings::OPTION ] = array( 'staff_emails' => 'desk@example.com' );

function make_event( array $over = array() ): array {
	return Events::get( Events::save( Events::sanitize( array_replace( array( 'title' => 'X-Mas Party', 'emoji' => '🎄', 'start_date' => '2030-12-19', 'start_time' => '10:00', 'end_date' => '2030-12-19', 'end_time' => '14:00', 'venue' => 'Paw-radise', 'price' => '15', 'status' => 'publish', 'coupon' => 'PawPark' ), $over ) ) ) );
}
function req( int $id, array $json, string $nonce = 'good-nonce' ): WP_REST_Request {
	$r          = new WP_REST_Request();
	$r->url     = array( 'id' => (string) $id );
	$r->json    = $json;
	$r->headers = array( 'x_wp_nonce' => $nonce );
	return $r;
}
function body( array $over = array() ): array {
	return array_replace( array( 'first_name' => 'Zoe', 'last_name' => 'O', 'email' => 'zoe@example.com', 'phone' => '239-420-7418', 'dogs' => array( array( 'name' => 'Rex' ), array( 'name' => 'Ivy' ) ), 'agree' => '1', 'submission_id' => '' ), $over );
}
function status( $r ): int { return is_wp_error( $r ) ? (int) $r->get_error_data()['status'] : $r->get_status(); }

$ev = make_event();
t( 'a bad nonce is refused', 403 === status( SignupRest::submit( req( $ev['id'], body(), 'bad' ) ) ) );
t( 'no such event', 404 === status( SignupRest::submit( req( 99999, body() ) ) ) );
t( 'a draft takes no sign-ups', 404 === status( SignupRest::submit( req( make_event( array( 'status' => 'draft' ) )['id'], body() ) ) ) );
t( 'an event with a ticket link takes no sign-ups', 404 === status( SignupRest::submit( req( make_event( array( 'ticket_url' => 'https://tickets.example/x' ) )['id'], body() ) ) ) );
t( 'a past event takes no sign-ups', 404 === status( SignupRest::submit( req( make_event( array( 'start_date' => '2020-01-01', 'end_date' => '2020-01-01' ) )['id'], body() ) ) ) );
$bad = SignupRest::submit( req( $ev['id'], body( array( 'first_name' => '' ) ) ) );
t( 'checks come back keyed by field', 400 === status( $bad ) && isset( $bad->get_error_data()['fields']['first_name'] ) );

$GLOBALS['paw_mail'] = array();
$ok = SignupRest::submit( req( $ev['id'], body( array( 'submission_id' => '0b6b1a4e-58f3-4a5c-9d8e-1f2a3b4c5d6e' ) ) ) );
$d  = $ok->get_data();
t( 'success: 201 with a reference, a summary and calendar links', 201 === $ok->get_status() && 1 === preg_match( '/^EVT-/', $d['reference'] ) && '2 × $15 = $30, paid at the event' === $d['summary']['total_text'] && 'Rex, Ivy' === $d['summary']['dogs'] && str_contains( $d['calendar']['google'], 'calendar.google.com' ) );
t( 'success: never echoes the member code', ! str_contains( wp_json_encode( $d ), 'PawPark' ) );
t( 'the spots check and the insert run under a per-event lock', (bool) preg_grep( '/GET_LOCK\(.*paw_su_' . $ev['id'] . '/', $GLOBALS['wpdb']->queries ) && (bool) preg_grep( '/RELEASE_LOCK\(.*paw_su_' . $ev['id'] . '/', $GLOBALS['wpdb']->queries ) );
t( 'one sign-up stored', 1 === count( Signups::for_event( $ev['id'] ) ) );
$again = SignupRest::submit( req( $ev['id'], body( array( 'submission_id' => '0b6b1a4e-58f3-4a5c-9d8e-1f2a3b4c5d6e' ) ) ) );
t( 'a double click gets the first reference and stores nothing new', $d['reference'] === $again->get_data()['reference'] && 1 === count( Signups::for_event( $ev['id'] ) ) );

$owner = $GLOBALS['paw_mail'][0] ?? array();
$staff = $GLOBALS['paw_mail'][1] ?? array();
t( 'the owner gets a confirmation', 'zoe@example.com' === $owner['to'] && str_contains( $owner['subject'], $d['reference'] ) && str_contains( $owner['message'], 'Rex, Ivy' ) && str_contains( $owner['message'], 'paid at the event' ) && str_contains( $owner['message'], 'calendar.google.com' ) );
t( 'staff get an alert', array( 'desk@example.com' ) === (array) $staff['to'] && str_contains( $staff['message'], 'zoe@example.com' ) && str_contains( $staff['subject'], 'X-Mas Party' ) );
t( 'emails never show the member code', ! str_contains( $owner['message'] . $staff['message'], 'PawPark' ) );

$hp = SignupRest::submit( req( $ev['id'], body( array( 'email' => 'bot@example.com', 'website' => 'spam' ) ) ) );
t( 'the honeypot: told it worked, nothing kept', 201 === $hp->get_status() && 1 === count( Signups::for_event( $ev['id'] ) ) );

$user = paw_test_user( 'amy@example.com', 'amy pass1', array( 'paw_customer' ) );
wp_set_current_user( $user->ID );
$mine = SignupRest::submit( req( $ev['id'], body( array( 'email' => 'amy@example.com' ) ) ) );
t( 'a signed-in owner\'s sign-up is tied to their account', $user->ID === Signups::for_user( $user->ID )[0]['user_id'] && 201 === $mine->get_status() );
wp_set_current_user( 0 );

for ( $i = 0; $i < 6; $i++ ) {
	$last = SignupRest::submit( req( $ev['id'], body( array( 'email' => "x$i@example.com" ) ) ) );
}
t( 'too many tries', 429 === status( $last ) );

done();
