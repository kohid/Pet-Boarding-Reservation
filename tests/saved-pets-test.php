<?php
/**
 * My Profile's saved pets and details for bookings: the booking form's rules, seeding from the last
 * booking, remembering pets from new bookings, and the booking form's prefill.
 * Run: php tests/saved-pets-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

use Pawfectly\Accounts;
use Pawfectly\Reservations;
use Pawfectly\SavedPets;

/** A booking for this owner with these pets (and its address, emergency contact and vet). */
function book( int $user_id, string $email, array $pets, array $more = array() ): array {
	$row = Reservations::build_row(
		array(
			'columns' => array( 'owner_first_name' => 'Zoe', 'owner_last_name' => 'O', 'owner_email' => $email, 'owner_phone' => '555-0100', 'pet_name' => $pets[0]['pet_name'], 'pet_type' => 'dog', 'check_in' => '2030-01-10', 'check_out' => '2030-01-12' ),
			'details' => array_merge( array( 'pets' => $pets, 'address_street' => '1 Bark St', 'address_city' => 'Estero', 'address_country' => 'US', 'emergency_name' => 'Amy', 'emergency_phone' => '239-555-0101', 'vet_name' => 'Dr. Paws', 'vet_authorization' => 'yes', 'special_requests' => 'Not saved' ), $more ),
		),
		'PAW-' . strtoupper( substr( md5( (string) microtime( true ) . wp_rand() ), 0, 6 ) ),
		array(),
		'',
		'',
		'2026-10-25 10:00:00'
	);
	$row['user_id'] = $user_id;
	return Reservations::find( Reservations::insert( $row ) );
}
function wp_rand( $a = 0, $b = 0 ) { return mt_rand(); }

$zoe = paw_test_user( 'zoe@example.com', 'some pass1', array( Accounts::ROLE ) );

// What is saved per pet: the booking form's pet, health and care questions, never files or grooming choices.
$fields = array_keys( SavedPets::pet_fields() );
t( 'pet fields: the pet, health and care questions', in_array( 'pet_name', $fields, true ) && in_array( 'medications_details', $fields, true ) && in_array( 'feeding_schedule', $fields, true ) && ! in_array( 'vaccination_files', $fields, true ) && ! in_array( 'service', $fields, true ) && ! in_array( 'grooming_notes', $fields, true ) && ! in_array( 'vaccinations_current', $fields, true ) );
t( 'pet fields come grouped as the booking form shows them', array( 'pet', 'health', 'care' ) === array_keys( SavedPets::pet_groups() ) );
t( 'details for bookings: address, emergency contact and vet', array( 'address_street', 'address_city', 'address_state', 'address_postcode', 'address_country', 'emergency_name', 'emergency_phone', 'emergency_relationship', 'vet_name', 'vet_clinic', 'vet_phone', 'vet_authorization' ) === array_keys( SavedPets::info_fields() ) );

// Checks.
[ $clean, $errors ] = SavedPets::sanitize( array( array( 'values' => array( 'pet_name' => '  Rex ', 'pet_type' => 'dog', 'dog_size' => 'small', 'pet_type_other' => 'Lizard', 'medications' => 'no', 'medications_details' => 'Hidden', 'bogus' => 'x' ) ) ) );
t( 'a pet: trimmed, unknown fields and hidden answers dropped', array() === $errors && array( 'pet_name' => 'Rex', 'pet_type' => 'dog', 'dog_size' => 'small', 'medications' => 'no' ) === $clean[0]['values'] && 1 === preg_match( '/^[a-f0-9]{12}$/', $clean[0]['uid'] ) );
[ , $errors ] = SavedPets::sanitize( array( array( 'values' => array( 'pet_type' => 'dog' ) ), array( 'values' => array( 'pet_name' => 'Ivy', 'pet_type' => 'dragon' ) ) ) );
t( 'the name is required; choices must be one of the options; errors keyed per pet', isset( $errors['pets.0.pet_name'], $errors['pets.1.pet_type'] ) && 2 === count( $errors ) );
[ $clean ] = SavedPets::sanitize( array( array( 'uid' => 'abcdef012345', 'values' => array( 'pet_name' => 'Rex' ) ), array( 'uid' => '<script>', 'values' => array( 'pet_name' => 'Ivy' ) ) ) );
t( 'a valid uid is kept, anything else replaced', 'abcdef012345' === $clean[0]['uid'] && '<script>' !== $clean[1]['uid'] );
[ , $errors ] = SavedPets::sanitize( array_fill( 0, 11, array( 'values' => array( 'pet_name' => 'Rex' ) ) ) );
t( 'at most 10 pets', isset( $errors['pets'] ) );
[ , $errors ] = SavedPets::sanitize( array( array( 'values' => array( 'pet_name' => str_repeat( 'x', 200 ) ) ) ) );
t( 'the booking form\'s length limits', isset( $errors['pets.0.pet_name'] ) );
[ $info, $errors ] = SavedPets::sanitize_info( array( 'address_city' => 'Estero', 'vet_phone' => 'call me', 'vet_authorization' => 'maybe', 'pet_name' => 'Rex' ) );
t( 'details: checked like the booking form, other fields ignored', isset( $errors['vet_phone'], $errors['vet_authorization'] ) && ! isset( $info['pet_name'] ) && 'Estero' === $info['address_city'] );

// Nothing saved yet: the last booking's pets and details are shown (not saved).
t( 'no bookings, nothing saved: empty', array() === SavedPets::get( $zoe->ID ) && array() === SavedPets::info( $zoe->ID ) );
$first = book( $zoe->ID, 'zoe@example.com', array( array( 'pet_name' => 'Rex', 'pet_type' => 'dog', 'dog_size' => 'large', 'feeding_schedule' => 'Twice a day', 'service' => 'bath' ), array( 'pet_name' => 'Mingming', 'pet_type' => 'cat' ) ) );
$seed  = SavedPets::get( $zoe->ID );
t( 'seeded from the last booking: its pets, without booking-only answers', 2 === count( $seed ) && 'Rex' === $seed[0]['values']['pet_name'] && 'Twice a day' === $seed[0]['values']['feeding_schedule'] && ! isset( $seed[0]['values']['service'] ) && 'Mingming' === $seed[1]['values']['pet_name'] );
t( 'and its details for bookings', 'Estero' === SavedPets::info( $zoe->ID )['address_city'] && 'Dr. Paws' === SavedPets::info( $zoe->ID )['vet_name'] && ! isset( SavedPets::info( $zoe->ID )['special_requests'] ) );
t( 'seeding saves nothing', '' === get_user_meta( $zoe->ID, SavedPets::META_PETS, true ) );

// Saved, then a new booking: known pets updated by name, new ones added.
[ $clean ] = SavedPets::sanitize( $seed );
SavedPets::save( $zoe->ID, $clean );
$uid = SavedPets::get( $zoe->ID )[0]['uid'];
$next = book( $zoe->ID, 'zoe@example.com', array( array( 'pet_name' => 'rex', 'pet_type' => 'dog', 'dog_size' => 'large', 'pet_weight' => '31 lbs' ), array( 'pet_name' => 'Brax', 'pet_type' => 'dog', 'dog_size' => 'small' ) ), array( 'address_city' => 'Naples' ) );
SavedPets::remember( $zoe->ID, $next );
$pets = SavedPets::get( $zoe->ID );
t( 'remember: a known name (any case) takes the new answers and keeps the old ones', 3 === count( $pets ) && $uid === $pets[0]['uid'] && '31 lbs' === $pets[0]['values']['pet_weight'] && 'Twice a day' === $pets[0]['values']['feeding_schedule'] );
t( 'remember: a new name is added', 'Brax' === $pets[2]['values']['pet_name'] );
t( 'remember: details for bookings updated', 'Naples' === SavedPets::info( $zoe->ID )['address_city'] && 'Dr. Paws' === SavedPets::info( $zoe->ID )['vet_name'] );
SavedPets::remember( 0, $next );
t( 'remember: nobody signed in, nothing kept', true );

// The booking form's prefill uses the saved pets and details.
SavedPets::save_info( $zoe->ID, array( 'address_city' => 'Fort Myers', 'emergency_name' => 'Bo' ) );
$pre = Accounts::prefill( $zoe->ID );
t( 'prefill: the saved details win over the last booking', 'Fort Myers' === $pre['address_city'] && 'Bo' === $pre['emergency_name'] );
t( 'prefill: the pets that came last time', array( 'Rex', 'Brax' ) === array_column( $pre['pets'], 'pet_name' ) );
t( 'prefill: every saved pet to choose from, with its answers and whether it came last time', array( 'Rex', 'Mingming', 'Brax' ) === array_column( $pre['saved_pets'], 'name' ) && array( true, false, true ) === array_column( $pre['saved_pets'], 'last' ) && 'cat' === $pre['saved_pets'][1]['values']['pet_type'] );
$amy = paw_test_user( 'amy@example.com', 'some pass1', array( Accounts::ROLE ) );
SavedPets::save( $amy->ID, array( array( 'uid' => 'aaaaaaaaaaaa', 'values' => array( 'pet_name' => 'Luna', 'pet_type' => 'dog' ) ) ) );
$pre = Accounts::prefill( $amy->ID );
t( 'prefill: no booking yet, every saved pet comes', array( 'Luna' ) === array_column( $pre['pets'], 'pet_name' ) && array( true ) === array_column( $pre['saved_pets'], 'last' ) );

done();
