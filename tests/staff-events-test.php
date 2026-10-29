<?php
/**
 * Staff event routes: permissions, list tabs, create, update, errors keyed by row,
 * duplicate, delete, sign-ups (counts, status, CSV) and event categories (manage-settings only).
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
use Pawfectly\StaffEvents;
use Pawfectly\StaffRest;

function req( array $json = array(), array $url = array() ): WP_REST_Request {
	$r       = new WP_REST_Request();
	$r->json = $json;
	$r->url  = $url;
	return $r;
}
function body( array $over = array() ): array {
	return array_replace_recursive(
		array( 'title' => 'X-Mas Party', 'emoji' => '🎄', 'start_date' => '2030-12-19', 'start_time' => '10:00', 'end_date' => '2030-12-19', 'end_time' => '14:00', 'venue' => 'Paw-radise', 'price' => '15', 'status' => 'publish', 'sections' => array( 'goodies' => array( 'rows' => array( array( 'emoji' => '🎅', 'title' => 'Photo booth', 'text' => '' ) ) ) ) ),
		$over
	);
}

$GLOBALS['paw_options']['admin_email'] = 'boss@example.com';
$owner = paw_test_user( 'zoe@example.com', 'some pass1', array( Accounts::ROLE ) );
wp_set_current_user( $owner->ID );
t( 'owners are refused', false === StaffRest::can() );
wp_set_current_user( 0 );
t( 'visitors are refused', false === StaffRest::can() );

$desk = paw_test_user( 'desk@example.com', 'desk pass1', array( Accounts::STAFF_ROLE ), array( Activator::CAPABILITY => true ) );
wp_set_current_user( $desk->ID );

$made = StaffEvents::save( req( body() ) );
$id   = $made->get_data()['id'];
t( 'create', 200 === $made->get_status() && true === $made->get_data()['ok'] && 'X-Mas Party' === Events::get( $id )['title'] && 'Photo booth' === $made->get_data()['event']['sections']['goodies']['rows'][0]['title'] );

$bad = StaffEvents::save( req( body( array( 'sections' => array( 'goodies' => array( 'rows' => array( array( 'emoji' => '🎅', 'title' => '', 'text' => 'Props' ) ) ) ) ) ) ) );
t( 'errors keyed by row field', is_wp_error( $bad ) && 400 === $bad->get_error_data()['status'] && isset( $bad->get_error_data()['fields']['sections.goodies.0.title'] ) );

$up = StaffEvents::save( req( body( array( 'title' => 'X-Mas Party 2030' ) ), array( 'id' => (string) $id ) ) );
t( 'update', 200 === $up->get_status() && 'X-Mas Party 2030' === Events::get( $id )['title'] && $id === $up->get_data()['id'] );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Home', 'post_status' => 'publish' ) );
$nope = StaffEvents::save( req( body(), array( 'id' => (string) $page ) ) );
t( 'updating something that is not an event is a 404', is_wp_error( $nope ) && 404 === $nope->get_error_data()['status'] );

$one = StaffEvents::one( req( array(), array( 'id' => (string) $id ) ) )->get_data();
t( 'one event for the editor, dates split for the form', '2030-12-19' === $one['start_date'] && '10:00' === $one['start_time'] && '14:00' === $one['end_time'] && str_contains( $one['preview_url'], 'paw_preview=1' ) && 'https://example.test/event/x-mas-party/' === $one['url'] );
t( 'one: not an event', is_wp_error( StaffEvents::one( req( array(), array( 'id' => (string) $page ) ) ) ) );

StaffEvents::save( req( body( array( 'title' => 'Old party', 'start_date' => '2020-01-01', 'end_date' => '2020-01-01' ) ) ) );
StaffEvents::save( req( body( array( 'title' => 'Draft walk', 'status' => 'draft' ) ) ) );
$titles = static fn( string $tab, string $q = '' ): array => array_column( StaffEvents::list( req( array(), array( 'tab' => $tab, 'q' => $q ) ) )->get_data()['rows'], 'title' );
t( 'list: upcoming', array( 'X-Mas Party 2030' ) === $titles( 'upcoming' ) );
t( 'list: drafts', array( 'Draft walk' ) === $titles( 'drafts' ) );
t( 'list: past', array( 'Old party' ) === $titles( 'past' ) );
t( 'list: all, and search', 3 === count( $titles( 'all' ) ) && array( 'Old party' ) === $titles( 'all', 'old' ) );
$row = StaffEvents::list( req( array(), array( 'tab' => 'upcoming' ) ) )->get_data()['rows'][0];
t( 'list row fields', '🎄' === $row['emoji'] && '$15 per dog' === $row['price_text'] && 'publish' === $row['status'] && str_contains( $row['when'], '10:00 AM – 2:00 PM' ) && $id === $row['id'] && 1 === StaffEvents::list( req( array(), array( 'tab' => 'upcoming' ) ) )->get_data()['pages'] );

$copy = StaffEvents::duplicate( req( array(), array( 'id' => (string) $id ) ) )->get_data()['id'];
t( 'duplicate', 'X-Mas Party 2030 (copy)' === Events::get( $copy )['title'] && 'draft' === Events::get( $copy )['status'] );
StaffEvents::delete( req( array(), array( 'id' => (string) $copy ) ) );
t( 'delete moves it to the trash', 'trash' === get_post( $copy )->post_status );

// Sign-ups.
$party = StaffEvents::save( req( body( array( 'title' => 'Sign-up party', 'spots' => '4', 'coupon' => 'PawPark', 'sections' => array( 'sessions' => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs' ), array( 'from' => '12:00', 'to' => '14:00', 'name' => 'Session 2', 'who' => '' ) ) ) ) ) ) ) )->get_data()['id'];
$pev   = Events::get( $party );
$su_in = static fn( string $email, string $session, array $dogs ): array => array( 'first_name' => 'Zoe', 'last_name' => 'O', 'email' => $email, 'phone' => '239-420-7418', 'session' => $session, 'dogs' => $dogs, 'agree' => '1' );
$s1    = Signups::create( $pev, Signups::sanitize( $pev, $su_in( 'zoe@example.com', '0', array( array( 'name' => 'Rex', 'breed' => 'Beagle' ), array( 'name' => 'Ivy' ) ) ) ), 0, '' );
$s2    = Signups::create( $pev, Signups::sanitize( $pev, $su_in( 'amy@example.com', '1', array( array( 'name' => '=cmd()' ) ) ) ), 0, '' );
$list  = StaffEvents::signups( req( array(), array( 'id' => (string) $party ) ) )->get_data();
t( 'sign-ups: a meter per session', array( array( 'label' => '10:00 AM – 12:00 PM · Session 1: Small dogs', 'taken' => 2, 'spots' => 4 ), array( 'label' => '12:00 – 2:00 PM · Session 2', 'taken' => 1, 'spots' => 4 ) ) === $list['sessions'] || ( var_dump( $list['sessions'] ) && false ) );
t( 'sign-ups: the rows, newest first, with dogs and the total', 2 === count( $list['rows'] ) && $s2['reference'] === $list['rows'][0]['reference'] && 'Rex' === $list['rows'][1]['dogs'][0]['name'] && '2 × $15 = $30, paid at the event' === $list['rows'][1]['total_text'] && 'confirmed' === $list['rows'][1]['status'] );
t( 'sign-ups: the counts', array( 'signups' => 2, 'dogs' => 3 ) === $list['counts'] );
t( 'sign-ups: the CSV link is a plain URL with its own nonce', str_contains( $list['csv_url'], 'action=paw_signups_csv' ) && str_contains( $list['csv_url'], 'event=' . $party ) && str_contains( $list['csv_url'], '_wpnonce=' ) && ! str_contains( $list['csv_url'], '&amp;' ) );
t( 'sign-ups: never the member code', ! str_contains( wp_json_encode( $list ), 'PawPark' ) );
t( 'sign-ups: not an event is a 404', is_wp_error( StaffEvents::signups( req( array(), array( 'id' => (string) $page ) ) ) ) );
$row = array_values( array_filter( StaffEvents::list( req( array(), array( 'tab' => 'upcoming' ) ) )->get_data()['rows'], static fn( $r ) => $r['id'] === $party ) )[0];
t( 'list rows count the dogs signed up', 3 === $row['dogs'] );

$st = StaffEvents::signup_status( req( array( 'status' => 'cancelled' ), array( 'id' => (string) $s1['id'] ) ) );
t( 'status: changed, and the cancel frees its spots', 200 === $st->get_status() && 'cancelled' === $st->get_data()['row']['status'] && 0 === StaffEvents::signups( req( array(), array( 'id' => (string) $party ) ) )->get_data()['sessions'][0]['taken'] );
t( 'status: only known statuses', is_wp_error( StaffEvents::signup_status( req( array( 'status' => 'lost' ), array( 'id' => (string) $s1['id'] ) ) ) ) );
t( 'status: not a sign-up is a 404', 404 === StaffEvents::signup_status( req( array( 'status' => 'attended' ), array( 'id' => (string) $party ) ) )->get_error_data()['status'] );

// Sessions with sign-ups keep their place and times (sign-ups point at them by position).
$sess  = static fn( array $rows ): array => body( array( 'title' => 'Sign-up party', 'spots' => '4', 'sections' => array( 'sessions' => array( 'rows' => $rows ) ) ) );
$s1row = array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs' );
$s2row = array( 'from' => '12:00', 'to' => '14:00', 'name' => 'Session 2', 'who' => '' );
$s3row = array( 'from' => '14:00', 'to' => '15:00', 'name' => 'Session 3', 'who' => '' );
$moved = StaffEvents::save( req( $sess( array( $s2row, $s1row ) ), array( 'id' => (string) $party ) ) );
t( 'sessions: moving one that has sign-ups is refused', is_wp_error( $moved ) && isset( $moved->get_error_data()['fields']['sections.sessions'] ) );
$gone = StaffEvents::save( req( $sess( array( $s1row ) ), array( 'id' => (string) $party ) ) );
t( 'sessions: removing one that has sign-ups is refused', is_wp_error( $gone ) );
$retimed = StaffEvents::save( req( $sess( array( $s1row, array_merge( $s2row, array( 'to' => '15:00' ) ) ) ), array( 'id' => (string) $party ) ) );
t( 'sessions: retiming one that has sign-ups is refused', is_wp_error( $retimed ) );
$renamed = StaffEvents::save( req( $sess( array( $s1row, array_merge( $s2row, array( 'name' => 'Big dogs' ) ), $s3row ) ), array( 'id' => (string) $party ) ) );
t( 'sessions: renaming them and adding one at the end is fine', ! is_wp_error( $renamed ) && 3 === count( Events::get( $party )['sections']['sessions']['rows'] ) );

$csv = StaffEvents::csv_lines( $party );
t( 'CSV: a header, one row per dog, formulas defused', 'Reference' === $csv[0][0] && 4 === count( $csv ) && in_array( "'=cmd()", array_merge( ...$csv ), true ) );
t( 'CSV: needs the capability and its nonce', StaffEvents::csv_allowed( $party, wp_create_nonce( 'paw_signups_csv_' . $party ) ) && ! StaffEvents::csv_allowed( $party, 'bad' ) );
wp_set_current_user( $owner->ID );
t( 'CSV: owners are refused', ! StaffEvents::csv_allowed( $party, wp_create_nonce( 'paw_signups_csv_' . $party ) ) );
wp_set_current_user( $desk->ID );

// Categories: manage-settings only.
t( 'categories: staff without settings access are refused', false === StaffRest::can_settings() );
$boss = paw_test_user( 'boss@example.com', 'boss pass1', array( 'administrator' ), array( 'manage_options' => true, Activator::CAPABILITY => true ) );
wp_set_current_user( $boss->ID );
$saved = StaffEvents::categories( req( array( 'categories' => array( array( 'name' => 'Holiday party', 'color' => '#0f42c0' ), array( 'name' => 'Training', 'color' => '#nothex' ) ) ) ) )->get_data()['categories'];
t( 'categories: created, with a palette colour', array( 'Holiday party', 'Training' ) === array_column( $saved, 'name' ) && '#0f42c0' === $saved[0]['color'] && '#ff7f50' === $saved[1]['color'] );
$again = StaffEvents::categories( req( array( 'categories' => array( array( 'slug' => $saved[0]['slug'], 'name' => 'Holidays', 'color' => '#06d6a0' ) ) ) ) )->get_data()['categories'];
t( 'categories: renamed, recoloured, and the missing one deleted', 1 === count( $again ) && 'Holidays' === $again[0]['name'] && '#06d6a0' === $again[0]['color'] );
$blank = StaffEvents::categories( req( array( 'categories' => array( array( 'name' => '' ) ) ) ) );
t( 'categories: a name is required', is_wp_error( $blank ) && isset( $blank->get_error_data()['fields']['categories.0.name'] ) );

done();
