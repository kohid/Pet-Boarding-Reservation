<?php
/**
 * Server-side validation of every non-file field: the part that must not trust the browser.
 * Run: php tests/validator-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';

use Pawfectly\Validator;

$today = new DateTimeImmutable( '2026-10-25' );
$good  = paw_with( paw_valid_params(), array( 'check_in' => '2026-10-28', 'check_out' => '2026-10-30', 'signed_date' => '2026-10-25' ) );

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
t( 'details carry the rest', 'Beagle' === $ok['details']['pets'][0]['pet_breed'] && ! isset( $ok['details']['owner_email'] ) );
t( 'agree is not stored in details', ! isset( $ok['details']['waiver_agree'] ) );
$bad = Validator::validate( array(), $today );
t( 'error code and status', is_wp_error( $bad ) && 'paw_invalid' === $bad->get_error_code() && 400 === $bad->get_error_data()['status'] );

t( 'empty first name', only_error( paw_with( $good, array( 'owner_first_name' => ' ' ) ), 'owner_first_name', 'This field is required.' ) );
t( 'bad email', only_error( paw_with( $good, array( 'owner_email' => 'nope' ) ), 'owner_email', 'Please enter a valid email address.' ) );
t( 'short phone', only_error( paw_with( $good, array( 'owner_phone' => '12' ) ), 'owner_phone', 'Please enter a valid phone number.' ) );
t( 'letters in phone', only_error( paw_with( $good, array( 'vet_phone' => '555-CALL-VET' ) ), 'vet_phone', 'Please enter a valid phone number.' ) );
t( 'unknown pet type', only_error( paw_with( $good, array( 'pet_type' => 'lizard' ) ), 'pets.0.pet_type', 'Please choose one of the options.' ) );
t( 'yes/no must be yes or no', only_error( paw_with( $good, array( 'pet_spayed' => 'maybe' ) ), 'pets.0.pet_spayed', 'Please choose one of the options.' ) );
t( 'check-in in the past', only_error( paw_with( $good, array( 'check_in' => '2026-10-24' ) ), 'check_in', "Check-in can't be in the past." ) );
t( 'check-in today is fine', null === errors( paw_with( $good, array( 'check_in' => '2026-10-25' ) ) ) );
t( 'check-out same day', only_error( paw_with( $good, array( 'check_out' => '2026-10-28' ) ), 'check_out', 'Check-out must be after check-in.' ) );
t( '90 nights is fine', null === errors( paw_with( $good, array( 'check_out' => '2027-01-26' ) ) ) );
t( '91 nights is too long', only_error( paw_with( $good, array( 'check_out' => '2027-01-27' ) ), 'check_out', 'Stays are limited to 90 nights.' ) );
t( 'impossible date', only_error( paw_with( $good, array( 'check_in' => '2026-02-30' ) ), 'check_in', 'Please enter a valid date.' ) );
t( 'US-format date rejected', only_error( paw_with( $good, array( 'check_in' => '10/28/2026' ) ), 'check_in', 'Please enter a valid date.' ) );
t( 'vaccinations not current', only_error( paw_with( $good, array( 'vaccinations_current' => 'no' ) ), 'pets.0.vaccinations_current', 'All pets must have current vaccinations to board with us.' ) );
$no_agree = $good;
unset( $no_agree['waiver_agree'] );
t( 'agreement missing', only_error( $no_agree, 'waiver_agree', 'Please agree to the terms to continue.' ) );

t( 'medications yes needs details', only_error( paw_with( $good, array( 'medications' => 'yes' ) ), 'pets.0.medications_details', 'This field is required.' ) );
$with = Validator::validate( paw_with( $good, array( 'medications' => 'yes', 'medications_details' => '5mg daily' ) ), $today );
t( 'medications details kept when shown', '5mg daily' === $with['details']['pets'][0]['medications_details'] );
$hidden = Validator::validate( paw_with( $good, array( 'medications' => 'no', 'medications_details' => 'stale text' ) ), $today );
t( 'hidden conditional is dropped', ! array_key_exists( 'medications_details', $hidden['details']['pets'][0] ) );
t( 'other pet type needs specifying', only_error( paw_with( $good, array( 'pet_type' => 'other' ) ), 'pets.0.pet_type_other', 'This field is required.' ) );

$no_city = Validator::validate( paw_with( $good, array( 'address_city' => '' ) ), $today );
t( 'empty city passes', is_array( $no_city ) && '' === $no_city['details']['address_city'] );
t( 'optional notes may be left out', is_array( $no_city ) && '' === $no_city['details']['special_requests'] );
t( 'unknown country', only_error( paw_with( $good, array( 'address_country' => 'XX' ) ), 'address_country', 'Please choose one of the options.' ) );

// REST hands over body params already unslashed; nothing typed may be altered.
$typed = Validator::validate( paw_with( $good, array( 'owner_last_name' => "O'Brien", 'feeding_schedule' => "1\\2 cup, 50%Beef\nLoves treats <3" ) ), $today );
t( 'apostrophe stored as typed', "O'Brien" === $typed['columns']['owner_last_name'] );
t( 'backslash, percent-hex and < kept as typed', "1\\2 cup, 50%Beef\nLoves treats <3" === $typed['details']['pets'][0]['feeding_schedule'] );
t( 'control characters removed', 'Biscuit' === Validator::validate( paw_with( $good, array( 'pet_name' => "Bis\x00cu\x07it" ) ), $today )['columns']['pet_name'] );
t( 'line breaks folded in one-line fields', 'A B' === Validator::validate( paw_with( $good, array( 'pet_breed' => "A\r\nB" ) ), $today )['details']['pets'][0]['pet_breed'] );
t( 'invalid UTF-8 rejected as empty', only_error( paw_with( $good, array( 'pet_name' => "\xC3\x28" ) ), 'pets.0.pet_name', 'This field is required.' ) );
t( '60 accented characters fit', null === errors( paw_with( $good, array( 'owner_first_name' => str_repeat( 'ë', 60 ) ) ) ) );
t( '61 characters are too many', only_error( paw_with( $good, array( 'owner_first_name' => str_repeat( 'ë', 61 ) ) ), 'owner_first_name', 'Please keep this under 60 characters.' ) );
t( 'markup kept as text (escaped when shown)', '<b>Biscuit</b>' === Validator::validate( paw_with( $good, array( 'pet_name' => '<b>Biscuit</b>' ) ), $today )['columns']['pet_name'] );
t( 'arrays are not accepted as text', only_error( paw_with( $good, array( 'pet_name' => array( 'x' ) ) ), 'pets.0.pet_name', 'This field is required.' ) );
t( 'textarea keeps line breaks', "a\nb" === Validator::validate( paw_with( $good, array( 'feeding_schedule' => "a\nb" ) ), $today )['details']['pets'][0]['feeding_schedule'] );

// Booking type, dog size and grooming.
$groom = paw_with( $good, array( 'booking_type' => 'grooming', 'dog_size' => 'small', 'service' => 'basic_bath', 'grooming_frequency' => 'once', 'appointment_date' => '2026-10-26', 'appointment_time' => 'morning', 'check_in' => '', 'check_out' => '' ) );
$g     = Validator::validate( $groom, $today );
t( 'grooming only: valid without stay dates', is_array( $g ) );
t( 'grooming only: the appointment fills check-in and check-out', is_array( $g ) && '2026-10-26' === $g['columns']['check_in'] && '2026-10-26' === $g['columns']['check_out'] && '2026-10-26' === $g['columns']['appointment_date'] );
t( 'grooming only: service and type in columns', is_array( $g ) && 'basic_bath' === $g['columns']['service'] && 'grooming' === $g['columns']['booking_type'] );
t( 'boarding: no grooming columns', '' === $ok['columns']['service'] && '' === $ok['columns']['appointment_date'] && 'boarding' === $ok['columns']['booking_type'] );
t( 'grooming day in the past', only_error( paw_with( $groom, array( 'appointment_date' => '2026-10-24' ) ), 'appointment_date', "The grooming day can't be in the past." ) );
t( 'grooming needs a service', only_error( paw_with( $groom, array( 'service' => '' ) ), 'pets.0.service', 'This field is required.' ) );
t( 'unknown service', only_error( paw_with( $groom, array( 'service' => 'teeth' ) ), 'pets.0.service', 'Please choose one of the services.' ) );
t( 'grooming needs a drop-off time', only_error( paw_with( $groom, array( 'appointment_time' => '' ) ), 'appointment_time', 'This field is required.' ) );
t( 'cats cannot book grooming', only_error( paw_with( $groom, array( 'pet_type' => 'cat' ) ), 'booking_type', 'Grooming is for dogs only. Choose Boarding + grooming to board your other pets.' ) );
t( 'cats board without a size', null === errors( paw_with( $good, array( 'pet_type' => 'cat', 'dog_size' => '' ) ) ) );
t( 'dogs need a size', only_error( paw_with( $good, array( 'dog_size' => '' ) ), 'pets.0.dog_size', 'This field is required.' ) );
t( 'booking type is required', only_error( paw_with( $good, array( 'booking_type' => '' ) ), 'booking_type', 'This field is required.' ) );
$both = paw_with( $good, array( 'booking_type' => 'both', 'service' => 'nail_trim', 'grooming_frequency' => 'once', 'appointment_date' => '2026-10-29' ) );
t( 'boarding + grooming: valid with the grooming day inside the stay', null === errors( $both ) );
t( 'boarding + grooming: check-in day and check-out day count as inside', null === errors( paw_with( $both, array( 'appointment_date' => '2026-10-28' ) ) ) && null === errors( paw_with( $both, array( 'appointment_date' => '2026-10-30' ) ) ) );
t( 'boarding + grooming: grooming day after check-out', only_error( paw_with( $both, array( 'appointment_date' => '2026-10-31' ) ), 'appointment_date', 'The grooming day must fall within the stay.' ) );
t( 'boarding + grooming: no drop-off time asked', null === errors( paw_with( $both, array( 'appointment_time' => '' ) ) ) );
$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'services' => array( 'basic_bath' => array( 'enabled' => false ) ) );
t( 'a turned-off service is refused', only_error( $groom, 'pets.0.service', 'Please choose one of the services.' ) );
unset( $GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] );

// Several pets.
$tom  = array( 'pet_name' => 'Tom', 'pet_type' => 'cat', 'dog_size' => '', 'pet_breed' => 'Tabby', 'pet_age' => '2', 'pet_gender' => 'male', 'pet_weight' => '9 lbs', 'pet_spayed' => 'yes', 'pet_microchipped' => 'yes', 'vaccinations_current' => 'yes', 'medical_conditions' => 'no', 'medications' => 'no', 'allergies' => 'no', 'feeding_schedule' => 'Once', 'food_preference' => 'own' );
$two  = $good;
$two['pets'][0]['pet_name'] = 'Rex';
$two['pets'][1] = $tom;
$t2 = Validator::validate( $two, $today );
t( 'two pets: valid, names joined, counted', is_array( $t2 ) && 'Rex + Tom' === $t2['columns']['pet_name'] && '2' === (string) $t2['columns']['pet_count'] && 'dog' === $t2['columns']['pet_type'] );
t( 'two pets: answers kept per pet', is_array( $t2 ) && 'Tabby' === $t2['details']['pets'][1]['pet_breed'] && 2 === count( $t2['details']['pets'] ) );
$six = $good;
$six['pets'] = array_fill( 0, 6, $good['pets'][0] );
t( 'six pets are refused', isset( errors( $six )['pets'] ) );
$none = $good;
$none['pets'] = array();
t( 'no pets are refused', isset( errors( $none )['pets'] ) );
t( 'an error on the second pet is keyed to it', only_error( paw_with( $two, array( 'pet_breed' => '' ), 1 ), 'pets.1.pet_breed', 'This field is required.' ) );
$g2 = paw_with( $two, array( 'booking_type' => 'grooming', 'appointment_date' => '2026-10-26', 'appointment_time' => 'morning', 'check_in' => '', 'check_out' => '', 'service' => 'nail_trim', 'grooming_frequency' => 'once' ) );
t( 'grooming with a cat is refused', only_error( $g2, 'booking_type', 'Grooming is for dogs only. Choose Boarding + grooming to board your other pets.' ) );
$b2 = paw_with( $two, array( 'booking_type' => 'both', 'appointment_date' => '2026-10-29', 'service' => 'nail_trim', 'grooming_frequency' => 'once' ) );
$b2v = Validator::validate( $b2, $today );
t( 'both: a groomed dog and a boarding cat are fine', is_array( $b2v ) && 'nail_trim' === $b2v['columns']['service'] );
t( 'both: nobody groomed is refused', only_error( paw_with( $b2, array( 'service' => 'none' ) ), 'booking_type', 'Choose a service for at least one pet, or book Boarding only.' ) );
$g1 = paw_with( $g2, array( 'pets' => array( $g2['pets'][0] ) ) );
t( 'none is only for boarding + grooming', only_error( paw_with( $g1, array( 'service' => 'none' ) ), 'pets.0.service', 'Please choose one of the services.' ) );
$dogs = paw_with( paw_with( $b2, array( 'pet_type' => 'dog', 'dog_size' => 'small', 'service' => 'basic_bath', 'grooming_frequency' => 'once' ), 1 ), array() );
t( 'two groomed dogs: services joined', 'nail_trim,basic_bath' === Validator::validate( $dogs, $today )['columns']['service'] );
$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'groomers' => array( 'Ana' ) );
t( 'a listed groomer is fine', null === errors( paw_with( $b2, array( 'groomer' => 'Ana' ) ) ) );
t( 'an unknown groomer is refused', only_error( paw_with( $b2, array( 'groomer' => 'Zed' ) ), 'pets.0.groomer', 'Please choose one of the options.' ) );
unset( $GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] );
t( 'a repeat schedule needs a day and time', errors( paw_with( $b2, array( 'grooming_frequency' => '4' ) ) ) === array( 'pets.0.preferred_day' => 'This field is required.', 'pets.0.preferred_time' => 'This field is required.' ) );
t( 'one time only needs no day', null === errors( paw_with( $b2, array( 'grooming_frequency' => 'once' ) ) ) );
t( 'last groom date cannot be in the future', only_error( paw_with( $b2, array( 'last_groom_date' => '2026-10-26' ) ), 'pets.0.last_groom_date', "The last groom date can't be in the future." ) );
$long = $two;
$long['pets'][0]['pet_name'] = str_repeat( 'Maximilian ', 4 ) . 'X';
$long['pets'][0]['pet_name'] = mb_substr( $long['pets'][0]['pet_name'], 0, 45 );
$long['pets'][1]['pet_name'] = str_repeat( 'é', 40 );
$ln = Validator::validate( $long, $today )['columns']['pet_name'];
t( 'a long joined name is cut to 60 characters with …', 60 === mb_strlen( $ln ) && str_ends_with( $ln, '…' ) );

// Grooming skips care and the emergency contact.
$short = paw_with( $groom, array( 'emergency_name' => '', 'emergency_phone' => '', 'emergency_relationship' => '', 'feeding_schedule' => '', 'food_preference' => '' ) );
$sv    = Validator::validate( $short, $today );
t( 'grooming: valid without care or emergency-contact answers', is_array( $sv ) && ! isset( $sv['details']['pets'][0]['feeding_schedule'] ) && ! isset( $sv['details']['emergency_name'] ) && 'Dr. Who' === $sv['details']['vet_name'] );

done();
