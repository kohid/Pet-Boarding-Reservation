<?php
/**
 * Server-side validation of every non-file field: the part that must not trust the browser.
 * Run: php tests/validator-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Validator;

$today = new DateTimeImmutable( '2026-10-25' );
$good  = array(
	'owner_first_name'       => 'Ada',
	'owner_last_name'        => 'Lovelace',
	'owner_email'            => 'ada@example.com',
	'owner_phone'            => '+1 (555) 010-2030',
	'address_street'         => '1 Main St',
	'address_city'           => 'Springfield',
	'address_state'          => 'IL',
	'address_postcode'       => '62701',
	'address_country'        => 'US',
	'pet_name'               => 'Biscuit',
	'pet_type'               => 'dog',
	'pet_breed'              => 'Beagle',
	'pet_age'                => '3 years',
	'pet_gender'             => 'male',
	'pet_weight'             => '25 lbs',
	'pet_spayed'             => 'yes',
	'pet_microchipped'       => 'no',
	'check_in'               => '2026-10-28',
	'check_out'              => '2026-10-30',
	'vaccinations_current'   => 'yes',
	'medical_conditions'     => 'no',
	'medications'            => 'no',
	'allergies'              => 'no',
	'feeding_schedule'       => 'Twice a day, one cup',
	'food_preference'        => 'own',
	'emergency_name'         => 'Charles Babbage',
	'emergency_phone'        => '555 010 9999',
	'emergency_relationship' => 'friend',
	'vet_name'               => 'Dr. Who',
	'vet_clinic'             => 'Tardis Vets',
	'vet_phone'              => '555-010-1111',
	'vet_authorization'      => 'yes',
	'waiver_agree'           => '1',
	'signed_date'            => '2026-10-25',
);

/** Field-error map for a submission, or null if it passed. */
function errors( array $params ): ?array {
	global $today;
	$r = Validator::validate( $params, $today );
	return is_wp_error( $r ) ? $r->get_error_data()['fields'] : null;
}
function only_error( array $params, string $field, string $message ): bool {
	return errors( $params ) === array( $field => $message );
}

$ok = Validator::validate( $good, $today );
t( 'good submission passes', is_array( $ok ) );
t( 'columns carry pet_type', 'dog' === $ok['columns']['pet_type'] );
t( 'columns are exactly the COLUMNS ids', array_keys( $ok['columns'] ) === \Pawfectly\Fields::COLUMNS );
t( 'details carry the rest', 'Beagle' === $ok['details']['pet_breed'] && ! isset( $ok['details']['owner_email'] ) );
t( 'agree is not stored in details', ! isset( $ok['details']['waiver_agree'] ) );
$bad = Validator::validate( array(), $today );
t( 'error code and status', is_wp_error( $bad ) && 'paw_invalid' === $bad->get_error_code() && 400 === $bad->get_error_data()['status'] );

t( 'empty first name', only_error( array_merge( $good, array( 'owner_first_name' => ' ' ) ), 'owner_first_name', 'This field is required.' ) );
t( 'bad email', only_error( array_merge( $good, array( 'owner_email' => 'nope' ) ), 'owner_email', 'Please enter a valid email address.' ) );
t( 'short phone', only_error( array_merge( $good, array( 'owner_phone' => '12' ) ), 'owner_phone', 'Please enter a valid phone number.' ) );
t( 'letters in phone', only_error( array_merge( $good, array( 'vet_phone' => '555-CALL-VET' ) ), 'vet_phone', 'Please enter a valid phone number.' ) );
t( 'unknown pet type', only_error( array_merge( $good, array( 'pet_type' => 'lizard' ) ), 'pet_type', 'Please choose one of the options.' ) );
t( 'yes/no must be yes or no', only_error( array_merge( $good, array( 'pet_spayed' => 'maybe' ) ), 'pet_spayed', 'Please choose one of the options.' ) );
t( 'check-in in the past', only_error( array_merge( $good, array( 'check_in' => '2026-10-24' ) ), 'check_in', "Check-in can't be in the past." ) );
t( 'check-in today is fine', null === errors( array_merge( $good, array( 'check_in' => '2026-10-25' ) ) ) );
t( 'check-out same day', only_error( array_merge( $good, array( 'check_out' => '2026-10-28' ) ), 'check_out', 'Check-out must be after check-in.' ) );
t( '90 nights is fine', null === errors( array_merge( $good, array( 'check_out' => '2027-01-26' ) ) ) );
t( '91 nights is too long', only_error( array_merge( $good, array( 'check_out' => '2027-01-27' ) ), 'check_out', 'Stays are limited to 90 nights.' ) );
t( 'impossible date', only_error( array_merge( $good, array( 'check_in' => '2026-02-30' ) ), 'check_in', 'Please enter a valid date.' ) );
t( 'US-format date rejected', only_error( array_merge( $good, array( 'check_in' => '10/28/2026' ) ), 'check_in', 'Please enter a valid date.' ) );
t( 'vaccinations not current', only_error( array_merge( $good, array( 'vaccinations_current' => 'no' ) ), 'vaccinations_current', 'All pets must have current vaccinations to board with us.' ) );
$no_agree = $good;
unset( $no_agree['waiver_agree'] );
t( 'agreement missing', only_error( $no_agree, 'waiver_agree', 'Please agree to the terms to continue.' ) );

t( 'medications yes needs details', only_error( array_merge( $good, array( 'medications' => 'yes' ) ), 'medications_details', 'This field is required.' ) );
$with = Validator::validate( array_merge( $good, array( 'medications' => 'yes', 'medications_details' => '5mg daily' ) ), $today );
t( 'medications details kept when shown', '5mg daily' === $with['details']['medications_details'] );
$hidden = Validator::validate( array_merge( $good, array( 'medications' => 'no', 'medications_details' => 'stale text' ) ), $today );
t( 'hidden conditional is dropped', ! array_key_exists( 'medications_details', $hidden['details'] ) );
t( 'other pet type needs specifying', only_error( array_merge( $good, array( 'pet_type' => 'other' ) ), 'pet_type_other', 'This field is required.' ) );

$no_city = Validator::validate( array_merge( $good, array( 'address_city' => '' ) ), $today );
t( 'empty city passes', is_array( $no_city ) && '' === $no_city['details']['address_city'] );
t( 'optional notes may be left out', is_array( $no_city ) && '' === $no_city['details']['special_requests'] );
t( 'unknown country', only_error( array_merge( $good, array( 'address_country' => 'XX' ) ), 'address_country', 'Please choose one of the options.' ) );

// REST hands over body params already unslashed; nothing typed may be altered.
$typed = Validator::validate( array_merge( $good, array( 'owner_last_name' => "O'Brien", 'feeding_schedule' => "1\\2 cup, 50%Beef\nLoves treats <3" ) ), $today );
t( 'apostrophe stored as typed', "O'Brien" === $typed['columns']['owner_last_name'] );
t( 'backslash, percent-hex and < kept as typed', "1\\2 cup, 50%Beef\nLoves treats <3" === $typed['details']['feeding_schedule'] );
t( 'control characters removed', 'Biscuit' === Validator::validate( array_merge( $good, array( 'pet_name' => "Bis\x00cu\x07it" ) ), $today )['columns']['pet_name'] );
t( 'line breaks folded in one-line fields', 'A B' === Validator::validate( array_merge( $good, array( 'pet_breed' => "A\r\nB" ) ), $today )['details']['pet_breed'] );
t( 'invalid UTF-8 rejected as empty', only_error( array_merge( $good, array( 'pet_name' => "\xC3\x28" ) ), 'pet_name', 'This field is required.' ) );
t( '60 accented characters fit', null === errors( array_merge( $good, array( 'owner_first_name' => str_repeat( 'ë', 60 ) ) ) ) );
t( '61 characters are too many', only_error( array_merge( $good, array( 'owner_first_name' => str_repeat( 'ë', 61 ) ) ), 'owner_first_name', 'Please keep this under 60 characters.' ) );
t( 'markup kept as text (escaped when shown)', '<b>Biscuit</b>' === Validator::validate( array_merge( $good, array( 'pet_name' => '<b>Biscuit</b>' ) ), $today )['columns']['pet_name'] );
t( 'arrays are not accepted as text', only_error( array_merge( $good, array( 'pet_name' => array( 'x' ) ) ), 'pet_name', 'This field is required.' ) );
t( 'textarea keeps line breaks', "a\nb" === Validator::validate( array_merge( $good, array( 'feeding_schedule' => "a\nb" ) ), $today )['details']['feeding_schedule'] );

done();
