<?php
/**
 * Starter events: Settings → Starter events on creates 30 real-looking events (Sept 2026 – Jan 2027),
 * off trashes them but keeps any with sign-ups, your own events and categories are left alone,
 * and nothing says "demo".
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

use Pawfectly\Accounts;
use Pawfectly\Activator;
use Pawfectly\Events;
use Pawfectly\Signups;
use Pawfectly\StarterEvents;
use Pawfectly\StaffRest;

$req = static function ( bool $on ): WP_REST_Request {
	$r       = new WP_REST_Request();
	$r->json = array( 'on' => $on );
	return $r;
};

// What was here before: your own event and category.
Events::save_categories( array( array( 'name' => 'Holiday party', 'color' => '#0f42c0' ), array( 'name' => 'Members only', 'color' => '#8a5cd8' ) ) );
$mine = Events::save( Events::sanitize( array( 'title' => 'X-Mas Party', 'category' => 'holiday-party', 'start_date' => '2026-12-19', 'start_time' => '10:00', 'end_date' => '2026-12-19', 'end_time' => '14:00', 'venue' => 'Paw-radise', 'status' => 'publish' ) ) );

t( 'off to start with', array( 'on' => false, 'count' => 0 ) === StarterEvents::state() );
t( 'the content: 30 events', 30 === count( StarterEvents::data() ) );

$res = StarterEvents::on();
$ids = get_option( StarterEvents::OPTION )['ids'];
t( 'on: 30 published events', 30 === $res['added'] && 30 === count( $ids ) && array( 'on' => true, 'count' => 30 ) === StarterEvents::state() );
$evs = array_map( array( Events::class, 'get' ), $ids );
t( 'every one is a published event', 30 === count( array_filter( $evs, static fn( $e ) => $e && 'publish' === $e['status'] ) ) );
$starts = array_column( $evs, 'start' );
sort( $starts );
t( 'September 2026 to January 2027', str_starts_with( $starts[0], '2026-09' ) && str_starts_with( end( $starts ), '2027-01' ) );
t( 'every month has events', array( '2026-09', '2026-10', '2026-11', '2026-12', '2027-01' ) === array_values( array_unique( array_map( static fn( $s ) => substr( $s, 0, 7 ), $starts ) ) ) );
t( 'real content: taglines, descriptions, sections', 30 === count( array_filter( $evs, static fn( $e ) => '' !== $e['tagline'] && '' !== $e['description'] && $e['sections']['includes']['rows'] && $e['sections']['good_to_know']['rows'] ) ) );
t( 'every one has a category', 30 === count( array_filter( $evs, static fn( $e ) => '' !== $e['category'] ) ) );
t( 'nothing says demo, dummy or placeholder', ! preg_match( '/\b(demo|dummy|lorem|placeholder)\b/i', wp_json_encode( $evs ) ) );
t( 'some free, some with spots and a member code', array_filter( $evs, static fn( $e ) => $e['free'] ) && array_filter( $evs, static fn( $e ) => $e['spots'] > 0 ) && array_filter( $evs, static fn( $e ) => '' !== $e['coupon'] ) );

$pages = array_filter( $evs, static fn( $e ) => str_contains( Pawfectly\EventPage::body( $e, new DateTimeImmutable( '2026-08-01', new DateTimeZone( 'UTC' ) ) ), esc_html( $e['title'] ) ) );
t( 'every one renders as an event page with its sign-up form', 30 === count( $pages ) && str_contains( Pawfectly\EventPage::body( $evs[0], new DateTimeImmutable( '2026-08-01', new DateTimeZone( 'UTC' ) ) ), 'data-paw-signup' ) );

$cats = array_column( Events::categories(), 'color', 'name' );
t( 'categories are added beside yours, yours kept as they were', array( 'Holiday party', 'Members only', 'Training', 'Social', 'Adoption', 'Grooming', 'Fundraiser' ) === array_keys( $cats ) && '#0f42c0' === $cats['Holiday party'] && '#8a5cd8' === $cats['Members only'] );
t( 'the holiday events use your existing Holiday party category', 'holiday-party' === Events::get( $ids[9] )['category'] );
t( 'on again while on: nothing more', 0 === StarterEvents::on()['added'] && 30 === count( get_option( StarterEvents::OPTION )['ids'] ) );

// Someone signs up for one of them.
$party = Events::get( $ids[11] ); // Howl-o-ween, 2026-10-31.
$su    = Signups::create( $party, Signups::sanitize( $party, array( 'first_name' => 'Zoe', 'last_name' => 'O', 'email' => 'zoe@example.com', 'phone' => '239-555-0100', 'session' => '0', 'dogs' => array( array( 'name' => 'Rex' ) ), 'agree' => '1' ) ), 0, '' );
t( 'a sign-up was stored', is_array( $su ) );

$res = StarterEvents::off();
t( 'off: 29 removed, 1 kept for its sign-up', 29 === $res['removed'] && 1 === $res['kept'] && array( 'on' => false, 'count' => 0 ) === StarterEvents::state() );
t( 'removed ones go to the trash', 'trash' === get_post( $ids[0] )->post_status && 'publish' === get_post( $ids[11] )->post_status );
t( 'your own event is untouched', 'publish' === get_post( $mine )->post_status );
t( 'categories stay', 7 === count( Events::categories() ) );
$res = StarterEvents::on();
t( 'on again: a fresh 30', 30 === $res['added'] && 30 === count( array_diff( get_option( StarterEvents::OPTION )['ids'], $ids ) ) );

// The route: settings access only.
$desk = paw_test_user( 'desk@example.com', 'desk pass1', array( Accounts::STAFF_ROLE ), array( Activator::CAPABILITY => true ) );
wp_set_current_user( $desk->ID );
t( 'staff without settings access are refused', false === StaffRest::can_settings() );
$GLOBALS['paw_options']['admin_email'] = 'boss@example.com';
$boss = paw_test_user( 'boss@example.com', 'boss pass1', array( 'administrator' ), array( 'manage_options' => true, Activator::CAPABILITY => true ) );
wp_set_current_user( $boss->ID );
$off = StarterEvents::route( $req( false ) )->get_data();
t( 'the route turns it off and says what happened', false === $off['on'] && 30 === $off['removed'] && '30 events removed.' === $off['message'] );
$on = StarterEvents::route( $req( true ) )->get_data();
t( 'the route turns it on', true === $on['on'] && 30 === $on['count'] && '30 events added.' === $on['message'] );
t( 'kept events are explained', str_contains( StarterEvents::message( array( 'on' => false, 'removed' => 28, 'kept' => 2 ) ), '2 kept because people signed up' ) );

done();
