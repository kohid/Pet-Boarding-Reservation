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
		Access::init();
		Rest::init();
		AccountRest::init();
		Accounts::init();
		StaffRest::init();
		Events::init();
		Signups::init();
		Calendar::init();
		EventsList::init();
		EventPage::init();
		Uploads::init();
		Shortcode::init();
		Pages::init();
		Login::init();
		UserMenu::init();
		Profile::init();
		MyBookings::init();
		Dashboard::init();
		Updater::init(); // Not admin-only: WordPress cron runs the update check too.
		if ( is_admin() ) {
			Admin::init();
			Settings::init();
		}
	}
}
