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
	array( array( 'name' => 'rabies.pdf', 'path' => '2030/01/x.pdf', 'mime' => 'application/pdf', 'size' => 2048 ), array( 'name' => 'card.png', 'path' => '2030/01/y.png', 'mime' => 'image/png', 'size' => 4096 ) ),
	'2030/01/x-sig.png', '203.0.113.9', '2026-10-25 10:00:00'
);
$id = Reservations::insert( $row );

function get_users( $args = array() ) { return array_values( array_filter( $GLOBALS['paw_users'], fn( $u ) => in_array( $args['role'] ?? '', $u->roles, true ) ) ); }
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
t( 'list rows carry the booking type and service', 'boarding' === $list['rows'][0]['booking_type'] && '' === $list['rows'][0]['service_name'] );
t( 'list rows carry the pet count', 1 === $list['rows'][0]['pet_count'] );
t( 'list rows carry no health details', ! isset( $list['rows'][0]['details'] ) );

$detail = StaffRest::detail( req( array(), array( 'id' => (string) $id ) ) );
$d      = $detail->get_data();
t( 'detail has the answers html', str_contains( $d['answers_html'], 'Beagle' ) && str_contains( $d['answers_html'], 'Pet Information' ) );
t( 'answers html escapes owner text', ! str_contains( $d['answers_html'], '<script>x</script>' ) && str_contains( $d['answers_html'], '&lt;script&gt;' ) );
t( 'files and signature through the staff-only handler', str_contains( $d['files'][0]['url'], 'action=paw_file' ) && str_contains( $d['signature_url'], 'n=sig' ) );
t( 'file and signature links are plain URLs, not HTML-escaped (the dashboard sets them as src and href)', ! str_contains( $d['signature_url'], '&amp;' ) && str_contains( $d['signature_url'], '&n=sig&_wpnonce=' ) && ! str_contains( $d['files'][0]['url'], '&amp;' ) );
t( 'files say which are images', false === $d['files'][0]['image'] && true === $d['files'][1]['image'] && 'card.png' === $d['files'][1]['name'] );
t( 'history with labels', 'Pending' === $d['history'][0]['label'] );
t( 'the admin-email account gets the wp-admin (print) link', str_contains( $d['admin_url'], 'page=paw-reservations' ) );
$GLOBALS['paw_options']['admin_email'] = 'boss@example.com';
t( 'no wp-admin link for staff who cannot open wp-admin', '' === StaffRest::detail( req( array(), array( 'id' => (string) $id ) ) )->get_data()['admin_url'] );
$GLOBALS['paw_options']['admin_email'] = 'desk@example.com';
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
// Settings from the dashboard.
use Pawfectly\Settings;
wp_set_current_user( $desk->ID );
$GLOBALS['paw_options']['admin_email'] = 'boss@example.com';
t( 'settings: staff are refused', false === StaffRest::can_settings() );
$dev = paw_test_user( 'dev@example.com', 'dev pass1', array( 'developer' ) );
wp_set_current_user( $dev->ID );
t( 'settings: developers allowed', true === StaffRest::can_settings() );
$saved = StaffRest::save_settings( req( array( 'business_name' => 'Pawfectly Uptown', 'boarding_rates' => array( 'small' => '30' ), 'services' => array( 'basic_bath' => array( 'small' => '45', 'enabled' => true ) ) ) ) );
t( 'settings: saved and normalised', 200 === $saved->get_status() && '45.00' === Settings::get()['services']['basic_bath']['small'] && '30.00' === Settings::get()['boarding_rates']['small'] && 'Pawfectly Uptown' === Settings::get()['business_name'] );
t( 'settings: what was not sent is kept', '$' === Settings::get()['currency'] && '59.00' === Settings::get()['services']['bath_membership']['small'] );
t( 'settings: response carries the saved values', '45.00' === $saved->get_data()['settings']['services']['basic_bath']['small'] );
$bad = StaffRest::save_settings( req( array( 'services' => array( 'basic_bath' => array( 'small' => 'abc', 'cat' => 'x' ) ), 'boarding_rates' => array( 'large' => '-5' ) ) ) );
t( 'settings: a bad price is a field error and nothing is saved', is_wp_error( $bad ) && 400 === $bad->get_error_data()['status'] && isset( $bad->get_error_data()['fields']['services.basic_bath.small'], $bad->get_error_data()['fields']['services.basic_bath.cat'], $bad->get_error_data()['fields']['boarding_rates.large'] ) && '45.00' === Settings::get()['services']['basic_bath']['small'] );
t( 'settings: lockout warning when nobody can reach wp-admin', false === StaffRest::lockout_risk() );
$GLOBALS['paw_options']['admin_email'] = 'nobody@example.com';
$dev->roles = array( 'administrator' );
t( 'settings: lockout warning shown', true === StaffRest::lockout_risk() );
wp_set_current_user( $desk->ID );

t( 'stats shape', isset( $stats['check_ins'], $stats['boarding'], $stats['pending'] ) && 14 === count( $stats['nights'] ) );

done();
