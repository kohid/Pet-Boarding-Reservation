<?php
/**
 * The table schema matches spec §4.1 and keeps dbDelta's formatting rules.
 * Run: php tests/activator-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Activator;

$sql = Activator::schema_sql( 'wp_paw_reservations', 'DEFAULT CHARSET=utf8mb4' );

t( 'creates the named table', str_contains( $sql, 'CREATE TABLE wp_paw_reservations (' ) );
foreach ( array(
	'id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
	'reference VARCHAR(12) NOT NULL',
	"status VARCHAR(20) NOT NULL DEFAULT 'pending'",
	'owner_first_name VARCHAR(60) NOT NULL',
	'owner_last_name VARCHAR(60) NOT NULL',
	'owner_email VARCHAR(100) NOT NULL',
	'owner_phone VARCHAR(30) NOT NULL',
	'pet_name VARCHAR(60) NOT NULL',
	'pet_type VARCHAR(10) NOT NULL',
	'check_in DATE NOT NULL',
	'check_out DATE NOT NULL',
	'details LONGTEXT NOT NULL',
	'files LONGTEXT NOT NULL',
	'signature_path VARCHAR(190) NOT NULL',
	'signed_at DATETIME NOT NULL',
	'signed_ip VARCHAR(45) NOT NULL',
	'staff_notes TEXT NULL',
	'status_log LONGTEXT NOT NULL',
	'created_at DATETIME NOT NULL',
	'updated_at DATETIME NOT NULL',
	'PRIMARY KEY  (id)',
	'UNIQUE KEY reference (reference)',
	'KEY status (status)',
	'KEY check_in (check_in)',
	'KEY owner_email (owner_email)',
	'user_id BIGINT(20) UNSIGNED NULL',
	'KEY user_id (user_id)',
	"booking_type VARCHAR(12) NOT NULL DEFAULT 'boarding'",
	'appointment_date DATE NULL',
	"service VARCHAR(255) NOT NULL DEFAULT ''",
	'pet_count TINYINT UNSIGNED NOT NULL DEFAULT 1',
) as $needle ) {
	t( "has: $needle", str_contains( $sql, $needle ) );
}
t( 'ends with the charset', str_ends_with( trim( $sql ), ') DEFAULT CHARSET=utf8mb4;' ) );
t( 'capability name', Activator::CAPABILITY === 'pawfectly_manage_reservations' );

done();
