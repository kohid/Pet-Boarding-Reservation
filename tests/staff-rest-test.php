<?php
/**
 * Staff dashboard data: every route needs the staff capability; list, detail, status, notes, delete.
 * Run: php tests/staff-rest-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';

use Pawfectly\Accounts;
use Pawfectly\Activator;
use Pawfectly\Reservations;
use Pawfectly\StaffRest;

function get_date_from_gmt( $s, $f = 'Y-m-d H:i:s' ) { return gmdate( $f, strtotime( $s . ' UTC' ) ); }
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

$GLOBALS['paw_options']['admin_email'] = 'desk@example.com';
$row = Reservations::build_row(
	array(
		'columns' => array( 'owner_first_name' => 'Zoë', 'owner_last_name' => "O'Brien", 'owner_email' => 'zoe@example.com', 'owner_phone' => '555', 'pet_name' => 'Biscuit', 'pet_type' => 'dog', 'check_in' => '2030-01-10', 'check_out' => '2030-01-13' ),
		'details' => array( 'behavior_notes' => '<script>x</script>', 'pet_breed' => 'Beagle' ),
	),
	'PAW-7K3QX9',
	array( array( 'name' => 'rabies.pdf', 'path' => '2030/01/x.pdf', 'mime' => 'application/pdf', 'size' => 2048 ) ),
	'2030/01/x-sig.png', '203.0.113.9', '2026-10-25 10:00:00'
);
$id = Reservations::insert( $row );

function req( array $json = array(), array $url = array() ): WP_REST_Request {
	$r       = new WP_REST_Request();
	$r->json = $json;
	$r->url  = $url;
	return $r;
}

$owner = paw_test_user( 'zoe@example.com', 'some pass1', array( Accounts::ROLE ) );
wp_set_current_user( $owner->ID );
t( 'owners are refused', false === StaffRest::can() );
wp_set_current_user( 0 );
t( 'visitors are refused', false === StaffRest::can() );
$desk = paw_test_user( 'desk@example.com', 'desk pass1', array( Accounts::STAFF_ROLE ), array( Activator::CAPABILITY => true ) );
wp_set_current_user( $desk->ID );
t( 'staff allowed', true === StaffRest::can() );

$list = StaffRest::list( req( array(), array( 'status' => 'pending' ) ) )->get_data();
t( 'list rows with summary fields', 1 === count( $list['rows'] ) && 'PAW-7K3QX9' === $list['rows'][0]['reference'] && "Zoë O'Brien" === $list['rows'][0]['owner'] && 3 === $list['rows'][0]['nights'] && 'Pending' === $list['rows'][0]['status_label'] );
t( 'list counts per status', isset( $list['counts']['pending'] ) );
t( 'list rows carry no health details', ! isset( $list['rows'][0]['details'] ) );

$detail = StaffRest::detail( req( array(), array( 'id' => (string) $id ) ) );
$d      = $detail->get_data();
t( 'detail has the answers html', str_contains( $d['answers_html'], 'Beagle' ) && str_contains( $d['answers_html'], 'Pet Information' ) );
t( 'answers html escapes owner text', ! str_contains( $d['answers_html'], '<script>x</script>' ) && str_contains( $d['answers_html'], '&lt;script&gt;' ) );
t( 'files and signature through the staff-only handler', str_contains( $d['files'][0]['url'], 'action=paw_file' ) && str_contains( $d['signature_url'], 'n=sig' ) );
t( 'history with labels', 'Pending' === $d['history'][0]['label'] );
t( 'missing reservation → 404', 404 === StaffRest::detail( req( array(), array( 'id' => '999' ) ) )->get_error_data()['status'] );

$GLOBALS['paw_mail'] = array();
$s = StaffRest::status( req( array( 'status' => 'confirmed', 'notify' => true ), array( 'id' => (string) $id ) ) );
t( 'status changed', 'confirmed' === $s->get_data()['status'] && 'confirmed' === Reservations::find( $id )['status'] );
t( 'owner emailed when asked', 1 === count( $GLOBALS['paw_mail'] ) && 'zoe@example.com' === $GLOBALS['paw_mail'][0]['to'] );
t( 'history records the staff member', $desk->ID === end( Reservations::find( $id )['status_log'] )['user_id'] );
t( 'unknown status → 400', 400 === StaffRest::status( req( array( 'status' => 'teleported' ), array( 'id' => (string) $id ) ) )->get_error_data()['status'] );

StaffRest::notes( req( array( 'notes' => 'Kennel 4' ), array( 'id' => (string) $id ) ) );
t( 'notes saved', 'Kennel 4' === Reservations::find( $id )['staff_notes'] );

$del = StaffRest::delete( req( array(), array( 'id' => (string) $id ) ) );
t( 'delete removes the reservation', true === $del->get_data()['deleted'] && null === Reservations::find( $id ) );

$stats = StaffRest::stats( req() )->get_data();
t( 'stats shape', isset( $stats['check_ins'], $stats['boarding'], $stats['pending'] ) && 14 === count( $stats['nights'] ) );

done();
