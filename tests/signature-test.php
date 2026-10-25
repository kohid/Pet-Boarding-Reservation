<?php
/**
 * The drawn signature arrives as a PNG data URL: decoded, checked for being a real, non-blank PNG, stored privately.
 * Run: php tests/signature-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';

use Pawfectly\Signature;
use Pawfectly\Uploads;

$msg = 'Please sign in the box above.';
function sig_error( $r ): ?string {
	return is_wp_error( $r ) ? ( $r->get_error_data()['fields']['signature'] ?? 'no field error' ) : null;
}

$png = fixture_png_bytes();
$ok  = Signature::decode( 'data:image/png;base64,' . base64_encode( $png ) );
t( 'drawn signature decodes to the PNG bytes', $ok === $png );
t( 'blank canvas rejected', $msg === sig_error( Signature::decode( 'data:image/png;base64,' . base64_encode( fixture_png_bytes( 300, 150, true ) ) ) ) );
t( 'jpeg data url rejected', $msg === sig_error( Signature::decode( 'data:image/jpeg;base64,' . base64_encode( fixture_jpeg_bytes() ) ) ) );
t( 'jpeg bytes behind a png prefix rejected', $msg === sig_error( Signature::decode( 'data:image/png;base64,' . base64_encode( fixture_jpeg_bytes() ) ) ) );
t( 'over 500 KB rejected', $msg === sig_error( Signature::decode( 'data:image/png;base64,' . base64_encode( $png . random_bytes( 600 * 1024 ) ) ) ) );
t( 'garbage base64 rejected', $msg === sig_error( Signature::decode( 'data:image/png;base64,@@@not-base64@@@' ) ) );
t( 'empty rejected', $msg === sig_error( Signature::decode( '' ) ) );
t( 'error status 400', 400 === Signature::decode( '' )->get_error_data()['status'] );

$path = Signature::store( 'PAW-7K3QX9', $png );
t( 'stored under the private dir', is_string( $path ) && (bool) preg_match( '#^\d{4}/\d{2}/PAW-7K3QX9-sig-[0-9a-f]{16}\.png$#', $path ) );
t( 'stored bytes match', is_string( $path ) && file_get_contents( Uploads::resolve( $path ) ) === $png );

fixture_rmrf( $GLOBALS['paw_upload_base'] );
done();
