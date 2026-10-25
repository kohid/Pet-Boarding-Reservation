<?php
/**
 * Creates and upgrades the reservations table, the roles and capability, and the private files folder.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public const CAPABILITY = 'pawfectly_manage_reservations';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'paw_reservations';
	}

	public static function activate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::schema_sql( self::table(), $wpdb->get_charset_collate() ) );

		Accounts::add_roles();
		Access::add_developer_role();
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAPABILITY );
		}
		if ( class_exists( Uploads::class ) ) {
			Uploads::ensure_private_dir();
		}
		update_option( 'paw_db_version', PAW_DB_VERSION );
	}

	/** Runs on every load; only does work when the stored schema version lags the constant. */
	public static function maybe_upgrade(): void {
		if ( get_option( 'paw_db_version' ) !== PAW_DB_VERSION ) {
			self::activate();
		}
	}

	/** dbDelta is picky: one column per line and two spaces after PRIMARY KEY. */
	public static function schema_sql( string $table, string $charset ): string {
		return "CREATE TABLE {$table} (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
reference VARCHAR(12) NOT NULL,
status VARCHAR(20) NOT NULL DEFAULT 'pending',
owner_first_name VARCHAR(60) NOT NULL,
owner_last_name VARCHAR(60) NOT NULL,
owner_email VARCHAR(100) NOT NULL,
owner_phone VARCHAR(30) NOT NULL,
pet_name VARCHAR(60) NOT NULL,
pet_type VARCHAR(10) NOT NULL,
check_in DATE NOT NULL,
check_out DATE NOT NULL,
booking_type VARCHAR(12) NOT NULL DEFAULT 'boarding',
appointment_date DATE NULL,
service VARCHAR(255) NOT NULL DEFAULT '',
pet_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
details LONGTEXT NOT NULL,
files LONGTEXT NOT NULL,
signature_path VARCHAR(190) NOT NULL,
signed_at DATETIME NOT NULL,
signed_ip VARCHAR(45) NOT NULL,
user_id BIGINT(20) UNSIGNED NULL,
staff_notes TEXT NULL,
status_log LONGTEXT NOT NULL,
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY reference (reference),
KEY status (status),
KEY check_in (check_in),
KEY owner_email (owner_email),
KEY user_id (user_id)
) {$charset};";
	}
}
