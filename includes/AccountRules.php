<?php
/**
 * Validation for sign-up and profile details. Pure: no WordPress state, so it is easy to test.
 * Text is cleaned the same way as reservation answers (kept as typed, minus control characters).
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class AccountRules {

	public const MIN_PASSWORD = 8;

	/** @return array{clean: array<string,string>, errors: array<string,string>} */
	public static function signup( array $in ): array {
		$r     = self::details( $in );
		$error = self::password( is_string( $in['password'] ?? null ) ? $in['password'] : '', is_string( $in['confirm'] ?? null ) ? $in['confirm'] : '' );
		if ( null !== $error ) {
			$r['errors']['password'] = $error;
		}
		return $r;
	}

	/** Name, email and phone, checked with the same rules as the reservation form's owner fields. */
	public static function details( array $in ): array {
		$all   = Fields::all();
		$map   = array(
			'first_name' => $all['owner_first_name'],
			'last_name'  => $all['owner_last_name'],
			'email'      => $all['owner_email'],
			'phone'      => array_merge( $all['owner_phone'], array( 'required' => false ) ),
		);
		$clean  = array();
		$errors = array();
		foreach ( $map as $key => $field ) {
			$clean[ $key ] = Validator::read( $in[ $key ] ?? '', $field['type'] );
			$error         = Validator::check( $field, $clean[ $key ] );
			if ( null !== $error ) {
				$errors[ $key ] = $error;
			}
		}
		return array( 'clean' => $clean, 'errors' => $errors );
	}

	/** The problem with a new password, or null. */
	public static function password( string $password, ?string $confirm ): ?string {
		if ( mb_strlen( $password ) < self::MIN_PASSWORD ) {
			/* translators: %d: minimum number of characters */
			return sprintf( __( 'Use at least %d characters.', 'pawfectly-reservations' ), self::MIN_PASSWORD );
		}
		if ( null !== $confirm && $confirm !== $password ) {
			return __( "The two passwords don't match.", 'pawfectly-reservations' );
		}
		return null;
	}
}
