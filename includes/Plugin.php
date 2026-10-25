<?php
/**
 * Wires every part of the plugin into WordPress.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function init(): void {
		load_plugin_textdomain( 'pawfectly-reservations', false, dirname( plugin_basename( PAW_FILE ) ) . '/languages' );
		Rest::init();
		Uploads::init();
		Shortcode::init();
		if ( is_admin() ) {
			Admin::init();
			Settings::init();
		}
	}
}
