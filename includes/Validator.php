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
	 * @param array              $params Request body values (REST delivers them already unslashed).
	 * @param \DateTimeImmutable $today  Today in the site's timezone.
	 * @return array{columns: array<string,string>, details: array<string,string>}|\WP_Error
	 */
	public static function validate( array $params, \DateTimeImmutable $today ) {
		$clean  = array();
		$errors = array();

		foreach ( Fields::all() as $id => $field ) {
			if ( in_array( $field['type'], self::SKIP, true ) ) {
				continue;
			}
			if ( ! self::is_shown( $field, $clean ) ) {
				continue;
			}
			$value = self::read( $params[ $id ] ?? '', $field['type'] );
			$error = self::check( $field, $value );
			if ( null !== $error ) {
				$errors[ $id ] = $error;
			}
			$clean[ $id ] = $value;
		}

		if ( ! isset( $errors['check_in'] ) && '' !== $clean['check_in'] ) {
			if ( $clean['check_in'] < $today->format( 'Y-m-d' ) ) {
				$errors['check_in'] = __( "Check-in can't be in the past.", 'pawfectly-reservations' );
			}
		}
		if ( ! isset( $errors['check_in'] ) && ! isset( $errors['check_out'] ) && '' !== $clean['check_out'] ) {
			$nights = ( new \DateTimeImmutable( $clean['check_in'] ) )->diff( new \DateTimeImmutable( $clean['check_out'] ) );
			if ( $clean['check_out'] <= $clean['check_in'] ) {
				$errors['check_out'] = __( 'Check-out must be after check-in.', 'pawfectly-reservations' );
			} elseif ( $nights->days > self::MAX_NIGHTS ) {
				/* translators: %d: maximum number of nights */
				$errors['check_out'] = sprintf( __( 'Stays are limited to %d nights.', 'pawfectly-reservations' ), self::MAX_NIGHTS );
			}
		}
		if ( ! isset( $errors['vaccinations_current'] ) && 'yes' !== $clean['vaccinations_current'] ) {
			$errors['vaccinations_current'] = __( 'All pets must have current vaccinations to board with us.', 'pawfectly-reservations' );
		}

		if ( $errors ) {
			return new \WP_Error(
				'paw_invalid',
				__( 'Please check the highlighted fields.', 'pawfectly-reservations' ),
				array( 'status' => 400, 'fields' => $errors )
			);
		}

		unset( $clean['waiver_agree'] );
		$columns = array();
		foreach ( Fields::COLUMNS as $col ) {
			$columns[ $col ] = $clean[ $col ];
			unset( $clean[ $col ] );
		}
		return array( 'columns' => $columns, 'details' => $clean );
	}

	/** A conditional field counts only while every field it depends on has the triggering value. */
	public static function is_shown( array $field, array $values ): bool {
		foreach ( $field['show_if'] as $target => $wanted ) {
			if ( ( $values[ $target ] ?? '' ) !== $wanted ) {
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
	private static function read( $raw, string $type ): string {
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
	private static function check( array $field, string $value ): ?string {
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
			case 'date':
				$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
				return ( $date && $date->format( 'Y-m-d' ) === $value ) ? null : __( 'Please enter a valid date.', 'pawfectly-reservations' );
		}
		return null;
	}
}
