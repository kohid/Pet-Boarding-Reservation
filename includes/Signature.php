<?php
/**
 * The owner's drawn signature, sent by the form as a PNG data URL.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Signature {

	public const MAX_BYTES = 512000;
	private const PREFIX   = 'data:image/png;base64,';

	/** @return string|\WP_Error The PNG bytes. */
	public static function decode( string $data_url ) {
		if ( ! str_starts_with( $data_url, self::PREFIX ) ) {
			return self::error();
		}
		$png = base64_decode( substr( $data_url, strlen( self::PREFIX ) ), true );
		if ( false === $png || strlen( $png ) > self::MAX_BYTES || ! str_starts_with( $png, "\x89PNG\r\n\x1a\n" ) ) {
			return self::error();
		}
		$size = getimagesizefromstring( $png );
		if ( ! $size || IMAGETYPE_PNG !== $size[2] || $size[0] < 50 || $size[1] < 30 || $size[0] > 3000 || $size[1] > 2000 ) {
			return self::error();
		}
		return self::is_blank( $png ) ? self::error() : $png;
	}

	/** @return string|\WP_Error Path relative to the private folder. */
	public static function store( string $reference, string $png ) {
		$relative = Uploads::new_path( $reference, 'png', 'sig' );
		if ( null === $relative || false === file_put_contents( Uploads::private_dir() . '/' . $relative, $png ) ) {
			return Uploads::storage_error();
		}
		return $relative;
	}

	/** A canvas nobody drew on is one flat colour: sample a grid and look for a second one. */
	private static function is_blank( string $png ): bool {
		$im = @imagecreatefromstring( $png );
		if ( ! $im ) {
			return true;
		}
		$w      = imagesx( $im );
		$h      = imagesy( $im );
		$colors = array();
		for ( $x = 0; $x < 40; $x++ ) {
			for ( $y = 0; $y < 40; $y++ ) {
				$colors[ imagecolorat( $im, intdiv( $x * ( $w - 1 ), 39 ), intdiv( $y * ( $h - 1 ), 39 ) ) ] = true;
				if ( count( $colors ) > 1 ) {
					return false;
				}
			}
		}
		return true;
	}

	private static function error(): \WP_Error {
		return new \WP_Error(
			'paw_invalid',
			__( 'Please check the highlighted fields.', 'pawfectly-reservations' ),
			array( 'status' => 400, 'fields' => array( 'signature' => __( 'Please sign in the box above.', 'pawfectly-reservations' ) ) )
		);
	}
}
