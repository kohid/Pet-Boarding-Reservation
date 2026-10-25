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
		AccountRest::init();
		Uploads::init();
		Shortcode::init();
		Pages::init();
		UserMenu::init();
		Profile::init();
		MyBookings::init();
		Updater::init(); // Not admin-only: WordPress cron runs the update check too.
		if ( is_admin() ) {
			Admin::init();
			Settings::init();
		}
	}
}
