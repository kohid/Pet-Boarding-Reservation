<?php
/**
 * POST /pawfectly/v1/reservations end to end, against the in-memory database and real temp files.
 * Run: php tests/rest-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

use Pawfectly\Reference;
use Pawfectly\Rest;
use Pawfectly\Uploads;

Uploads::$is_uploaded = static fn( string $path ): bool => is_file( $path );
Uploads::$mover       = static fn( string $from, string $to ): bool => copy( $from, $to );

$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$pdf = fixture_file( 'rec.pdf', fixture_pdf_bytes() );

$good = paw_with(
	paw_valid_params(),
	array(
		'address_city'     => '',
		'feeding_schedule' => 'Twice a day',
		'emergency_name'   => 'Charles',
		'owner_phone'      => '555 010 2030',
		'signature'        => 'data:image/png;base64,' . base64_encode( fixture_png_bytes() ),
	)
);

function request( array $params, array $override = array(), ?array $files = null, string $nonce = 'good-nonce' ): WP_REST_Request {
	global $pdf;
	$r          = new WP_REST_Request();
	$r->params  = paw_with( $params, $override );
	$r->files   = $files ?? array( 'vaccination_files_0' => fixture_files_field( array( 'Rabies.pdf' => $pdf ) ) );
	$r->headers = array( 'x_wp_nonce' => $nonce );
	return $r;
}
function inserts(): int {
	return count( array_filter( $GLOBALS['wpdb']->queries, fn( $q ) => str_starts_with( $q, 'INSERT' ) ) );
}
function reset_state(): void {
	$GLOBALS['wpdb']           = new Fake_WPDB();
	$GLOBALS['paw_transients'] = array();
}
function private_files(): array {
	return glob( Uploads::private_dir() . '/*/*/PAW-*' ) ?: array();
}

// Happy path.
reset_state();
$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';
$res = Rest::submit( request( $good ) );
t( 'good submission → 201', $res instanceof WP_REST_Response && 201 === $res->get_status() );
t( 'returns a valid reference', $res instanceof WP_REST_Response && Reference::is_valid( $res->get_data()['reference'] ) );
t( 'one row inserted', 1 === inserts() );
$row = $wpdb->rows[1] ?? array();
t( 'row has owner email and pet', 'ada@example.com' === ( $row['owner_email'] ?? '' ) && 'Biscuit' === ( $row['pet_name'] ?? '' ) );
t( 'row records the IP', '203.0.113.9' === ( $row['signed_ip'] ?? '' ) );
$saved = json_decode( $row['details'] ?? '{}', true );
t( 'row keeps the exact waiver text that was signed', \Pawfectly\Settings::get()['waiver'] === ( $saved['waiver_text'] ?? null ) );
t( 'file and signature stored privately', 2 === count( private_files() ) );

t( 'owner and staff emailed', 2 === count( $GLOBALS['paw_mail'] ) && 'ada@example.com' === $GLOBALS['paw_mail'][0]['to'] );
t( 'boarding row: type stored, no grooming day', 'boarding' === ( $row['booking_type'] ?? '' ) && array_key_exists( 'appointment_date', $row ) && null === $row['appointment_date'] );
t( 'price snapshot stored with the reservation', isset( $saved['pricing']['lines'][0]['label'] ) && 'Biscuit: Boarding, 2 nights' === $saved['pricing']['lines'][0]['label'] );

reset_state();
$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'services' => array( 'basic_bath' => array( 'small' => '45' ) ) );
$res = Rest::submit( request( paw_with( $good, array( 'booking_type' => 'grooming', 'dog_size' => 'small', 'service' => 'basic_bath', 'grooming_frequency' => 'once', 'appointment_date' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ), 'appointment_time' => 'morning', 'check_in' => '', 'check_out' => '' ) ) ) );
$grow  = $wpdb->rows[1] ?? array();
$gsnap = json_decode( $grow['details'] ?? '{}', true )['pricing'] ?? array();
t( 'grooming booking saved', $res instanceof WP_REST_Response && 'grooming' === ( $grow['booking_type'] ?? '' ) && 'basic_bath' === ( $grow['service'] ?? '' ) );
t( 'grooming snapshot: service and price at booking time', 'Biscuit: Basic Bath' === ( $gsnap['lines'][0]['label'] ?? '' ) && 45.0 === (float) ( $gsnap['lines'][0]['amount'] ?? 0 ) );
$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'services' => array( 'basic_bath' => array( 'small' => '60' ) ) );
t( 'a later price change does not touch the stored booking', 45.0 === (float) ( json_decode( $wpdb->rows[1]['details'], true )['pricing']['lines'][0]['amount'] ?? 0 ) );
unset( $GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] );

// Nonce, rate limit, honeypot.
reset_state();
$res = Rest::submit( request( $good, array(), null, 'stale' ) );
t( 'bad nonce → 403 paw_nonce', is_wp_error( $res ) && 'paw_nonce' === $res->get_error_code() && 403 === $res->get_error_data()['status'] );
t( 'bad nonce inserts nothing', 0 === inserts() );

reset_state();
for ( $i = 0; $i < 5; $i++ ) {
	Rest::submit( request( $good, array( 'owner_email' => 'bad' ) ) );
}
$res = Rest::submit( request( $good ) );
t( 'sixth submission in the window → 429', is_wp_error( $res ) && 429 === $res->get_error_data()['status'] );

reset_state();
$res = Rest::submit( request( $good, array( 'website' => 'http://spam.example' ) ) );
t( 'honeypot → 201 with a reference', $res instanceof WP_REST_Response && 201 === $res->get_status() && Reference::is_valid( $res->get_data()['reference'] ) );
t( 'honeypot inserts nothing', 0 === inserts() );

// Validation errors from fields, files and signature arrive together.
reset_state();
$res = Rest::submit( request( $good, array( 'owner_email' => 'nope', 'signature' => '' ), array() ) );
$fields = is_wp_error( $res ) ? $res->get_error_data()['fields'] : array();
t( 'invalid → 400 paw_invalid', is_wp_error( $res ) && 'paw_invalid' === $res->get_error_code() && 400 === $res->get_error_data()['status'] );
t( 'field, file and signature errors merged', isset( $fields['owner_email'], $fields['vaccination_files_0'], $fields['signature'] ) );
t( 'invalid inserts nothing', 0 === inserts() );

// Review Focus 1: the same submission sent twice makes one reservation.
reset_state();
$sid   = '3f2b8c1e-9a4d-4f6b-8e2a-1c5d7e9f0a3b';
$first = Rest::submit( request( $good, array( 'submission_id' => $sid ) ) );
$again = Rest::submit( request( $good, array( 'submission_id' => $sid ) ) );
t( 'repeat submission → same reference', $first instanceof WP_REST_Response && $again instanceof WP_REST_Response && $first->get_data()['reference'] === $again->get_data()['reference'] );
t( 'repeat submission → one insert', 1 === inserts() );
t( 'repeat answers 201', $again instanceof WP_REST_Response && 201 === $again->get_status() );
$other = Rest::submit( request( $good, array( 'submission_id' => '9d1e2f3a-4b5c-4d6e-9f70-8a9b0c1d2e3f' ) ) );
t( 'a different submission id makes a new reservation', 2 === inserts() && $other->get_data()['reference'] !== $first->get_data()['reference'] );

// Review Focus 2: a body over post_max_size arrives empty.
reset_state();
$_SERVER['CONTENT_LENGTH'] = (string) ( 64 * 1024 * 1024 * 1024 );
$empty                     = new WP_REST_Request();
$empty->headers            = array( 'x_wp_nonce' => 'good-nonce' );
$res = Rest::submit( $empty );
t( 'oversized body → 413 paw_too_large', is_wp_error( $res ) && 'paw_too_large' === $res->get_error_code() && 413 === $res->get_error_data()['status'] );
t( 'oversized message names the real total limit', is_wp_error( $res ) && str_contains( $res->get_error_message(), Uploads::human( Uploads::max_request_bytes() ) . ' in total' ) );
unset( $_SERVER['CONTENT_LENGTH'] );
t( 'post_max_size parsing', 8 * 1024 * 1024 === Rest::ini_bytes( '8M' ) && 1024 === Rest::ini_bytes( '1k' ) && 2 * 1024 ** 3 === Rest::ini_bytes( '2G' ) && 500 === Rest::ini_bytes( '500' ) );

// Storage and database failures clean up.
reset_state();
fixture_rmrf( Uploads::private_dir() );
$wpdb->fail_insert = true;
$res = Rest::submit( request( $good ) );
t( 'insert failure → 500', is_wp_error( $res ) && 500 === $res->get_error_data()['status'] );
t( 'insert failure removes stored files', array() === private_files() );

reset_state();
$wpdb->var_queue = array( '1', null ); // First reference generated is already taken.
$res = Rest::submit( request( $good ) );
t( 'taken reference → a new one is generated', $res instanceof WP_REST_Response && 201 === $res->get_status() && 1 === inserts() );

// Accounts from the owner step.
reset_state();
$GLOBALS['paw_mail'] = array();
$ada = paw_test_user( 'ada@example.com', 'some pass1', array( 'paw_customer' ) );
wp_set_current_user( $ada->ID );
Rest::submit( request( $good ) );
t( 'signed-in owner: reservation linked to the account', (string) $ada->ID === ( $wpdb->rows[1]['user_id'] ?? '' ) );
$ada_pets = get_user_meta( $ada->ID, \Pawfectly\SavedPets::META_PETS, true );
t( 'signed-in owner: the booking\'s pets are saved to My Profile', is_array( $ada_pets ) && $good['pets'][0]['pet_name'] === ( $ada_pets[0]['values']['pet_name'] ?? '' ) && is_array( get_user_meta( $ada->ID, \Pawfectly\SavedPets::META_INFO, true ) ) );
reset_state();
$staffer = paw_test_user( 'front@example.com', 'some pass1', array( 'administrator' ) );
wp_set_current_user( $staffer->ID );
Rest::submit( request( $good ) );
t( 'staff booking for a customer: not linked to the staff account', null === $wpdb->rows[1]['user_id'] );
t( 'staff booking for a customer: nothing saved to the staff account', '' === get_user_meta( $staffer->ID, \Pawfectly\SavedPets::META_PETS, true ) );
wp_set_current_user( 0 );

reset_state();
$res = Rest::submit( request( $good, array( 'create_account' => '1', 'account_password' => 'short' ) ) );
t( 'create account: short password → field error', is_wp_error( $res ) && 'Use at least 8 characters.' === ( $res->get_error_data()['fields']['account_password'] ?? '' ) && 0 === inserts() );
$res = Rest::submit( request( $good, array( 'create_account' => '1', 'account_password' => 'correct horse', 'account_password_confirm' => 'correct horsf' ) ) );
t( 'create account: confirm must match → field error', is_wp_error( $res ) && "The two passwords don't match." === ( $res->get_error_data()['fields']['account_password_confirm'] ?? '' ) && 0 === inserts() );
$res = Rest::submit( request( $good, array( 'create_account' => '1', 'account_password' => 'correct horse', 'account_password_confirm' => 'correct horse' ) ) );
t( 'create account: email already registered → field error on email', is_wp_error( $res ) && str_contains( $res->get_error_data()['fields']['owner_email'] ?? '', 'already uses this email' ) );

reset_state();
$res = Rest::submit( request( $good, array( 'owner_email' => 'Owner@example.com', 'create_account' => '1', 'account_password' => 'correct horse', 'account_password_confirm' => 'correct horse' ) ) );
t( "create account: the site's admin email is refused", is_wp_error( $res ) && isset( $res->get_error_data()['fields']['owner_email'] ) && ! email_exists( 'owner@example.com' ) );

reset_state();
$GLOBALS['paw_mail'] = array();
$res = Rest::submit( request( $good, array( 'owner_email' => 'new.owner@example.com', 'create_account' => '1', 'account_password' => 'correct horse', 'account_password_confirm' => 'correct horse' ) ) );
$new = get_user_by( 'email', 'new.owner@example.com' );
t( 'create account: reservation saved and account made', $res instanceof WP_REST_Response && 201 === $res->get_status() && $new instanceof WP_User );
t( 'create account: reservation linked and owner signed in', (string) $new->ID === ( $wpdb->rows[1]['user_id'] ?? '' ) && $new->ID === get_current_user_id() );
t( 'create account: response says the account is ready', true === ( $res->get_data()['account'] ?? null ) );
t( 'create account: verification and reservation emails sent', 3 === count( $GLOBALS['paw_mail'] ) && (bool) array_filter( $GLOBALS['paw_mail'], fn( $m ) => str_contains( $m['message'], 'paw_verify_t=' ) ) );
wp_set_current_user( 0 );

reset_state();
Rest::submit( request( $good, array( 'owner_email' => 'guest@example.com', 'account_password' => 'correct horse' ) ) );
t( 'unticked box: no account even if a password was typed', ! email_exists( 'guest@example.com' ) && null === $wpdb->rows[1]['user_id'] );

// Two pets, files for each.
reset_state();
$two = $good;
$two['pets'][1] = array_merge( $good['pets'][0], array( 'pet_name' => 'Luna', 'pet_type' => 'cat', 'dog_size' => '' ) );
$both_files = array(
	'vaccination_files_0' => fixture_files_field( array( 'Rex.pdf' => fixture_file( 'r.pdf', fixture_pdf_bytes() ) ) ),
	'vaccination_files_1' => fixture_files_field( array( 'Luna.pdf' => fixture_file( 'l.pdf', fixture_pdf_bytes() ) ) ),
);
$res = Rest::submit( request( $two, array(), $both_files ) );
$row = $wpdb->rows[1] ?? array();
$stored = json_decode( $row['files'] ?? '[]', true );
t( 'two pets: one booking, counted', $res instanceof WP_REST_Response && '2' === ( $row['pet_count'] ?? '' ) && 'Biscuit + Luna' === ( $row['pet_name'] ?? '' ) );
t( 'two pets: each file tagged with its pet', array( 0, 1 ) === array_column( $stored, 'pet' ) && array( 'Rex.pdf', 'Luna.pdf' ) === array_column( $stored, 'name' ) );
t( 'two pets: snapshot labelled per pet', str_starts_with( json_decode( $row['details'], true )['pricing']['lines'][1]['label'] ?? '', 'Luna: Boarding' ) );
reset_state();
$res = Rest::submit( request( $two, array(), array( 'vaccination_files_0' => $both_files['vaccination_files_0'] ) ) );
t( "the second pet's missing files are reported on its own field", is_wp_error( $res ) && isset( $res->get_error_data()['fields']['vaccination_files_1'] ) && ! isset( $res->get_error_data()['fields']['vaccination_files_0'] ) && 0 === inserts() );
reset_state();
$six = $good;
$six['pets'] = array_fill( 0, 6, $good['pets'][0] );
t( 'six pets → 400', 400 === Rest::submit( request( $six ) )->get_error_data()['status'] && 0 === inserts() );

// Grooming: vaccination files per the setting; the waiver that was signed.
$groom_only = paw_with( $good, array( 'booking_type' => 'grooming', 'dog_size' => 'small', 'service' => 'basic_bath', 'grooming_frequency' => 'once', 'appointment_date' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ), 'appointment_time' => 'morning', 'check_in' => '', 'check_out' => '' ) );
reset_state();
$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'grooming_needs_vaccines' => false, 'grooming_waiver' => '<p>Grooming terms</p>' );
$res = Rest::submit( request( $groom_only, array(), array() ) );
t( 'grooming without files when the switch is off → 201', $res instanceof WP_REST_Response && 201 === $res->get_status() );
t( 'grooming keeps the grooming waiver it was signed with', '<p>Grooming terms</p>' === ( json_decode( $wpdb->rows[1]['details'] ?? '{}', true )['waiver_text'] ?? '' ) );
reset_state();
$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'grooming_needs_vaccines' => true );
$res = Rest::submit( request( $groom_only, array(), array() ) );
t( 'grooming without files when records are needed → field error', is_wp_error( $res ) && isset( $res->get_error_data()['fields']['vaccination_files_0'] ) );
reset_state();
$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'waiver' => '<p>Boarding terms</p>', 'grooming_waiver' => '<p>Grooming terms</p>' );
Rest::submit( request( paw_with( $good, array( 'booking_type' => 'both', 'service' => 'nail_trim', 'grooming_frequency' => 'once', 'appointment_date' => gmdate( 'Y-m-d', strtotime( '+4 days' ) ) ) ) ) );
$wt = json_decode( $wpdb->rows[1]['details'] ?? '{}', true )['waiver_text'] ?? '';
t( 'boarding + grooming keeps both waivers', str_contains( $wt, 'Boarding terms' ) && str_contains( $wt, 'Grooming terms' ) );
unset( $GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] );

t( 'nonce endpoint', 'good-nonce' === Rest::nonce()->get_data()['nonce'] );

fixture_rmrf( $GLOBALS['paw_upload_base'] );
done();
