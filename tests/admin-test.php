<?php
/**
 * wp-admin: CSV export is safe in spreadsheets and complete; the detail view shows every answer, escaped.
 * Run: php tests/admin-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Admin;
use Pawfectly\Fields;

function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) { $f = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; if ( $echo ) { echo $f; } return $f; }
function get_userdata( $id ) { return $id ? (object) array( 'display_name' => 'Sam Staff' ) : false; }
function current_user_can( $cap ) { return true; }
function disabled( $a, $b = true, $echo = true ) { $r = $a === $b ? ' disabled="disabled"' : ''; if ( $echo ) { echo $r; } return $r; }
function submit_button( $text = '' ) { echo '<button type="submit">' . esc_html( $text ) . '</button>'; }
function get_date_from_gmt( $s, $f = 'Y-m-d H:i:s' ) { return gmdate( $f, strtotime( $s . ' UTC' ) ); }

t( 'formula prefixed', "'=1+1" === Admin::csv_cell( '=1+1' ) );
t( 'minus prefixed', "'-5" === Admin::csv_cell( '-5' ) );
t( 'plus and at prefixed', "'+1 555" === Admin::csv_cell( '+1 555' ) && "'@SUM(A1)" === Admin::csv_cell( '@SUM(A1)' ) );
t( 'tab and CR prefixed', "'\tx" === Admin::csv_cell( "\tx" ) && "'\rx" === Admin::csv_cell( "\rx" ) );
t( 'plain text unchanged', 'Zoë' === Admin::csv_cell( 'Zoë' ) );

$r = array(
	'id'               => 5,
	'reference'        => 'PAW-7K3QX9',
	'status'           => 'confirmed',
	'owner_first_name' => 'Zoë',
	'owner_last_name'  => "O'Brien",
	'owner_email'      => 'zoe@example.com',
	'owner_phone'      => '+1 555 010 2030',
	'pet_name'         => 'Biscuit',
	'pet_type'         => 'dog',
	'check_in'         => '2026-10-28',
	'check_out'        => '2026-10-30',
	'details'          => array(
		'address_street' => '1 Main St', 'address_city' => '', 'address_state' => 'IL', 'address_postcode' => '62701', 'address_country' => 'US',
		'pet_breed' => 'Beagle', 'pet_age' => '3 years', 'pet_gender' => 'male', 'pet_weight' => '25 lbs', 'pet_spayed' => 'yes', 'pet_microchipped' => 'no',
		'special_requests' => '', 'vaccinations_current' => 'yes', 'medical_conditions' => 'no', 'medications' => 'yes', 'medications_details' => '5mg daily',
		'allergies' => 'no', 'feeding_schedule' => "Morning\nEvening", 'food_preference' => 'facility', 'dietary_notes' => '',
		'behavior_notes' => '<script>alert(1)</script>', 'exercise_needs' => '', 'emergency_name' => 'Charles', 'emergency_phone' => '555 010 9999',
		'emergency_relationship' => 'friend', 'vet_name' => 'Dr. Who', 'vet_clinic' => 'Tardis', 'vet_phone' => '555 010 1111', 'vet_authorization' => 'yes',
		'signed_date' => '2026-10-25', 'waiver_text' => '<p>Old waiver wording v1</p><script>x</script>',
	),
	'files'            => array( array( 'name' => 'Rabies <1>.pdf', 'path' => '2026/10/x.pdf', 'mime' => 'application/pdf', 'size' => 2048 ) ),
	'signature_path'   => '2026/10/x-sig.png',
	'signed_at'        => '2026-10-25 14:00:00',
	'signed_ip'        => '203.0.113.9',
	'staff_notes'      => 'Kennel 4',
	'status_log'       => array( array( 'status' => 'pending', 'user_id' => 0, 'at' => '2026-10-25 14:00:00' ), array( 'status' => 'confirmed', 'user_id' => 3, 'at' => '2026-10-26 09:00:00' ) ),
	'created_at'       => '2026-10-25 14:00:00',
	'updated_at'       => '2026-10-26 09:00:00',
);

$header = Admin::csv_header();
$row    = Admin::csv_row( $r );
t( 'header and row line up', count( $header ) === count( $row ) );
t( 'starts with reference and status', 'PAW-7K3QX9' === $row[0] && 'Confirmed' === $row[1] );
$col = array_flip( Admin::csv_keys() );
t( 'phone escaped against formulas', "'+1 555 010 2030" === $row[ $col['owner_phone'] ] );
t( 'choices exported as their labels', 'Dog' === $row[ $col['pet_type'] ] && 'Please use facility food' === $row[ $col['food_preference'] ] );
t( 'country exported by name', 'United States' === $row[ $col['address_country'] ] );
t( 'hidden conditional is empty', '' === $row[ $col['allergies_details'] ] );
t( 'shown conditional exported', '5mg daily' === $row[ $col['medications_details'] ] );
t( 'files counted', '1' === $row[ $col['files_count'] ] );
t( 'no file, signature or agree columns', ! isset( $col['vaccination_files'] ) && ! isset( $col['signature'] ) && ! isset( $col['waiver_agree'] ) );

ob_start();
Admin::render_detail( $r );
$html = ob_get_clean();
foreach ( Fields::steps() as $step ) {
	t( "detail shows step: {$step['title']}", str_contains( $html, esc_html( $step['title'] ) ) );
}
t( 'script in notes escaped', ! str_contains( $html, '<script>alert(1)</script>' ) && str_contains( $html, '&lt;script&gt;alert(1)&lt;/script&gt;' ) );
t( 'file name escaped', str_contains( $html, 'Rabies &lt;1&gt;.pdf' ) );
t( 'signature served through the staff-only handler', str_contains( $html, 'admin-post.php?action=paw_file&amp;id=5&amp;n=sig' ) );
t( 'file link through the staff-only handler', str_contains( $html, 'admin-post.php?action=paw_file&amp;id=5&amp;n=0' ) );
t( 'signing details shown', str_contains( $html, '203.0.113.9' ) );
t( 'dates shown as m/d/Y', str_contains( $html, '10/28/2026' ) );
t( 'textarea line breaks kept', str_contains( $html, 'Morning<br />' ) );
t( 'yes on a health question highlighted', (bool) preg_match( '/paw-flag[^>]*>Yes/', $html ) );
t( 'hidden conditional not listed', ! str_contains( $html, 'Please describe the allergies' ) );
t( 'status form with nonce', str_contains( $html, 'name="action" value="paw_status"' ) && str_contains( $html, 'nonce-paw_status_5' ) );
t( 'current status selected', str_contains( $html, '<option value="confirmed" selected="selected">' ) );
t( 'history names the staff member', str_contains( $html, 'Sam Staff' ) );
t( 'notes form', str_contains( $html, 'name="action" value="paw_notes"' ) && str_contains( $html, 'Kennel 4' ) );
t( 'print button', str_contains( $html, 'data-paw-print' ) );
t( 'detail shows the waiver as signed', str_contains( $html, '<p>Old waiver wording v1</p>' ) && ! str_contains( $html, '<script>x</script>' ) );
t( 'waiver text is not exported as a CSV column', ! in_array( 'waiver_text', Admin::csv_keys(), true ) );

done();
