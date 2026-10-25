<?php
/**
 * Renders the account menu (guest and signed-in) and, once they exist, My Profile and My Bookings
 * into tests/e2e/out/account.html with the real CSS and JS, for account-e2e.js.
 * Run: php tests/e2e/build-account.php
 */
define( 'PAW_URL', '/' );
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__ ) . '/wp-db-stubs.php';
require dirname( __DIR__ ) . '/user-stubs.php';
function get_permalink( $id = 0 ) { return '/tests/e2e/out/account.html'; }
function home_url( $p = '' ) { return '/' . ltrim( $p, '/' ); }
function get_edit_profile_url() { return '/wp-admin/profile.php'; }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_add_inline_script() {}
function wp_localize_script() {}

use Pawfectly\UserMenu;

$guest = UserMenu::render( array( 'align' => 'left' ) );
$jay   = paw_test_user( 'jay@example.com', 'some pass1', array( 'paw_customer' ) );
$jay->display_name = 'Jay Miralles';
wp_set_current_user( $jay->ID );
$member = UserMenu::render();

$extra = '';
foreach ( array( 'Pawfectly\\Profile', 'Pawfectly\\MyBookings' ) as $class ) {
	if ( class_exists( $class ) && file_exists( dirname( __DIR__ ) . '/e2e/build-account-' . strtolower( substr( $class, 10 ) ) . '.inc.php' ) ) {
		$extra .= include dirname( __DIR__ ) . '/e2e/build-account-' . strtolower( substr( $class, 10 ) ) . '.inc.php';
	}
}

$page = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pawfectly account (e2e)</title><link rel="stylesheet" href="/assets/css/account.css">
<style>body{margin:0;font-family:system-ui;background:#f4f4f4} header{display:flex;justify-content:space-between;padding:16px 24px;background:#fff} main{padding:24px}</style>
</head><body>
<header><div id="guest-menu">' . $guest . '</div><div id="member-menu">' . $member . '</div></header>
<main><p style="margin-top:320px"><a href="#" id="outside">Somewhere else</a></p>' . $extra . '</main>
<script src="/assets/js/user-menu.js"></script>
' . ( $extra ? '<script src="/assets/js/account.js"></script>' : '' ) . '
</body></html>';
@mkdir( __DIR__ . '/out', 0755, true );
file_put_contents( __DIR__ . '/out/account.html', $page );
echo "wrote tests/e2e/out/account.html\n";
