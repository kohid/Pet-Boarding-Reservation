<?php
/**
 * Checks a submission against the field list. Files and the signature are
 * checked by Uploads and Signature; everything else is checked here.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Validator {

	public const MAX_NIGHTS = 90;

	/** Types this class does not read: handled elsewhere, or (agree) checked but not stored. */
	private const SKIP = array( 'file', 'signature' );

	/**
	 * Booking answers stay flat; per-pet answers arrive as pets[N][field] and come back in
	 * details['pets'], with errors keyed "pets.N.field".
	 *
	 * @param array              $params Request body values (REST delivers them already unslashed).
	 * @param \DateTimeImmutable $today  Today in the site's timezone.
	 * @return array{columns: array<string,string>, details: array<string,mixed>}|\WP_Error
	 */
	public static function validate( array $params, \DateTimeImmutable $today ) {
		$clean   = array();
		$errors  = array();
		$per_pet = Fields::pet_fields();
		$day     = $today->format( 'Y-m-d' );

		foreach ( Fields::all() as $id => $field ) {
			if ( in_array( $field['type'], self::SKIP, true ) || isset( $per_pet[ $id ] ) || ! self::is_shown( $field, $clean ) ) {
				continue;
			}
			$value = self::read( $params[ $id ] ?? '', $field['type'] );
			$error = self::check( $field, $value );
			if ( null !== $error ) {
				$errors[ $id ] = $error;
			}
			$clean[ $id ] = $value;
		}

		$raw  = $params['pets'] ?? null;
		$pets = array();
		if ( ! is_array( $raw ) || ! $raw || count( $raw ) > Fields::MAX_PETS || array_keys( $raw ) !== range( 0, count( $raw ) - 1 ) ) {
			$errors['pets'] = __( 'Please check the highlighted fields.', 'pawfectly-reservations' );
		} else {
			foreach ( $raw as $n => $answers ) {
				$answers = is_array( $answers ) ? $answers : array();
				$pet     = array();
				foreach ( $per_pet as $id => $field ) {
					if ( in_array( $field['type'], self::SKIP, true ) || ! self::is_shown( $field, $pet + $clean ) ) {
						continue;
					}
					$value = self::read( $answers[ $id ] ?? '', $field['type'] );
					if ( '' === $value && '' !== $field['default'] ) {
						$value = $field['default']; // The groomer left at "Any / Flexible".
					}
					$error = self::pet_check( $field, $value, $clean, $day );
					if ( null !== $error ) {
						$errors[ "pets.$n.$id" ] = $error;
					}
					$pet[ $id ] = $value;
				}
				$pets[] = $pet;
			}
		}

		$errors += self::booking_rules( $clean, $errors, $day );
		$errors += self::pet_rules( $clean, $pets, $errors );

		if ( $errors ) {
			return new \WP_Error(
				'paw_invalid',
				__( 'Please check the highlighted fields.', 'pawfectly-reservations' ),
				array( 'status' => 400, 'fields' => $errors )
			);
		}

		unset( $clean['waiver_agree'] );
		if ( 'grooming' === $clean['booking_type'] ) {
			// One-day visit: lists and sorting keep working on check-in; booking_type keeps it out of boarding counts.
			$clean['check_in']  = $clean['appointment_date'];
			$clean['check_out'] = $clean['appointment_date'];
		}
		$names                = implode( ' + ', array_column( $pets, 'pet_name' ) );
		$clean['pet_name']    = mb_strlen( $names ) > 60 ? mb_substr( $names, 0, 59 ) . '…' : $names;
		$clean['pet_type']    = $pets[0]['pet_type'];
		$clean['pet_count']   = (string) count( $pets );
		$clean['service']     = implode( ',', array_filter( array_column( $pets, 'service' ), static fn( string $s ): bool => '' !== $s && 'none' !== $s ) );
		$columns = array();
		foreach ( Fields::COLUMNS as $col ) {
			$columns[ $col ] = $clean[ $col ] ?? '';
			unset( $clean[ $col ] );
		}
		$clean['pets'] = $pets;
		return array( 'columns' => $columns, 'details' => $clean );
	}

	/** One per-pet value: the usual checks, plus "No grooming" in Both bookings and no future last-groom date. */
	private static function pet_check( array $field, string $value, array $booking, string $today ): ?string {
		if ( 'service' === $field['type'] && 'none' === $value ) {
			return 'both' === ( $booking['booking_type'] ?? '' ) ? null : __( 'Please choose one of the services.', 'pawfectly-reservations' );
		}
		$error = self::check( $field, $value );
		if ( null === $error && $field['past'] && '' !== $value && $value > $today ) {
			return __( "The last groom date can't be in the future.", 'pawfectly-reservations' );
		}
		return $error;
	}

	/** Rules across pets: current vaccinations, grooming for dogs only, at least one groomed pet. */
	private static function pet_rules( array $booking, array $pets, array $errors ): array {
		$out  = array();
		$type = $booking['booking_type'] ?? '';
		foreach ( $pets as $n => $pet ) {
			if ( ! isset( $errors[ "pets.$n.vaccinations_current" ] ) && 'yes' !== ( $pet['vaccinations_current'] ?? '' ) ) {
				$out[ "pets.$n.vaccinations_current" ] = __( 'All pets must have current vaccinations to board with us.', 'pawfectly-reservations' );
			}
		}
		if ( isset( $errors['booking_type'] ) || isset( $errors['pets'] ) || 'boarding' === $type ) {
			return $out;
		}
		if ( 'grooming' === $type && array_filter( $pets, static fn( array $p ): bool => 'dog' !== ( $p['pet_type'] ?? '' ) ) ) {
			$out['booking_type'] = __( 'Grooming is for dogs only. Choose Boarding + grooming to board your other pets.', 'pawfectly-reservations' );
		} elseif ( 'both' === $type && ! array_filter( $pets, static fn( array $p ): bool => '' !== ( $p['service'] ?? '' ) && 'none' !== $p['service'] ) && ! preg_grep( '/^pets\.\d+\.service$/', array_keys( $errors ) ) ) {
			$out['booking_type'] = __( 'Choose a service for at least one pet, or book Boarding only.', 'pawfectly-reservations' );
		}
		return $out;
	}

	/** Rules across fields: dates per booking type, grooming for dogs only. */
	private static function booking_rules( array $clean, array $errors, string $today ): array {
		$out  = array();
		$type = $clean['booking_type'] ?? '';
		if ( isset( $errors['booking_type'] ) ) {
			return $out;
		}
		$stay = in_array( $type, array( 'boarding', 'both' ), true );
		if ( $stay && ! isset( $errors['check_in'] ) && '' !== $clean['check_in'] && $clean['check_in'] < $today ) {
			$out['check_in'] = __( "Check-in can't be in the past.", 'pawfectly-reservations' );
		}
		$dates_ok = $stay && ! isset( $errors['check_in'] ) && ! isset( $errors['check_out'] ) && ! isset( $out['check_in'] ) && '' !== $clean['check_out'];
		if ( $dates_ok ) {
			$nights = ( new \DateTimeImmutable( $clean['check_in'] ) )->diff( new \DateTimeImmutable( $clean['check_out'] ) );
			if ( $clean['check_out'] <= $clean['check_in'] ) {
				$out['check_out'] = __( 'Check-out must be after check-in.', 'pawfectly-reservations' );
				$dates_ok         = false;
			} elseif ( $nights->days > self::MAX_NIGHTS ) {
				/* translators: %d: maximum number of nights */
				$out['check_out'] = sprintf( __( 'Stays are limited to %d nights.', 'pawfectly-reservations' ), self::MAX_NIGHTS );
				$dates_ok         = false;
			}
		}

		$day = $clean['appointment_date'] ?? '';
		if ( 'boarding' !== $type && ! isset( $errors['appointment_date'] ) && '' !== $day ) {
			if ( 'grooming' === $type && $day < $today ) {
				$out['appointment_date'] = __( "The grooming day can't be in the past.", 'pawfectly-reservations' );
			} elseif ( 'both' === $type && $dates_ok && ( $day < $clean['check_in'] || $day > $clean['check_out'] ) ) {
				$out['appointment_date'] = __( 'The grooming day must fall within the stay.', 'pawfectly-reservations' );
			}
		}
		return $out;
	}

	/** A conditional field counts only while every field it depends on has (one of) the triggering value(s). */
	public static function is_shown( array $field, array $values ): bool {
		foreach ( $field['show_if'] as $target => $wanted ) {
			if ( ! in_array( $values[ $target ] ?? '', (array) $wanted, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Keeps what the visitor typed: only invalid UTF-8 and control characters are removed.
	 * No HTML filtering here, so "<3" or "50%Beef" survive; every output escapes instead.
	 * REST body params arrive already unslashed.
	 */
	public static function read( $raw, string $type ): string {
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		$value = (string) $raw;
		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			return '';
		}
		$value = str_replace( "\r\n", "\n", $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );
		if ( 'textarea' !== $type ) {
			$value = preg_replace( '/[\r\n\t]+/', ' ', $value );
		}
		return trim( $value );
	}

	/** The first problem with one value, or null. */
	public static function check( array $field, string $value ): ?string {
		if ( '' === $value ) {
			if ( ! $field['required'] ) {
				return null;
			}
			return 'agree' === $field['type']
				? __( 'Please agree to the terms to continue.', 'pawfectly-reservations' )
				: __( 'This field is required.', 'pawfectly-reservations' );
		}
		if ( mb_strlen( $value ) > $field['max'] ) {
			/* translators: %d: maximum number of characters */
			return sprintf( __( 'Please keep this under %d characters.', 'pawfectly-reservations' ), $field['max'] );
		}

		switch ( $field['type'] ) {
			case 'email':
				return is_email( $value ) ? null : __( 'Please enter a valid email address.', 'pawfectly-reservations' );
			case 'tel':
				$digits = preg_replace( '/[\s+\-().]/', '', $value );
				return preg_match( '/^\d{7,20}$/', $digits ) ? null : __( 'Please enter a valid phone number.', 'pawfectly-reservations' );
			case 'choice':
			case 'yesno':
				return isset( $field['options'][ $value ] ) ? null : __( 'Please choose one of the options.', 'pawfectly-reservations' );
			case 'agree':
				return '1' === $value ? null : __( 'Please agree to the terms to continue.', 'pawfectly-reservations' );
			case 'country':
				return isset( Fields::countries()[ $value ] ) ? null : __( 'Please choose one of the options.', 'pawfectly-reservations' );
			case 'service':
				return isset( Services::enabled()[ $value ] ) ? null : __( 'Please choose one of the services.', 'pawfectly-reservations' );
			case 'date':
				$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
				return ( $date && $date->format( 'Y-m-d' ) === $value ) ? null : __( 'Please enter a valid date.', 'pawfectly-reservations' );
		}
		return null;
	}
}
