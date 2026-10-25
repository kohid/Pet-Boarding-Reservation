<?php
/**
 * Vaccination uploads: count, size and real file type are checked, files land in the private folder.
 * Run: php tests/uploads-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';

use Pawfectly\Uploads;

Uploads::$is_uploaded = static fn( string $path ): bool => is_file( $path );
Uploads::$mover       = static fn( string $from, string $to ): bool => rename( $from, $to );

function field_error( $result ): ?string {
	return is_wp_error( $result ) ? ( $result->get_error_data()['fields']['vaccination_files'] ?? 'no field error' ) : null;
}

$pdf = fixture_file( 'rec.pdf', fixture_pdf_bytes() );
$png = fixture_file( 'rec.png', fixture_png_bytes() );
$jpg = fixture_file( 'rec.jpeg', fixture_jpeg_bytes() );

$ok = Uploads::check( fixture_files_field( array( 'Rabies.pdf' => $pdf, 'card.png' => $png, 'photo.jpeg' => $jpg ) ) );
t( 'pdf, png and jpeg pass', is_array( $ok ) && 3 === count( $ok ) );
t( 'mime comes from the content', is_array( $ok ) && array( 'application/pdf', 'image/png', 'image/jpeg' ) === array_column( $ok, 'mime' ) );
t( '.jpeg normalised to jpg', is_array( $ok ) && 'jpg' === $ok[2]['ext'] );
t( 'original name kept for display', is_array( $ok ) && 'Rabies.pdf' === $ok[0]['name'] );

$count_msg = 'Please upload between 1 and 3 files.';
$size_msg  = 'Each file must be 10 MB or smaller.';
$type_msg  = 'Only PDF, JPG or PNG files are accepted.';

t( 'no files', $count_msg === field_error( Uploads::check( array() ) ) );
t( 'empty file input (UPLOAD_ERR_NO_FILE)', $count_msg === field_error( Uploads::check( fixture_files_field( array( '' => '' ), array( UPLOAD_ERR_NO_FILE ) ) ) ) );
t( 'four files', $count_msg === field_error( Uploads::check( fixture_files_field( array( 'a.pdf' => $pdf, 'b.pdf' => $pdf, 'c.pdf' => $pdf, 'd.pdf' => $pdf ) ) ) ) );

$big = fixture_file( 'big.pdf', fixture_pdf_bytes() );
$fh  = fopen( $big, 'r+' );
ftruncate( $fh, 11 * 1024 * 1024 );
fclose( $fh );
t( '11 MB file', $size_msg === field_error( Uploads::check( fixture_files_field( array( 'big.pdf' => $big ) ) ) ) );
t( 'php ini size error', $size_msg === field_error( Uploads::check( fixture_files_field( array( 'a.pdf' => $pdf ), array( UPLOAD_ERR_INI_SIZE ) ) ) ) );

$php  = fixture_file( 'x.pdf', '<?php echo 1;' );
$heic = fixture_file( 'x.jpg', "\0\0\0\x18ftypheic\0\0\0\0mif1heic" . str_repeat( "\0", 64 ) );
t( 'php renamed to .pdf', $type_msg === field_error( Uploads::check( fixture_files_field( array( 'x.pdf' => $php ) ) ) ) );
t( 'heic renamed to .jpg', $type_msg === field_error( Uploads::check( fixture_files_field( array( 'IMG_0001.jpg' => $heic ) ) ) ) );
t( 'heic with its own extension', $type_msg === field_error( Uploads::check( fixture_files_field( array( 'IMG_0001.HEIC' => $heic ) ) ) ) );
t( 'png content with .pdf name', $type_msg === field_error( Uploads::check( fixture_files_field( array( 'sneaky.pdf' => $png ) ) ) ) );
t( 'not an uploaded file', $type_msg === field_error( ( function () use ( $pdf ) {
	$keep                 = Uploads::$is_uploaded;
	Uploads::$is_uploaded = static fn() => false;
	$r                    = Uploads::check( fixture_files_field( array( 'a.pdf' => $pdf ) ) );
	Uploads::$is_uploaded = $keep;
	return $r;
} )() ) );

// Limits follow the server's real PHP settings, never above 10 MB.
t( 'per-file limit capped at 10 MB', 10485760 === Uploads::max_file_bytes() );
$GLOBALS['paw_max_upload'] = 2 * 1024 * 1024;
t( 'per-file limit follows a 2 MB host', 2097152 === Uploads::max_file_bytes() );
$three = fixture_file( 'three.pdf', fixture_pdf_bytes() );
$fh    = fopen( $three, 'r+' );
ftruncate( $fh, 3 * 1024 * 1024 );
fclose( $fh );
t( '3 MB file on a 2 MB host names the real limit', 'Each file must be 2 MB or smaller.' === field_error( Uploads::check( fixture_files_field( array( 'three.pdf' => $three ) ) ) ) );
t( 'php ini size error names the real limit', 'Each file must be 2 MB or smaller.' === field_error( Uploads::check( fixture_files_field( array( 'a.pdf' => $pdf ), array( UPLOAD_ERR_INI_SIZE ) ) ) ) );
unset( $GLOBALS['paw_max_upload'] );
t( 'request limit leaves room for the other answers', \Pawfectly\Rest::ini_bytes( (string) ini_get( 'post_max_size' ) ) - 65536 === Uploads::max_request_bytes() );
t( 'sizes read like people write them', '2 MB' === Uploads::human( 2097152 ) && '10 MB' === Uploads::human( 10485760 ) && '1.5 MB' === Uploads::human( 1572864 ) && '500 KB' === Uploads::human( 512000 ) );

// Storage.
$dir = Uploads::ensure_private_dir();
t( 'private dir created', is_dir( $dir ) );
t( 'private dir denies web access', str_contains( (string) @file_get_contents( $dir . '/.htaccess' ), 'Require all denied' ) );
t( 'private dir has an index.php', is_file( $dir . '/index.php' ) );

$stored = Uploads::store( 'PAW-7K3QX9', $ok );
t( 'store returns one entry per file', is_array( $stored ) && 3 === count( $stored ) );
$first = $stored[0];
t( 'stored name uses the reference, not the original name', (bool) preg_match( '#^\d{4}/\d{2}/PAW-7K3QX9-[0-9a-f]{16}\.pdf$#', $first['path'] ) );
t( 'stored file exists', is_file( $dir . '/' . $first['path'] ) );
t( 'stored entry belongs to pet 1 by default', 0 === $first['pet'] );
$second = Uploads::store( 'PAW-7K3QX8', array_slice( Uploads::check( fixture_files_field( array( 'b.pdf' => fixture_file( 'b.pdf', fixture_pdf_bytes() ) ) ) ), 0, 1 ), 1 );
t( 'stored entry records which pet it belongs to', is_array( $second ) && 1 === $second[0]['pet'] );
t( 'stored entry keeps name, mime and size', 'Rabies.pdf' === $first['name'] && 'application/pdf' === $first['mime'] && $first['size'] > 0 );
t( 'resolve finds a stored file', Uploads::resolve( $first['path'] ) === realpath( $dir . '/' . $first['path'] ) );
t( 'resolve refuses traversal', null === Uploads::resolve( '../../wp-config.php' ) );
t( 'resolve refuses missing', null === Uploads::resolve( '2026/10/nope.pdf' ) );

Uploads::delete( $stored );
t( 'delete removes the files', ! is_file( $dir . '/' . $first['path'] ) );

// A failed move cleans up whatever was already written.
$a = fixture_file( 'a.pdf', fixture_pdf_bytes() );
$b = fixture_file( 'b.pdf', fixture_pdf_bytes() );
$checked = Uploads::check( fixture_files_field( array( 'a.pdf' => $a, 'b.pdf' => $b ) ) );
$calls   = 0;
Uploads::$mover = static function ( string $from, string $to ) use ( &$calls ): bool {
	return 1 === ++$calls ? rename( $from, $to ) : false;
};
$failed = Uploads::store( 'PAW-AAAAAA', $checked );
t( 'failed move returns paw_storage', is_wp_error( $failed ) && 'paw_storage' === $failed->get_error_code() );
t( 'failed move leaves no partial files', array() === glob( $dir . '/*/*/PAW-AAAAAA-*' ) );

fixture_rmrf( $GLOBALS['paw_upload_base'] );

done();
