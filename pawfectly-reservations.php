<?php
/**
 * Plugin Name:       Pawfectly Pet Boarding Reservation
 * Description:       A multi-step pet boarding reservation form with vaccination uploads, a signed waiver and a reservations list in wp-admin.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * License:           GPL v2 or later
 * Text Domain:       pawfectly-reservations
 * Update URI:        https://github.com/kohid/Pet-Boarding-Reservation
 *
 * @package Pawfectly
 */

defined( 'ABSPATH' ) || exit;

define( 'PAW_VERSION', '0.1.0' );
define( 'PAW_DB_VERSION', '1' );
define( 'PAW_FILE', __FILE__ );
define( 'PAW_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAW_URL', plugin_dir_url( __FILE__ ) );

// PSR-4-style autoloader: Pawfectly\Foo -> includes/Foo.php.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Pawfectly\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$path = PAW_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( \Pawfectly\Activator::class, 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\Pawfectly\Activator::maybe_upgrade();
		\Pawfectly\Plugin::init();
	}
);
