<?php
/**
 * Event sign-ups: checks, spots, the member code, duplicates, totals, references, owners.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';

use Pawfectly\Events;
use Pawfectly\Signups;

function party( array $over = array() ): array {
	$in = array_replace_recursive(
		array(
			'title' => 'X-Mas Party', 'start_date' => '2030-12-19', 'start_time' => '10:00', 'end_date' => '2030-12-19', 'end_time' => '14:00',
			'venue' => 'Paw-radise', 'price' => '15', 'status' => 'publish', 'spots' => '3', 'coupon' => 'PawPark',
			'sections' => array( 'sessions' => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs' ), array( 'from' => '12:00', 'to' => '14:00', 'name' => 'Session 2', 'who' => 'Large dogs' ) ) ) ),
		),
		$over
	);
	return Events::get( Events::save( Events::sanitize( $in ) ) );
}
function form( array $over = array() ): array {
	return array_replace( array( 'first_name' => 'Zoe', 'last_name' => 'O', 'email' => 'zoe@example.com', 'phone' => '239-420-7418', 'session' => '0', 'dogs' => array( array( 'name' => 'Rex', 'breed' => 'Beagle', 'weight' => '22 lbs' ) ), 'coupon' => '', 'agree' => '1', 'notes' => '' ), $over );
}
function errs( $r ): array { return is_wp_error( $r ) ? $r->get_error_data()['fields'] : array(); }

$ev = party();
t( 'events keep spots and the member code', 3 === $ev['spots'] && 'PawPark' === $ev['coupon'] );
t( 'events: spots must be 1-500 and the code 3-30 characters', isset( errs( Events::sanitize( array( 'spots' => '0', 'coupon' => 'ab' ) ) )['spots'], errs( Events::sanitize( array( 'spots' => '0', 'coupon' => 'ab' ) ) )['coupon'] ) && 0 === party( array( 'spots' => '' ) )['spots'] );
t( 'events: the public view never carries the member code', ! isset( Events::public_view( $ev )['coupon'] ) );

// Checks.
t( 'required fields', array( 'first_name', 'last_name', 'email', 'phone', 'session', 'dogs', 'agree' ) === array_keys( errs( Signups::sanitize( $ev, array() ) ) ) );
t( 'a valid email', isset( errs( Signups::sanitize( $ev, form( array( 'email' => 'nope' ) ) ) )['email'] ) );
t( 'the session must be one of the event\'s', isset( errs( Signups::sanitize( $ev, form( array( 'session' => '7' ) ) ) )['session'] ) );
$six = form( array( 'dogs' => array_fill( 0, 6, array( 'name' => 'Rex' ) ) ) );
t( 'at most 5 dogs', isset( errs( Signups::sanitize( $ev, $six ) )['dogs'] ) );
$blank = form( array( 'dogs' => array( array( 'name' => 'Rex' ), array( 'name' => '', 'breed' => '', 'weight' => '' ) ) ) );
t( 'blank dogs are dropped', 1 === Signups::sanitize( $ev, $blank )['dog_count'] );
t( 'a dog with a breed needs a name', isset( errs( Signups::sanitize( $ev, form( array( 'dogs' => array( array( 'name' => '', 'breed' => 'Pug' ) ) ) ) ) )['dogs.0.name'] ) );
t( 'the agreement is required', isset( errs( Signups::sanitize( $ev, form( array( 'agree' => '' ) ) ) )['agree'] ) );

// Member code and totals.
$two = form( array( 'dogs' => array( array( 'name' => 'Rex' ), array( 'name' => 'Ivy' ) ) ) );
$c   = Signups::sanitize( $ev, $two );
t( 'total: dogs × price', '30.00' === $c['total'] && '15.00' === $c['price_each'] && false === $c['coupon'] );
t( 'member code, any case: free', '0.00' === Signups::sanitize( $ev, form( array( 'coupon' => ' pawpark ' ) ) )['total'] && true === Signups::sanitize( $ev, form( array( 'coupon' => 'PAWPARK' ) ) )['coupon'] );
t( 'a wrong code is refused on its field', "That code isn't valid for this event." === ( errs( Signups::sanitize( $ev, form( array( 'coupon' => 'nope' ) ) ) )['coupon'] ?? '' ) );
$nosessions = Events::get( Events::save( Events::sanitize( array( 'title' => 'Walk', 'start_date' => '2030-12-20', 'start_time' => '08:00', 'end_date' => '2030-12-20', 'end_time' => '09:00', 'venue' => 'Park', 'price' => '15', 'status' => 'publish' ) ) ) );
t( 'no sessions: nothing to pick, no limit', -1 === Signups::sanitize( $nosessions, form( array( 'session' => '' ) ) )['session'] && null === Signups::spots_left( $nosessions, -1 ) );

// Spots, duplicates, create.
$a = Signups::create( $ev, Signups::sanitize( $ev, $two ), 0, '203.0.113.9' );
t( 'create: a reference and the snapshot', 1 === preg_match( '/^EVT-[A-HJ-NP-Z2-9]{6}$/', $a['reference'] ) && 'confirmed' === $a['status'] && '10:00 AM – 12:00 PM · Session 1: Small dogs' === $a['session_label'] && 2 === $a['dog_count'] && 'Ivy' === $a['dogs'][1]['name'] );
t( 'get round trip', Signups::get( $a['id'] )['reference'] === $a['reference'] && null === Signups::get( $ev['id'] ) );
t( 'spots left', 1 === Signups::spots_left( $ev, 0 ) && 3 === Signups::spots_left( $ev, 1 ) && 2 === Signups::taken( $ev['id'], 0 ) );
t( 'the same email again is refused', isset( errs( Signups::sanitize( $ev, form( array( 'session' => '1' ) ) ) )['email'] ) );
$b = form( array( 'email' => 'amy@example.com', 'dogs' => array( array( 'name' => 'A' ), array( 'name' => 'B' ) ) ) );
t( 'a session without enough spots is refused', 'Only 1 spot left in this session.' === ( errs( Signups::sanitize( $ev, $b ) )['session'] ?? '' ) );
Signups::create( $ev, Signups::sanitize( $ev, form( array( 'email' => 'bo@example.com' ) ) ), 0, '' );
t( 'a full session is refused', 'Sorry, that session just filled up. Please pick another.' === ( errs( Signups::sanitize( $ev, form( array( 'email' => 'cy@example.com' ) ) ) )['session'] ?? '' ) );
Signups::set_status( $a['id'], 'cancelled' );
t( 'a cancel frees its spots and its email', 2 === Signups::spots_left( $ev, 0 ) && is_array( Signups::sanitize( $ev, form() ) ) );
t( 'only known statuses', false === Signups::set_status( $a['id'], 'lost' ) && 'cancelled' === Signups::get( $a['id'] )['status'] );

// The event's price changes later: sign-ups keep theirs.
Events::save( Events::sanitize( array( 'title' => 'X-Mas Party', 'start_date' => '2030-12-19', 'start_time' => '10:00', 'end_date' => '2030-12-19', 'end_time' => '14:00', 'venue' => 'Paw-radise', 'price' => '20', 'status' => 'publish' ) ), $ev['id'] );
t( 'totals are snapshots', '30.00' === Signups::get( $a['id'] )['total'] );

// Owners and lists.
$mine = Signups::create( $ev, Signups::sanitize( $ev, form( array( 'email' => 'me@example.com', 'session' => '1' ) ) ), 42, '' );
t( 'for_user', array( $mine['id'] ) === array_column( Signups::for_user( 42 ), 'id' ) );
$tz = new DateTimeZone( 'UTC' );
t( 'cancel until the event starts', Signups::can_cancel( $mine, $ev, new DateTimeImmutable( '2030-12-19 09:59', $tz ) ) && ! Signups::can_cancel( $mine, $ev, new DateTimeImmutable( '2030-12-19 10:00', $tz ) ) && ! Signups::can_cancel( Signups::get( $a['id'] ), $ev, new DateTimeImmutable( '2030-01-01', $tz ) ) );
t( 'for_event, active only', 3 === count( Signups::for_event( $ev['id'] ) ) && 2 === count( Signups::for_event( $ev['id'], true ) ) );
$csv = Signups::csv_rows( $ev['id'] );
t( 'CSV: a header, then one row per dog', 'Reference' === $csv[0][0] && 5 === count( $csv ) && in_array( 'Ivy', array_merge( ...$csv ), true ) );

done();
