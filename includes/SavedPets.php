<?php
/**
 * An owner's saved pets and details for bookings (My Profile), so the booking form can fill them
 * in. Pets live in user meta paw_pets as [{uid, values}], the details in paw_owner_info; both are
 * read and written only here, with the booking form's own field rules (Fields, Validator).
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class SavedPets {

	public const META_PETS = 'paw_pets';
	public const META_INFO = 'paw_owner_info';
	public const MAX       = 10;
	/** Booking-form steps whose per-pet answers are saved with each pet. */
	private const PET_STEPS = array( 'pet', 'health', 'care' );
	/** Asked fresh on every booking: vaccinations must be current at the time of the stay. */
	private const PER_BOOKING = array( 'vaccinations_current' );
	/** Owner-step fields saved as details for bookings (with the whole emergency step). */
	private const INFO_FIELDS = array( 'address_street', 'address_city', 'address_state', 'address_postcode', 'address_country' );

	/**
	 * The saved pet fields, keyed by id, grouped as the booking form's steps: step id => [title, fields].
	 * Files are uploaded with each booking, so they are left out.
	 *
	 * @return array<string, array{0: string, 1: array<string, array>}>
	 */
	public static function pet_groups(): array {
		$out = array();
		foreach ( Fields::steps() as $step ) {
			if ( ! in_array( $step['id'], self::PET_STEPS, true ) ) {
				continue;
			}
			$fields = array();
			foreach ( $step['fields'] as $f ) {
				if ( 'file' !== $f['type'] && ! in_array( $f['id'], self::PER_BOOKING, true ) ) {
					$fields[ $f['id'] ] = $f;
				}
			}
			$out[ $step['id'] ] = array( $step['title'], $fields );
		}
		return $out;
	}

	/** @return array<string, array> */
	public static function pet_fields(): array {
		return array_merge( ...array_values( array_map( static fn( array $g ): array => $g[1], self::pet_groups() ) ) );
	}

	/** The details-for-bookings fields (address, emergency contact, vet), keyed by id. */
	public static function info_fields(): array {
		$out = array();
		foreach ( Fields::steps() as $step ) {
			foreach ( $step['fields'] as $f ) {
				if ( 'emergency' === $step['id'] || in_array( $f['id'], self::INFO_FIELDS, true ) ) {
					$out[ $f['id'] ] = $f;
				}
			}
		}
		return $out;
	}

	/**
	 * The saved pets; with none saved yet, the pets from the owner's last booking (not saved until
	 * they are). $last: that booking, when the caller already has it (false: look it up).
	 */
	public static function get( int $user_id, $last = false ): array {
		$saved = get_user_meta( $user_id, self::META_PETS, true );
		if ( is_array( $saved ) ) {
			return array_values( $saved );
		}
		$last = false === $last ? self::last_booking( $user_id ) : $last;
		if ( ! $last ) {
			return array();
		}
		$pets = array();
		foreach ( array_slice( Reservations::pets( $last ), 0, self::MAX ) as $pet ) {
			$pets[] = array( 'uid' => self::uid(), 'values' => self::keep( $pet, self::pet_fields() ) );
		}
		return $pets;
	}

	/** The saved details for bookings; with none saved yet, those from the last booking ($last as in get()). */
	public static function info( int $user_id, $last = false ): array {
		$saved = get_user_meta( $user_id, self::META_INFO, true );
		if ( is_array( $saved ) ) {
			return $saved;
		}
		$last = false === $last ? self::last_booking( $user_id ) : $last;
		return $last ? self::keep( Reservations::values( $last ), self::info_fields() ) : array();
	}

	/**
	 * Checks a list of pets from My Profile. @return array{0: array, 1: array<string, string>}
	 * The clean list, and errors keyed "pets.N.field_id" (or "pets" for the list itself).
	 */
	public static function sanitize( $pets ): array {
		if ( ! is_array( $pets ) ) {
			return array( array(), array() );
		}
		if ( count( $pets ) > self::MAX ) {
			/* translators: %d: most pets */
			return array( array(), array( 'pets' => sprintf( __( 'You can save up to %d pets.', 'pawfectly-reservations' ), self::MAX ) ) );
		}
		$clean  = array();
		$errors = array();
		foreach ( array_values( $pets ) as $n => $pet ) {
			$pet    = is_array( $pet ) ? $pet : array();
			$values = is_array( $pet['values'] ?? null ) ? $pet['values'] : array();
			[ $v, $e ] = self::clean_values( $values, self::pet_fields(), array( 'pet_name' ) );
			foreach ( $e as $id => $msg ) {
				$errors[ "pets.$n.$id" ] = $msg;
			}
			$uid     = is_string( $pet['uid'] ?? null ) && preg_match( '/^[a-f0-9]{12}$/', $pet['uid'] ) ? $pet['uid'] : self::uid();
			$clean[] = array( 'uid' => $uid, 'values' => $v );
		}
		return array( $clean, $errors );
	}

	/** @return array{0: array<string, string>, 1: array<string, string>} Clean details, and errors keyed by field. */
	public static function sanitize_info( $values ): array {
		return self::clean_values( is_array( $values ) ? $values : array(), self::info_fields(), array() );
	}

	public static function save( int $user_id, array $clean_pets ): void {
		update_user_meta( $user_id, self::META_PETS, array_values( $clean_pets ) );
	}

	public static function save_info( int $user_id, array $clean_info ): void {
		update_user_meta( $user_id, self::META_INFO, $clean_info );
	}

	/**
	 * After a signed-in owner's booking: its pets join their saved pets (matched by name, any case;
	 * a match takes the booking's non-empty answers), and its details update the saved details.
	 */
	public static function remember( int $user_id, array $reservation ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		$pets  = self::get( $user_id );
		$index = array();
		foreach ( $pets as $i => $p ) {
			$index[ mb_strtolower( trim( (string) ( $p['values']['pet_name'] ?? '' ) ) ) ] = $i;
		}
		foreach ( Reservations::pets( $reservation ) as $pet ) {
			[ $values ] = self::clean_values( array_map( 'strval', array_filter( $pet, 'is_scalar' ) ), self::pet_fields(), array() );
			$name       = mb_strtolower( trim( (string) ( $values['pet_name'] ?? '' ) ) );
			if ( '' === $name ) {
				continue;
			}
			if ( isset( $index[ $name ] ) ) {
				unset( $values['pet_name'] ); // The profile's spelling of the name stays.
				// Checked again as a whole: answers the new ones hide (details after a "no", a cat's dog size) go.
				$pets[ $index[ $name ] ]['values'] = self::keep( array_merge( $pets[ $index[ $name ] ]['values'], $values ), self::pet_fields() );
			} elseif ( count( $pets ) < self::MAX ) {
				$index[ $name ] = count( $pets );
				$pets[]         = array( 'uid' => self::uid(), 'values' => $values );
			}
		}
		self::save( $user_id, $pets );
		[ $info ] = self::clean_values( array_map( 'strval', array_filter( Reservations::values( $reservation ), 'is_scalar' ) ), self::info_fields(), array() );
		self::save_info( $user_id, array_merge( self::info( $user_id ), $info ) );
	}

	/**
	 * The values that belong to these fields and pass the booking form's checks, with answers whose
	 * show_if (within these fields) is not met dropped. $required: ids that must not be empty.
	 *
	 * @return array{0: array<string, string>, 1: array<string, string>}
	 */
	private static function clean_values( array $in, array $fields, array $required ): array {
		$values = array();
		foreach ( $fields as $id => $f ) {
			$values[ $id ] = Validator::read( $in[ $id ] ?? '', $f['type'] );
		}
		$out    = array();
		$errors = array();
		foreach ( $fields as $id => $f ) {
			$conditions = array_intersect_key( $f['show_if'], $fields ); // Booking-type conditions don't apply here.
			if ( ! Validator::is_shown( array( 'show_if' => $conditions ) + $f, $values ) ) {
				continue;
			}
			$error = Validator::check( array( 'required' => in_array( $id, $required, true ) ) + $f, $values[ $id ] );
			if ( null !== $error ) {
				$errors[ $id ] = $error;
			} elseif ( '' !== $values[ $id ] ) {
				$out[ $id ] = $values[ $id ];
			}
		}
		return array( $out, $errors );
	}

	/** The values among these fields that pass, as strings (used when reading old bookings). */
	private static function keep( array $values, array $fields ): array {
		return self::clean_values( array_map( 'strval', array_filter( $values, 'is_scalar' ) ), $fields, array() )[0];
	}

	private static function last_booking( int $user_id ): ?array {
		$user = get_user_by( 'id', $user_id );
		return $user ? Reservations::last_for_owner( $user_id, (string) $user->user_email, Accounts::is_verified( $user_id ) ) : null;
	}

	private static function uid(): string {
		return bin2hex( random_bytes( 6 ) );
	}
}
