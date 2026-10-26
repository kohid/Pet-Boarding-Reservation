<?php
/**
 * The one list of reservation fields. The form template, the browser rules and
 * the server Validator all read from here, so a field is defined exactly once.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Fields {

	/** Field ids stored in their own table columns; everything else goes in `details`. */
	public const COLUMNS = array( 'owner_first_name', 'owner_last_name', 'owner_email', 'owner_phone', 'pet_name', 'pet_type', 'pet_count', 'booking_type', 'check_in', 'check_out', 'appointment_date', 'service' );

	/** Pets one booking can hold. */
	public const MAX_PETS = 5;

	/** Steps whose every field is asked once per pet. */
	private const PET_STEPS = array( 'pet', 'health', 'care' );

	private const SHORT = 60;
	private const LONG  = 2000;

	/**
	 * Ordered steps, each with its fields.
	 *
	 * @return array<int, array{id: string, title: string, intro: string, fields: array<int, array>}>
	 */
	public static function steps(): array {
		$yes_no = array(
			'yes' => __( 'Yes', 'pawfectly-reservations' ),
			'no'  => __( 'No', 'pawfectly-reservations' ),
		);
		// Grooming extras show once a pet has a real service; the day and time only for a repeat schedule.
		$groomed = array( 'service' => array_keys( Services::enabled() ) );
		$repeats = array( 'grooming_frequency' => array( '2', '4', '6', '8' ) );
		// Asked only when the pet stays overnight.
		$stays = array( 'booking_type' => array( 'boarding', 'both' ) );

		$steps = array(
			array(
				'id'     => 'owner',
				'title'  => __( 'Owner Information', 'pawfectly-reservations' ),
				'intro'  => '',
				'fields' => array(
					self::f( 'owner_first_name', 'text', __( 'First Name', 'pawfectly-reservations' ) ),
					self::f( 'owner_last_name', 'text', __( 'Last Name', 'pawfectly-reservations' ) ),
					self::f( 'owner_email', 'email', __( 'Email Address', 'pawfectly-reservations' ), array( 'max' => 100 ) ),
					self::f( 'owner_phone', 'tel', __( 'Phone Number', 'pawfectly-reservations' ), array( 'max' => 30 ) ),
					self::f( 'address_street', 'text', __( 'Street', 'pawfectly-reservations' ), array( 'max' => 120, 'half' => true, 'group' => __( 'Home Address', 'pawfectly-reservations' ) ) ),
					self::f( 'address_city', 'text', __( 'City/Suburb', 'pawfectly-reservations' ), array( 'required' => false, 'half' => true ) ),
					self::f( 'address_state', 'text', __( 'State', 'pawfectly-reservations' ), array( 'half' => true ) ),
					self::f( 'address_postcode', 'text', __( 'Zip/Post Code', 'pawfectly-reservations' ), array( 'max' => 20, 'half' => true ) ),
					self::f( 'address_country', 'country', __( 'Country', 'pawfectly-reservations' ), array( 'max' => 2 ) ),
				),
			),
			array(
				'id'     => 'pet',
				'title'  => __( 'Pet Information', 'pawfectly-reservations' ),
				'intro'  => '',
				'fields' => array(
					self::f( 'pet_name', 'text', __( "Pet's Name", 'pawfectly-reservations' ) ),
					self::f(
						'pet_type',
						'choice',
						__( 'Pet Type', 'pawfectly-reservations' ),
						array(
							'options' => array(
								'dog'   => __( 'Dog', 'pawfectly-reservations' ),
								'cat'   => __( 'Cat', 'pawfectly-reservations' ),
								'other' => __( 'Other', 'pawfectly-reservations' ),
							),
						)
					),
					self::f(
						'dog_size',
						'choice',
						__( 'Dog size', 'pawfectly-reservations' ),
						array(
							'options' => array(
								'small' => __( 'Under 25 lbs', 'pawfectly-reservations' ),
								'large' => __( '25 lbs & up', 'pawfectly-reservations' ),
							),
							'show_if' => array( 'pet_type' => 'dog' ),
							'help'    => __( 'Grooming prices depend on size.', 'pawfectly-reservations' ),
						)
					),
					self::f( 'pet_type_other', 'text', __( 'Please specify', 'pawfectly-reservations' ), array( 'show_if' => array( 'pet_type' => 'other' ) ) ),
					self::f( 'pet_breed', 'text', __( 'Breed', 'pawfectly-reservations' ) ),
					self::f( 'pet_age', 'text', __( 'Age', 'pawfectly-reservations' ), array( 'placeholder' => __( 'e.g., 3 years', 'pawfectly-reservations' ) ) ),
					self::f(
						'pet_gender',
						'choice',
						__( 'Gender', 'pawfectly-reservations' ),
						array(
							'options' => array(
								'male'   => __( 'Male', 'pawfectly-reservations' ),
								'female' => __( 'Female', 'pawfectly-reservations' ),
							),
						)
					),
					self::f( 'pet_weight', 'text', __( 'Weight', 'pawfectly-reservations' ), array( 'placeholder' => __( 'e.g., 25 lbs', 'pawfectly-reservations' ) ) ),
					self::f( 'pet_spayed', 'yesno', __( 'Is your pet spayed/neutered?', 'pawfectly-reservations' ), array( 'options' => $yes_no ) ),
					self::f( 'pet_microchipped', 'yesno', __( 'Is your pet microchipped?', 'pawfectly-reservations' ), array( 'options' => $yes_no ) ),
				),
			),
			array(
				'id'     => 'stay',
				'title'  => __( 'Reservation Details', 'pawfectly-reservations' ),
				'intro'  => '',
				'fields' => array(
					self::f( 'check_in', 'date', __( 'Check-In Date', 'pawfectly-reservations' ), array( 'max' => 10, 'show_if' => array( 'booking_type' => array( 'boarding', 'both' ) ) ) ),
					self::f( 'check_out', 'date', __( 'Check-Out Date', 'pawfectly-reservations' ), array( 'max' => 10, 'show_if' => array( 'booking_type' => array( 'boarding', 'both' ) ) ) ),
					self::f( 'appointment_date', 'date', __( 'Grooming day', 'pawfectly-reservations' ), array( 'max' => 10, 'show_if' => array( 'booking_type' => array( 'grooming', 'both' ) ) ) ),
					self::f(
						'appointment_time',
						'choice',
						__( 'Preferred drop-off time', 'pawfectly-reservations' ),
						array(
							'options' => array(
								'morning'   => __( 'Morning (8–12)', 'pawfectly-reservations' ),
								'afternoon' => __( 'Afternoon (12–5)', 'pawfectly-reservations' ),
							),
							'show_if' => array( 'booking_type' => 'grooming' ),
						)
					),
					self::f( 'service', 'service', __( 'Service', 'pawfectly-reservations' ), array( 'max' => 40, 'per_pet' => true, 'show_if' => array( 'booking_type' => array( 'grooming', 'both' ), 'pet_type' => Services::GROOMED ) ) ),
					self::f( 'groomer', 'choice', __( 'Preferred groomer', 'pawfectly-reservations' ), array( 'per_pet' => true, 'options' => array( 'any' => __( 'Any / Flexible', 'pawfectly-reservations' ) ) + array_combine( Services::groomers(), Services::groomers() ), 'default' => 'any', 'show_if' => $groomed ) ),
					self::f(
						'grooming_frequency',
						'choice',
						__( 'Weeks between grooms', 'pawfectly-reservations' ),
						array(
							'per_pet' => true,
							'options' => array(
								'once' => __( 'One time only', 'pawfectly-reservations' ),
								'2'    => __( 'Every 2 weeks', 'pawfectly-reservations' ),
								'4'    => __( 'Every 4 weeks', 'pawfectly-reservations' ),
								'6'    => __( 'Every 6 weeks', 'pawfectly-reservations' ),
								'8'    => __( 'Every 8 weeks', 'pawfectly-reservations' ),
							),
							'show_if' => $groomed,
						)
					),
					self::f(
						'preferred_day',
						'choice',
						__( 'Preferred day', 'pawfectly-reservations' ),
						array(
							'per_pet' => true,
							'options' => array(
								'mon' => __( 'Monday', 'pawfectly-reservations' ),
								'tue' => __( 'Tuesday', 'pawfectly-reservations' ),
								'wed' => __( 'Wednesday', 'pawfectly-reservations' ),
								'thu' => __( 'Thursday', 'pawfectly-reservations' ),
								'fri' => __( 'Friday', 'pawfectly-reservations' ),
								'sat' => __( 'Saturday', 'pawfectly-reservations' ),
							),
							'show_if' => $repeats,
							'half'    => true,
						)
					),
					self::f(
						'preferred_time',
						'choice',
						__( 'Preferred time', 'pawfectly-reservations' ),
						array(
							'per_pet' => true,
							'options' => array(
								'morning'   => __( 'Morning', 'pawfectly-reservations' ),
								'afternoon' => __( 'Afternoon', 'pawfectly-reservations' ),
							),
							'show_if' => $repeats,
							'half'    => true,
						)
					),
					self::f( 'last_groom_date', 'date', __( 'Last groom date', 'pawfectly-reservations' ), array( 'per_pet' => true, 'required' => false, 'max' => 10, 'show_if' => $groomed, 'past' => true ) ),
					self::f( 'grooming_notes', 'textarea', __( 'Grooming notes', 'pawfectly-reservations' ), array( 'per_pet' => true, 'required' => false, 'show_if' => $groomed, 'placeholder' => __( 'Cut style, sensitivities, handling notes, same groomer if possible…', 'pawfectly-reservations' ) ) ),
					self::f( 'special_requests', 'textarea', __( 'Special Requests or Notes', 'pawfectly-reservations' ), array( 'required' => false, 'help' => __( "Any specific preferences for your pet's stay?", 'pawfectly-reservations' ) ) ),
				),
			),
			array(
				'id'     => 'health',
				'title'  => __( 'Health & Vaccination Records', 'pawfectly-reservations' ),
				'intro'  => '',
				'fields' => array(
					self::f(
						'vaccinations_current',
						'yesno',
						__( 'Are all vaccinations up to date?', 'pawfectly-reservations' ),
						array(
							'options' => $yes_no,
							'help'    => __( 'All pets must have current vaccinations to board with us', 'pawfectly-reservations' ),
							'warn'    => array( 'no' => __( "We're sorry, all pets must have current vaccinations to board with us. Please contact us once vaccinations are updated.", 'pawfectly-reservations' ) ),
						)
					),
					self::f( 'vaccination_files', 'file', __( 'Upload Vaccination Records', 'pawfectly-reservations' ), array( 'help' => __( 'Please upload proof of current vaccinations (PDF or image files)', 'pawfectly-reservations' ) ) ),
					self::f( 'medical_conditions', 'yesno', __( 'Does your pet have any medical conditions?', 'pawfectly-reservations' ), array( 'options' => $yes_no ) ),
					self::f( 'medical_conditions_details', 'textarea', __( 'Please describe the medical conditions', 'pawfectly-reservations' ), array( 'show_if' => array( 'medical_conditions' => 'yes' ) ) ),
					self::f( 'medications', 'yesno', __( 'Is your pet currently taking any medications?', 'pawfectly-reservations' ), array( 'options' => $yes_no ) ),
					self::f( 'medications_details', 'textarea', __( 'Please list the medications', 'pawfectly-reservations' ), array( 'show_if' => array( 'medications' => 'yes' ), 'help' => __( 'Name, dose and schedule', 'pawfectly-reservations' ) ) ),
					self::f( 'allergies', 'yesno', __( 'Does your pet have any allergies?', 'pawfectly-reservations' ), array( 'options' => $yes_no ) ),
					self::f( 'allergies_details', 'textarea', __( 'Please describe the allergies', 'pawfectly-reservations' ), array( 'show_if' => array( 'allergies' => 'yes' ) ) ),
				),
			),
			array(
				'id'     => 'care',
				'title'  => __( 'Special Care Instructions', 'pawfectly-reservations' ),
				'intro'  => '',
				'fields' => array(
					self::f( 'feeding_schedule', 'textarea', __( 'Feeding Schedule', 'pawfectly-reservations' ), array( 'help' => __( 'Please describe feeding times and portion sizes', 'pawfectly-reservations' ) ) ),
					self::f(
						'food_preference',
						'choice',
						__( 'Food Preference', 'pawfectly-reservations' ),
						array(
							'options' => array(
								'own'      => __( "I will provide my pet's food", 'pawfectly-reservations' ),
								'facility' => __( 'Please use facility food', 'pawfectly-reservations' ),
							),
						)
					),
					self::f( 'dietary_notes', 'textarea', __( 'Dietary Restrictions or Preferences', 'pawfectly-reservations' ), array( 'required' => false, 'placeholder' => __( 'e.g., grain-free, no chicken, etc.', 'pawfectly-reservations' ) ) ),
					self::f( 'behavior_notes', 'textarea', __( 'Behavioral Notes', 'pawfectly-reservations' ), array( 'required' => false, 'help' => __( 'Any important behavioral information we should know? (e.g., gets along with other pets, anxious tendencies, favorite toys, etc.)', 'pawfectly-reservations' ) ) ),
					self::f( 'exercise_needs', 'textarea', __( 'Exercise Needs', 'pawfectly-reservations' ), array( 'required' => false, 'help' => __( 'How much exercise does your pet need daily?', 'pawfectly-reservations' ) ) ),
				),
			),
			array(
				'id'     => 'emergency',
				'title'  => __( 'Emergency Contacts & Veterinarian', 'pawfectly-reservations' ),
				// Grooming-only visits skip the emergency contact; the vet stays.
				'title_grooming' => __( 'Veterinarian', 'pawfectly-reservations' ),
				'intro'  => '',
				'fields' => array(
					self::f( 'emergency_name', 'text', __( 'Emergency Contact Name', 'pawfectly-reservations' ), array( 'help' => __( 'Someone we can reach if we cannot contact you', 'pawfectly-reservations' ), 'show_if' => $stays ) ),
					self::f( 'emergency_phone', 'tel', __( 'Emergency Contact Phone', 'pawfectly-reservations' ), array( 'max' => 30, 'show_if' => $stays ) ),
					self::f( 'emergency_relationship', 'text', __( 'Relationship to Owner', 'pawfectly-reservations' ), array( 'placeholder' => __( 'e.g., spouse, friend, family member', 'pawfectly-reservations' ), 'show_if' => $stays ) ),
					self::f( 'vet_name', 'text', __( 'Veterinarian Name', 'pawfectly-reservations' ) ),
					self::f( 'vet_clinic', 'text', __( 'Veterinary Clinic Name', 'pawfectly-reservations' ) ),
					self::f( 'vet_phone', 'tel', __( 'Veterinarian Phone Number', 'pawfectly-reservations' ), array( 'max' => 30 ) ),
					self::f( 'vet_authorization', 'yesno', __( 'Do you authorize us to seek veterinary care if needed?', 'pawfectly-reservations' ), array( 'options' => $yes_no, 'help' => __( 'In case of emergency, we will contact you first, but this authorization allows us to act quickly if we cannot reach you', 'pawfectly-reservations' ) ) ),
				),
			),
			array(
				'id'     => 'waiver',
				'title'  => __( 'Liability Waiver & Agreement', 'pawfectly-reservations' ),
				'intro'  => __( 'Please read and acknowledge the following terms:', 'pawfectly-reservations' ),
				'fields' => array(
					self::f( 'waiver_agree', 'agree', __( 'Agreement', 'pawfectly-reservations' ), array( 'options' => array( '1' => __( 'I have read and agree to the above terms and conditions', 'pawfectly-reservations' ) ) ) ),
					self::f( 'contact_consent', 'agree', __( 'Contact (optional)', 'pawfectly-reservations' ), array( 'required' => false, 'options' => array( '1' => __( 'I consent to be contacted by Pawfectly via phone, email, or text to confirm and remind me about these appointments. Message & data rates may apply.', 'pawfectly-reservations' ) ) ) ),
					self::f( 'signature', 'signature', __( 'Owner Signature', 'pawfectly-reservations' ), array( 'help' => __( 'Please sign below to confirm your agreement', 'pawfectly-reservations' ) ) ),
					self::f( 'signed_date', 'date', __( 'Date', 'pawfectly-reservations' ), array( 'max' => 10, 'readonly' => true ) ),
				),
			),
		);

		foreach ( $steps as &$step ) {
			if ( in_array( $step['id'], self::PET_STEPS, true ) ) {
				foreach ( $step['fields'] as &$field ) {
					$field['per_pet'] = true;
					if ( 'care' === $step['id'] ) {
						$field['show_if'] += $stays; // Feeding and exercise only matter for a stay.
					}
				}
				unset( $field );
			}
		}
		unset( $step );
		return $steps;
	}

	/** The fields on the welcome screen, before step 1. */
	public static function welcome(): array {
		return array(
			self::f(
				'booking_type',
				'choice',
				__( 'What would you like to book?', 'pawfectly-reservations' ),
				array(
					'options' => array(
						'boarding' => __( 'Boarding', 'pawfectly-reservations' ),
						'grooming' => __( 'Grooming', 'pawfectly-reservations' ),
						'both'     => __( 'Boarding + grooming', 'pawfectly-reservations' ),
					),
					'default' => 'boarding',
					'max'     => 12,
				)
			),
		);
	}

	/**
	 * Fields asked once per pet, keyed by id, in step order.
	 *
	 * @return array<string, array>
	 */
	public static function pet_fields(): array {
		return array_filter( self::all(), static fn( array $f ): bool => $f['per_pet'] );
	}

	/**
	 * Every field keyed by id, each with its step number added (0 for the welcome screen).
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		$all = array();
		foreach ( self::welcome() as $field ) {
			$all[ $field['id'] ] = $field + array( 'step' => 0 );
		}
		foreach ( self::steps() as $i => $step ) {
			foreach ( $step['fields'] as $field ) {
				$all[ $field['id'] ] = $field + array( 'step' => $i + 1 );
			}
		}
		return $all;
	}

	/** @return array<string, string> ISO 3166-1 alpha-2 code => English name. */
	public static function countries(): array {
		static $countries = null;
		if ( null === $countries ) {
			$countries = require __DIR__ . '/data/countries.php';
		}
		return $countries;
	}

	private static function f( string $id, string $type, string $label, array $extra = array() ): array {
		return array_merge(
			array(
				'id'          => $id,
				'type'        => $type,
				'label'       => $label,
				'help'        => '',
				'placeholder' => '',
				'required'    => true,
				'max'         => 'textarea' === $type ? self::LONG : self::SHORT,
				'options'     => array(),
				'show_if'     => array(),
				'half'        => false,
				'readonly'    => false,
				'warn'        => array(),
				'default'     => '',
				'dog_only'    => array(),
				'per_pet'     => false,
				'past'        => false,
			),
			$extra
		);
	}
}
