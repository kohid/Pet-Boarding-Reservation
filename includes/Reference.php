<?php
/**
 * Short, readable reservation references such as PAW-7K3QX9.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Reference {

	/** No O/0, I/1 or L, so a reference read out over the phone is never ambiguous. */
	public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	/** PAW-7K3QX9 for reservations; EVT-… for event sign-ups. */
	public static function generate( string $prefix = 'PAW' ): string {
		$ref = $prefix . '-';
		$max = strlen( self::ALPHABET ) - 1;
		for ( $i = 0; $i < 6; $i++ ) {
			$ref .= self::ALPHABET[ random_int( 0, $max ) ];
		}
		return $ref;
	}

	public static function is_valid( string $ref ): bool {
		return (bool) preg_match( '/^PAW-[' . self::ALPHABET . ']{6}$/', $ref );
	}
}
