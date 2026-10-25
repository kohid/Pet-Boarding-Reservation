<?php
/**
 * Turning clean data into a table row, and the safety of the list query.
 * Run: php tests/reservations-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
function fixture_rmrf_simple( string $p ): void { if ( '' === $p || ! file_exists( $p ) ) { return; } if ( is_dir( $p ) ) { foreach ( array_diff( scandir( $p ), array( '.', '..' ) ) as $f ) { fixture_rmrf_simple( "$p/$f" ); } rmdir( $p ); } else { unlink( $p ); } }

use Pawfectly\Reservations;

$clean = array(
	'columns' => array(
		'owner_first_name' => 'Zoë',
		'owner_last_name'  => "O'Brien",
		'owner_email'      => 'zoe@example.com',
		'owner_phone'      => '555 010 2030',
		'pet_name'         => 'Biscuit 🐶',
		'pet_type'         => 'dog',
		'check_in'         => '2026-10-28',
		'check_out'        => '2026-10-30',
	),
	'details' => array( 'pet_breed' => 'Beagle', 'behavior_notes' => "Loves \"squeaky\" toys\nShy" ),
);
$files = array( array( 'name' => 'Rabies.pdf', 'path' => '2026/10/PAW-7K3QX9-ab.pdf', 'mime' => 'application/pdf', 'size' => 1234 ) );
$row   = Reservations::build_row( $clean, 'PAW-7K3QX9', $files, '2026/10/PAW-7K3QX9-sig-cd.png', '203.0.113.9', '2026-10-25 14:00:00' );

t( 'columns copied', "O'Brien" === $row['owner_last_name'] && 'dog' === $row['pet_type'] && '2026-10-30' === $row['check_out'] );
t( 'no grooming day: stored as NULL (DATE column)', array_key_exists( 'appointment_date', Reservations::build_row( array( 'columns' => $clean['columns'] + array( 'appointment_date' => '' ), 'details' => array() ), 'PAW-7K3QX8', array(), '', '', '2026-10-25 14:00:00' ) ) && null === Reservations::build_row( array( 'columns' => $clean['columns'] + array( 'appointment_date' => '' ), 'details' => array() ), 'PAW-7K3QX8', array(), '', '', '2026-10-25 14:00:00' )['appointment_date'] );
t( 'a grooming day is kept', '2026-10-29' === Reservations::build_row( array( 'columns' => $clean['columns'] + array( 'appointment_date' => '2026-10-29' ), 'details' => array() ), 'PAW-7K3QX8', array(), '', '', '2026-10-25 14:00:00' )['appointment_date'] );
t( 'reference and pending status', 'PAW-7K3QX9' === $row['reference'] && 'pending' === $row['status'] );
t( 'details JSON round-trips emoji, quotes and newlines', $clean['details'] === json_decode( $row['details'], true ) );
t( 'pet name with emoji kept', 'Biscuit 🐶' === $row['pet_name'] );
t( 'files JSON', $files === json_decode( $row['files'], true ) );
t( 'signature fields', '2026/10/PAW-7K3QX9-sig-cd.png' === $row['signature_path'] && '203.0.113.9' === $row['signed_ip'] && '2026-10-25 14:00:00' === $row['signed_at'] );
t( 'status log starts at pending', array( array( 'status' => 'pending', 'user_id' => 0, 'at' => '2026-10-25 14:00:00' ) ) === json_decode( $row['status_log'], true ) );
t( 'timestamps', '2026-10-25 14:00:00' === $row['created_at'] && '2026-10-25 14:00:00' === $row['updated_at'] );
t( 'six statuses', array( 'pending', 'confirmed', 'declined', 'checked_in', 'completed', 'cancelled' ) === array_keys( Reservations::STATUSES ) );

// Insert, find and status changes against the in-memory database.
$id    = Reservations::insert( $row );
$found = Reservations::find( $id );
t( 'insert returns the new id', 1 === $id );
t( 'find decodes JSON columns', is_array( $found ) && 'Beagle' === $found['details']['pet_breed'] && 'Rabies.pdf' === $found['files'][0]['name'] && 'pending' === $found['status_log'][0]['status'] );
t( 'find casts id', 1 === $found['id'] );
t( 'find of a missing id', null === Reservations::find( 99 ) );
t( 'set_status appends to the log', Reservations::set_status( $id, 'confirmed', 7 ) && 'confirmed' === Reservations::find( $id )['status'] && 7 === Reservations::find( $id )['status_log'][1]['user_id'] );
t( 'set_status rejects unknown status', ! Reservations::set_status( $id, 'teleported', 7 ) && 'confirmed' === Reservations::find( $id )['status'] );
t( 'set_notes', Reservations::set_notes( $id, "Kennel 4\n<b>quiet</b>" ) && "Kennel 4\nquiet" === Reservations::find( $id )['staff_notes'] );
$wpdb->fail_insert = true;
t( 'insert failure returns false', false === Reservations::insert( $row ) );
$wpdb->fail_insert = false;

// Deleting removes the row and its private files.
$dir = \Pawfectly\Uploads::ensure_private_dir();
@mkdir( $dir . '/2026/10', 0755, true );
file_put_contents( $dir . '/2026/10/PAW-DDDDDD-a.pdf', 'pdf' );
file_put_contents( $dir . '/2026/10/PAW-DDDDDD-sig-b.png', 'png' );
$del_id = Reservations::insert( Reservations::build_row( $clean, 'PAW-DDDDDD', array( array( 'name' => 'a.pdf', 'path' => '2026/10/PAW-DDDDDD-a.pdf', 'mime' => 'application/pdf', 'size' => 3 ) ), '2026/10/PAW-DDDDDD-sig-b.png', '203.0.113.9', '2026-10-25 14:00:00' ) );
t( 'delete succeeds', Reservations::delete( $del_id ) );
t( 'deleted row is gone', null === Reservations::find( $del_id ) );
t( 'deleted files are gone', ! file_exists( $dir . '/2026/10/PAW-DDDDDD-a.pdf' ) && ! file_exists( $dir . '/2026/10/PAW-DDDDDD-sig-b.png' ) );
t( 'deleting a missing id fails', ! Reservations::delete( 999 ) );

// The list query builds safe SQL.
$wpdb->queries   = array();
$wpdb->var_queue = array( 1 );
$res = Reservations::query( array( 'search' => "50%_O'Brien", 'status' => 'pending', 'orderby' => 'check_in; DROP TABLE x', 'order' => 'sideways', 'from' => '2026-10-01', 'to' => 'nonsense' ) );
$sql = implode( "\n", $wpdb->queries );
t( 'search wildcards escaped', str_contains( $sql, '50\\\\%\\\\_O' ) );
t( 'unknown orderby falls back to check_in', str_contains( $sql, 'ORDER BY check_in ASC' ) && ! str_contains( $sql, 'DROP' ) );
t( 'status filter', str_contains( $sql, "status = 'pending'" ) );
t( 'valid from date used, invalid to date ignored', str_contains( $sql, "check_in >= '2026-10-01'" ) && ! str_contains( $sql, 'nonsense' ) );
t( 'unknown status ignored', ! str_contains( implode( '', ( function () use ( $wpdb ) {
	$wpdb->queries = array();
	Reservations::query( array( 'status' => "x' OR 1=1" ) );
	return $wpdb->queries;
} )() ), 'OR 1=1' ) );
t( 'query returns rows and total', 1 === $res['total'] && is_array( $res['rows'] ) );

fixture_rmrf_simple( $GLOBALS['paw_upload_base'] ?? '' );
done();
