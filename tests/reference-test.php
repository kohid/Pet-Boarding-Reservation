<?php
/**
 * Reservation references look like PAW-7K3QX9 and avoid look-alike characters.
 * Run: php tests/reference-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Reference;

$refs = array();
for ( $i = 0; $i < 1000; $i++ ) {
	$refs[] = Reference::generate();
}
t( 'every generated reference is valid', count( array_filter( $refs, array( Reference::class, 'is_valid' ) ) ) === 1000 );
t( 'generated references are (almost always) unique', count( array_unique( $refs ) ) >= 990 );
t( 'no look-alike characters', ! preg_match( '/[O0I1L]/', implode( '', array_map( fn( $r ) => substr( $r, 4 ), $refs ) ) ) );
t( 'accepts a known good reference', Reference::is_valid( 'PAW-7K3QX9' ) );
t( 'rejects look-alikes', ! Reference::is_valid( 'PAW-O0I1LL' ) );
t( 'rejects lowercase', ! Reference::is_valid( 'paw-ABCDEF' ) );
t( 'rejects short', ! Reference::is_valid( 'PAW-ABCDE' ) );

done();
