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
			t( "{$f['id']}: at least two options", count( $f['options'] ) >= 2 );
		}
		foreach ( array_keys( $f['show_if'] ?? array() ) as $target ) {
			t( "{$f['id']}: show_if target $target is earlier in the step", in_array( $target, $seen, true ) );
		}
		$seen[] = $f['id'];
	}
}
t( 'ids are unique', count( $ids ) === count( array_unique( $ids ) ) );
t( 'all() is keyed by every id', array_keys( $all ) === $ids );
foreach ( Fields::COLUMNS as $col ) {
	t( "column field $col exists", isset( $all[ $col ] ) );
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
t( 'stay step: booking type first, then dates, grooming day, time, service', array( 'booking_type', 'check_in', 'check_out', 'appointment_date', 'appointment_time', 'service', 'special_requests' ) === $stay );
t( 'booking type: boarding preselected, grooming options for dogs only', 'boarding' === $all['booking_type']['default'] && array( 'grooming', 'both' ) === $all['booking_type']['dog_only'] && array( 'boarding', 'grooming', 'both' ) === array_keys( $all['booking_type']['options'] ) );
t( 'stay dates only for boarding', array( 'booking_type' => array( 'boarding', 'both' ) ) === $all['check_in']['show_if'] );
t( 'grooming day for grooming and both; drop-off time for grooming only', array( 'booking_type' => array( 'grooming', 'both' ) ) === $all['appointment_date']['show_if'] && array( 'booking_type' => 'grooming' ) === $all['appointment_time']['show_if'] );
t( 'service field', 'service' === $all['service']['type'] && array( 'booking_type' => array( 'grooming', 'both' ) ) === $all['service']['show_if'] );
t( 'new columns', array( 'booking_type', 'appointment_date', 'service' ) === array_values( array_intersect( Fields::COLUMNS, array( 'booking_type', 'appointment_date', 'service' ) ) ) );

done();
