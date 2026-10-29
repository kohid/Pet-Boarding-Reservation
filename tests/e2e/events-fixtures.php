<?php
/**
 * The events every public-events browser test sees, in the in-memory post store. Used by
 * build-events.php (the pages) and events-router.php (the public routes), so both agree.
 */
define( 'PAW_URL', '/' );
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__ ) . '/post-stubs.php';
require dirname( __DIR__ ) . '/wp-db-stubs.php';
function get_current_user_id() { return 0; } // A visitor: nobody is signed in.
function is_user_logged_in() { return false; }
function wp_get_current_user() { return new class() { public function exists() { return false; } }; }
function home_url( $p = '' ) { return '/' . ltrim( $p, '/' ); }
function wp_enqueue_style( ...$a ) {}
function wp_enqueue_script( ...$a ) {}
function add_query_arg( $key, $value, $url = null ) { // WordPress's (key, value) form, against the current address.
	$url = $url ?? ( $_SERVER['REQUEST_URI'] ?? '/tests/e2e/out/events.html' );
	return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $key . '=' . $value;
}

use Pawfectly\Events;
use Pawfectly\Signups;

function paw_e2e_event( string $title, string $start, string $end, array $more = array() ): int {
	[ $sd, $st ] = explode( ' ', $start );
	[ $ed, $et ] = explode( ' ', $end );
	$clean = Events::sanitize( array_merge( array( 'title' => $title, 'start_date' => $sd, 'start_time' => $st, 'end_date' => $ed, 'end_time' => $et, 'venue' => 'Paw-radise Indoor Dog Park', 'address' => 'Estero, FL', 'price' => '15', 'status' => 'publish' ), $more ) );
	if ( is_wp_error( $clean ) ) {
		throw new RuntimeException( $title . ': ' . wp_json_encode( $clean->get_error_data() ) );
	}
	return Events::save( $clean );
}

function paw_e2e_seed(): int {
	wp_insert_term( 'Holiday party', 'paw_event_cat', array( 'slug' => 'holiday' ) );
	update_term_meta( get_term_by( 'slug', 'holiday', 'paw_event_cat' )->term_id, '_paw_color', '#e8a33a' );
	wp_insert_term( 'Training', 'paw_event_cat', array( 'slug' => 'training' ) );
	update_term_meta( get_term_by( 'slug', 'training', 'paw_event_cat' )->term_id, '_paw_color', '#3b7597' );

	$xmas = paw_e2e_event(
		'X-Mas Party',
		'2026-12-19 10:00',
		'2026-12-19 14:00',
		array(
			'category'    => 'holiday',
			'spots'       => '3',
			'coupon'      => 'PawPark',
			'tagline'     => 'A jingle-bark holiday party for dogs of every breed',
			'emoji'       => '🎄',
			'note'        => '🐾 FREE for Dog Park Members — call or text 239-420-7418 for your coupon code',
			'description' => '<p>Deck the paws! Join us Saturday, December 19th for a holiday celebration at the Paw-radise indoor dog park. Enjoy off-leash play, festive photo ops with your pup, and holiday treats for dogs and humans alike.</p>',
			'sections'    => array(
				'sessions'     => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs (under 30 lbs)' ), array( 'from' => '12:00', 'to' => '14:00', 'name' => 'Session 2', 'who' => 'Large dogs (30+ lbs)' ) ) ),
				'includes'     => array( 'rows' => array( array( 'emoji' => '🐾', 'text' => 'Off-leash holiday play time' ), array( 'emoji' => '📸', 'text' => 'Festive photo booth with holiday props' ), array( 'emoji' => '🍪', 'text' => 'Holiday treat samples for pups' ), array( 'emoji' => '🎁', 'text' => 'Goodie bags' ), array( 'emoji' => '🎟️', 'text' => 'Raffle prizes' ) ) ),
				'goodies'      => array( 'rows' => array( array( 'emoji' => '🎅', 'title' => 'Holiday Photo Booth', 'text' => 'Get your pup’s picture with festive holiday props — perfect for this year’s holiday card.' ), array( 'emoji' => '🐾', 'title' => 'Free for Park Members', 'text' => 'Paw-radise Dog Park members attend free with a coupon code — call or text us at 239-420-7418 and we’ll get it to you.' ) ) ),
				'good_to_know' => array( 'rows' => array( array( 'emoji' => '🐾', 'text' => 'Please join the session that matches your dog: small dogs (under 30 lbs) from 10:00 AM–12:00 PM, large dogs (30+ lbs) from 12:00–2:00 PM.' ), array( 'emoji' => '🐾', 'text' => 'Standard park entry requirements apply: current Rabies, Bordetella, and DHPP vaccinations (photos can be texted to 239-420-7418).' ), array( 'emoji' => '🐾', 'text' => 'Dogs must be socialized and good around other pets — the 1 Bite Policy applies.' ) ) ),
			),
		)
	);
	// The large-dog session is full: one sign-up with three dogs.
	$ev = Events::get( $xmas );
	Signups::create( $ev, Signups::sanitize( $ev, array( 'first_name' => 'Bo', 'last_name' => 'Lee', 'email' => 'bo@example.com', 'phone' => '239-555-0100', 'session' => '1', 'dogs' => array( array( 'name' => 'Max' ), array( 'name' => 'Duke' ), array( 'name' => 'Bear' ) ), 'agree' => '1' ) ), 0, '' );
	$GLOBALS['paw_e2e_gala'] = paw_e2e_event( 'Ticketed gala', '2027-02-05 18:00', '2027-02-05 21:00', array( 'emoji' => '🥂', 'ticket_url' => 'https://tickets.example/gala' ) );
	paw_e2e_event( 'New Year Pawty', '2026-12-31 18:00', '2027-01-02 12:00', array( 'emoji' => '🎉', 'tagline' => 'Ring in the new year with your pup' ) );
	for ( $i = 1; $i <= 5; $i++ ) {
		paw_e2e_event( "Puppy class $i", '2026-12-08 0' . ( $i + 3 ) . ':00', '2026-12-08 0' . ( $i + 3 ) . ':45', array( 'category' => 'training', 'emoji' => '🐶' ) );
	}
	paw_e2e_event( 'Agility basics', '2026-12-12 09:00', '2026-12-12 11:00', array( 'category' => 'training', 'emoji' => '🥏', 'free' => true ) );
	for ( $i = 1; $i <= 10; $i++ ) {
		paw_e2e_event( "Walk club $i", sprintf( '2027-01-%02d 08:00', 10 + $i ), sprintf( '2027-01-%02d 09:00', 10 + $i ), array( 'emoji' => '🦮', 'price' => '' ) );
	}
	$day = static fn( string $mod ): string => ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( $mod )->format( 'Y-m-d' );
	paw_e2e_event( 'Summer splash', $day( '-30 days' ) . ' 10:00', $day( '-30 days' ) . ' 12:00', array( 'emoji' => '💦' ) );
	paw_e2e_event( 'Secret draft', '2026-12-20 10:00', '2026-12-20 11:00', array( 'status' => 'draft' ) );
	return $xmas;
}
