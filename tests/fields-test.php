<?php
/**
 * The field list is internally consistent: every rule the form and Validator rely on holds.
 * Run: php tests/fields-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Fields;

$types = array( 'text', 'email', 'tel', 'textarea', 'choice', 'yesno', 'date', 'country', 'file', 'signature', 'agree', 'service' );
$steps = Fields::steps();
$all   = Fields::all();

t( 'seven steps', count( $steps ) === 7 );
t( 'step ids in order', array_column( $steps, 'id' ) === array( 'owner', 'pet', 'stay', 'health', 'care', 'emergency', 'waiver' ) );

$ids = array();
foreach ( $steps as $step ) {
	$seen = array();
	foreach ( $step['fields'] as $f ) {
		$ids[] = $f['id'];
		t( "{$f['id']}: known type", in_array( $f['type'], $types, true ) );
		t( "{$f['id']}: has a label", '' !== $f['label'] );
		if ( 'choice' === $f['type'] ) {
			t( "{$f['id']}: at least two options", count( $f['options'] ) >= 2 || 'groomer' === $f['id'] ); // Groomers come from Settings.
		}
		foreach ( array_keys( $f['show_if'] ?? array() ) as $target ) {
			t( "{$f['id']}: show_if target $target comes before it", in_array( $target, $seen, true ) || in_array( $target, $ids, true ) || 'booking_type' === $target );
		}
		$seen[] = $f['id'];
	}
}
t( 'ids are unique', count( $ids ) === count( array_unique( $ids ) ) );
t( 'all() is keyed by every id, welcome first', array_keys( $all ) === array_merge( array( 'booking_type' ), $ids ) );
foreach ( Fields::COLUMNS as $col ) {
	t( "column field $col exists", isset( $all[ $col ] ) || 'pet_count' === $col ); // pet_count is counted, not asked.
}

t( 'city is optional', false === $all['address_city']['required'] );
t( 'street is required', true === $all['address_street']['required'] );
t( 'country list has US', 'United States' === Fields::countries()['US'] );
t( 'medications details are conditional', array( 'medications' => 'yes' ) === $all['medications_details']['show_if'] );
t( 'pet type other is conditional', array( 'pet_type' => 'other' ) === $all['pet_type_other']['show_if'] );
t( 'all() records the 1-based step', 1 === $all['owner_first_name']['step'] && 7 === $all['signature']['step'] );
t( 'pet type options', array( 'dog', 'cat', 'other' ) === array_keys( $all['pet_type']['options'] ) );
t( 'food preference options', array( 'own', 'facility' ) === array_keys( $all['food_preference']['options'] ) );
t( 'textareas allow 2000 characters', 2000 === $all['feeding_schedule']['max'] );
t( 'email allows 100 characters', 100 === $all['owner_email']['max'] );

// Grooming.
t( 'dog size right after pet type, dogs only', 'pet' === $steps[1]['id'] && array( 'pet_type' => 'dog' ) === $all['dog_size']['show_if'] && array( 'small', 'large' ) === array_keys( $all['dog_size']['options'] ) && 'Under 25 lbs' === $all['dog_size']['options']['small'] );
$stay = array_column( $steps[2]['fields'], 'id' );
t( 'stay step: dates, grooming day and time, then each pet\'s grooming choices', array( 'check_in', 'check_out', 'appointment_date', 'appointment_time', 'service', 'groomer', 'grooming_frequency', 'preferred_day', 'preferred_time', 'last_groom_date', 'grooming_notes', 'special_requests' ) === $stay );
t( 'booking type: boarding preselected', 'boarding' === $all['booking_type']['default'] && array( 'boarding', 'grooming', 'both' ) === array_keys( $all['booking_type']['options'] ) );
t( 'stay dates only for boarding', array( 'booking_type' => array( 'boarding', 'both' ) ) === $all['check_in']['show_if'] );
t( 'grooming day for grooming and both; drop-off time for grooming only', array( 'booking_type' => array( 'grooming', 'both' ) ) === $all['appointment_date']['show_if'] && array( 'booking_type' => 'grooming' ) === $all['appointment_time']['show_if'] );
t( 'service field', 'service' === $all['service']['type'] && $all['service']['per_pet'] );
t( 'new columns', array( 'booking_type', 'appointment_date', 'service' ) === array_values( array_intersect( Fields::COLUMNS, array( 'booking_type', 'appointment_date', 'service' ) ) ) );

// v0.4: welcome field, per-pet fields, groomers.
$GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] = array( 'groomers' => array( 'Ana', 'Ben' ) );
$all = Fields::all();
t( 'booking type lives on the welcome screen', 'booking_type' === Fields::welcome()[0]['id'] && 0 === $all['booking_type']['step'] );
$pf = Fields::pet_fields();
t( 'per-pet fields', isset( $pf['pet_name'], $pf['dog_size'], $pf['service'], $pf['groomer'], $pf['vaccination_files'], $pf['feeding_schedule'] ) && ! isset( $pf['check_in'] ) && ! isset( $pf['owner_email'] ) );
t( 'groomer: Any / Flexible first, then the names in order', array( 'any' => 'Any / Flexible', 'Ana' => 'Ana', 'Ben' => 'Ben' ) === $all['groomer']['options'] && 'any' === $all['groomer']['default'] );
t( 'preferred day only for a repeat schedule', array( 'grooming_frequency' => array( '2', '4', '6', '8' ) ) === $all['preferred_day']['show_if'] && $all['preferred_time']['show_if'] === $all['preferred_day']['show_if'] );
t( 'frequency options', array( 'once', '2', '4', '6', '8' ) === array_map( 'strval', array_keys( $all['grooming_frequency']['options'] ) ) );
t( 'grooming extras shown only with a real service', array( 'service' => array_keys( \Pawfectly\Services::enabled() ) ) === $all['groomer']['show_if'] );
t( 'last groom date and notes optional', false === $all['last_groom_date']['required'] && false === $all['grooming_notes']['required'] );
t( 'service shown for dogs in grooming bookings', array( 'booking_type' => array( 'grooming', 'both' ), 'pet_type' => 'dog' ) === $all['service']['show_if'] );
t( 'contact consent is optional', 'agree' === $all['contact_consent']['type'] && false === $all['contact_consent']['required'] && 'waiver' === Fields::steps()[ $all['contact_consent']['step'] - 1 ]['id'] );
t( 'five pets at most', 5 === Fields::MAX_PETS );
t( 'pet count is a column', in_array( 'pet_count', Fields::COLUMNS, true ) );
unset( $GLOBALS['paw_options'][ \Pawfectly\Settings::OPTION ] );

done();
