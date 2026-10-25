<?php
/**
 * Files generated at run time for upload and signature tests, so no binaries live in the repo.
 */

function fixture_dir(): string {
	static $dir = null;
	if ( null === $dir ) {
		$dir = sys_get_temp_dir() . '/paw-fixtures-' . getmypid();
		@mkdir( $dir, 0755, true );
		register_shutdown_function( static fn() => fixture_rmrf( $dir ) );
	}
	return $dir;
}

function fixture_rmrf( string $path ): void {
	if ( is_dir( $path ) ) {
		foreach ( scandir( $path ) as $f ) {
			if ( '.' !== $f && '..' !== $f ) {
				fixture_rmrf( "$path/$f" );
			}
		}
		@rmdir( $path );
	} elseif ( file_exists( $path ) ) {
		@unlink( $path );
	}
}

function fixture_file( string $name, string $bytes ): string {
	$path = fixture_dir() . '/' . uniqid() . '-' . $name;
	file_put_contents( $path, $bytes );
	return $path;
}

function fixture_pdf_bytes(): string {
	return "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
}

/** A PNG with a dark diagonal stroke on a transparent background, like a real signature. */
function fixture_png_bytes( int $w = 300, int $h = 150, bool $blank = false ): string {
	$im = imagecreatetruecolor( $w, $h );
	imagesavealpha( $im, true );
	imagefill( $im, 0, 0, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) );
	if ( ! $blank ) {
		imagesetthickness( $im, 4 );
		imageline( $im, 10, 10, $w - 10, $h - 10, imagecolorallocate( $im, 20, 20, 20 ) );
		imageline( $im, 10, $h - 10, $w - 10, 10, imagecolorallocate( $im, 20, 20, 20 ) );
	}
	ob_start();
	imagepng( $im );
	return ob_get_clean();
}

function fixture_jpeg_bytes(): string {
	$im = imagecreatetruecolor( 40, 40 );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 100, 50 ) );
	ob_start();
	imagejpeg( $im );
	return ob_get_clean();
}

/** Builds a PHP-style multi-file $_FILES entry from [name => path] pairs. */
function fixture_files_field( array $files, array $errors = array() ): array {
	$field = array( 'name' => array(), 'type' => array(), 'tmp_name' => array(), 'error' => array(), 'size' => array() );
	$i     = 0;
	foreach ( $files as $name => $path ) {
		$field['name'][]     = $name;
		$field['type'][]     = 'application/pdf'; // What the browser claims; never trusted.
		$field['tmp_name'][] = $path;
		$field['error'][]    = $errors[ $i ] ?? UPLOAD_ERR_OK;
		$field['size'][]     = file_exists( $path ) ? filesize( $path ) : 0;
		++$i;
	}
	return $field;
}

/** A valid one-pet boarding submission in the v0.4 shape (per-pet answers under pets[0]). */
function paw_valid_params(): array {
	$flat = array(
		'booking_type'           => 'boarding',
		'owner_first_name'       => 'Ada',
		'owner_last_name'        => 'Lovelace',
		'owner_email'            => 'ada@example.com',
		'owner_phone'            => '+1 (555) 010-2030',
		'address_street'         => '1 Main St',
		'address_city'           => 'Springfield',
		'address_state'          => 'IL',
		'address_postcode'       => '62701',
		'address_country'        => 'US',
		'pet_name'               => 'Biscuit',
		'pet_type'               => 'dog',
		'dog_size'               => 'large',
		'pet_breed'              => 'Beagle',
		'pet_age'                => '3 years',
		'pet_gender'             => 'male',
		'pet_weight'             => '25 lbs',
		'pet_spayed'             => 'yes',
		'pet_microchipped'       => 'no',
		'check_in'               => gmdate( 'Y-m-d', strtotime( '+3 days' ) ),
		'check_out'              => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
		'vaccinations_current'   => 'yes',
		'medical_conditions'     => 'no',
		'medications'            => 'no',
		'allergies'              => 'no',
		'feeding_schedule'       => 'Twice a day, one cup',
		'food_preference'        => 'own',
		'emergency_name'         => 'Charles Babbage',
		'emergency_phone'        => '555 010 9999',
		'emergency_relationship' => 'friend',
		'vet_name'               => 'Dr. Who',
		'vet_clinic'             => 'Tardis Vets',
		'vet_phone'              => '555-010-1111',
		'vet_authorization'      => 'yes',
		'waiver_agree'           => '1',
		'signed_date'            => gmdate( 'Y-m-d' ),
	);
	return paw_with( array( 'pets' => array( array() ) ), $flat );
}

/**
 * Overrides for a submission: per-pet field ids go to pets[$n], everything else to the booking.
 * A 'pets' key replaces the pet list.
 */
function paw_with( array $params, array $over, int $n = 0 ): array {
	$per_pet = \Pawfectly\Fields::pet_fields();
	foreach ( $over as $key => $value ) {
		if ( 'pets' === $key ) {
			$params['pets'] = $value;
		} elseif ( isset( $per_pet[ $key ] ) ) {
			$params['pets'][ $n ][ $key ] = $value;
		} else {
			$params[ $key ] = $value;
		}
	}
	return $params;
}
