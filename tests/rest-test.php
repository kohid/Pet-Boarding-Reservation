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

$good = array(
	'owner_first_name' => 'Ada', 'owner_last_name' => 'Lovelace', 'owner_email' => 'ada@example.com', 'owner_phone' => '555 010 2030',
	'address_street' => '1 Main St', 'address_city' => '', 'address_state' => 'IL', 'address_postcode' => '62701', 'address_country' => 'US',
	'pet_name' => 'Biscuit', 'pet_type' => 'dog', 'dog_size' => 'large', 'booking_type' => 'boarding', 'pet_breed' => 'Beagle', 'pet_age' => '3 years', 'pet_gender' => 'male', 'pet_weight' => '25 lbs',
	'pet_spayed' => 'yes', 'pet_microchipped' => 'no',
	'check_in' => gmdate( 'Y-m-d', strtotime( '+3 days' ) ), 'check_out' => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
	'vaccinations_current' => 'yes', 'medical_conditions' => 'no', 'medications' => 'no', 'allergies' => 'no',
	'feeding_schedule' => 'Twice a day', 'food_preference' => 'own',
	'emergency_name' => 'Charles', 'emergency_phone' => '555 010 9999', 'emergency_relationship' => 'friend',
	'vet_name' => 'Dr. Who', 'vet_clinic' => 'Tardis Vets', 'vet_phone' => '555-010-1111', 'vet_authorization' => 'yes',
	'waiver_agree' => '1', 'signed_date' => gmdate( 'Y-m-d' ),
	'signature' => 'data:image/png;base64,' . base64_encode( fixture_png_bytes() ),
);

function request( array $params, array $override = array(), ?array $files = null, string $nonce = 'good-nonce' ): WP_REST_Request {
	global $pdf;
	$r          = new WP_REST_Request();
	$r->params  = array_merge( $params, $override );
	$r->files   = $files ?? array( 'vaccination_files' => fixture_files_field( array( 'Rabies.pdf' => $pdf ) ) );
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
t( 'field, file and signature errors merged', isset( $fields['owner_email'], $fields['vaccination_files'], $fields['signature'] ) );
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
reset_state();
$staffer = paw_test_user( 'front@example.com', 'some pass1', array( 'administrator' ) );
wp_set_current_user( $staffer->ID );
Rest::submit( request( $good ) );
t( 'staff booking for a customer: not linked to the staff account', '' === ( $wpdb->rows[1]['user_id'] ?? 'x' ) );
wp_set_current_user( 0 );

reset_state();
$res = Rest::submit( request( $good, array( 'create_account' => '1', 'account_password' => 'short' ) ) );
t( 'create account: short password → field error', is_wp_error( $res ) && 'Use at least 8 characters.' === ( $res->get_error_data()['fields']['account_password'] ?? '' ) && 0 === inserts() );
$res = Rest::submit( request( $good, array( 'create_account' => '1', 'account_password' => 'correct horse', 'account_password_confirm' => 'correct horsf' ) ) );
t( 'create account: confirm must match → field error', is_wp_error( $res ) && "The two passwords don't match." === ( $res->get_error_data()['fields']['account_password_confirm'] ?? '' ) && 0 === inserts() );
$res = Rest::submit( request( $good, array( 'create_account' => '1', 'account_password' => 'correct horse', 'account_password_confirm' => 'correct horse' ) ) );
t( 'create account: email already registered → field error on email', is_wp_error( $res ) && str_contains( $res->get_error_data()['fields']['owner_email'] ?? '', 'already uses this email' ) );

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
t( 'unticked box: no account even if a password was typed', ! email_exists( 'guest@example.com' ) && '' === ( $wpdb->rows[1]['user_id'] ?? 'x' ) );

t( 'nonce endpoint', 'good-nonce' === Rest::nonce()->get_data()['nonce'] );

fixture_rmrf( $GLOBALS['paw_upload_base'] );
done();
