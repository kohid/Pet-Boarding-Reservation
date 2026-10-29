<?php
/**
 * Renders the account menu (guest and signed-in), My Profile (signed out and in) and My Bookings
 * (reservations and event sign-ups) into tests/e2e/out/account.html with the real CSS and JS, for account-e2e.js.
 * Run: php tests/e2e/build-account.php
 */
define( 'PAW_URL', '/' );
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__ ) . '/post-stubs.php';
require dirname( __DIR__ ) . '/wp-db-stubs.php';
require dirname( __DIR__ ) . '/user-stubs.php';
function get_permalink( $id = 0 ) { return '/tests/e2e/out/account.html'; }
function home_url( $p = '' ) { return '/' . ltrim( $p, '/' ); }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_add_inline_script() {}

use Pawfectly\Events;
use Pawfectly\MyBookings;
use Pawfectly\Signups;
use Pawfectly\Profile;
use Pawfectly\Reservations;
use Pawfectly\UserMenu;

$guest_menu  = UserMenu::render( array( 'align' => 'left' ) );
$profile_out = Profile::render();

$jay               = paw_test_user( 'jay@example.com', 'some pass1', array( 'paw_customer' ) );
$jay->first_name   = 'Jay';
$jay->last_name    = 'Miralles';
$jay->display_name = 'Jay Miralles';
update_user_meta( $jay->ID, 'paw_email_verified', '1' );
wp_set_current_user( $jay->ID );
foreach ( array( array( 'PAW-7K3QX9', 'Biscuit', 'confirmed', '2030-01-10' ), array( 'PAW-ABCDEF', 'Luna', 'completed', '2025-06-01' ) ) as $r ) {
	$row            = Reservations::build_row( array( 'columns' => array( 'owner_first_name' => 'Jay', 'owner_last_name' => 'Miralles', 'owner_email' => 'jay@example.com', 'owner_phone' => '555', 'pet_name' => $r[1], 'pet_type' => 'dog', 'check_in' => $r[3], 'check_out' => '2030-01-12' ), 'details' => array() ), $r[0], array(), '', '', '2026-10-25 10:00:00' );
	$row['status']  = $r[2];
	$row['user_id'] = $jay->ID;
	Reservations::insert( $row );
}
// Event sign-ups: one ahead (Cancel), one over.
foreach ( array( array( 'X-Mas Party', '2030-12-19', '🎄' ), array( 'Summer splash', '2020-07-01', '💦' ) ) as $e ) {
	$ev = Events::get( Events::save( Events::sanitize( array( 'title' => $e[0], 'emoji' => $e[2], 'start_date' => $e[1], 'start_time' => '10:00', 'end_date' => $e[1], 'end_time' => '14:00', 'venue' => 'Paw-radise', 'price' => '15', 'status' => 'publish', 'sections' => array( 'sessions' => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs' ) ) ) ) ) ) ) );
	Signups::create( $ev, Signups::sanitize( $ev, array( 'first_name' => 'Jay', 'last_name' => 'Miralles', 'email' => 'jay@example.com', 'phone' => '555-0100', 'session' => '0', 'dogs' => array( array( 'name' => 'Biscuit' ), array( 'name' => 'Luna' ) ), 'agree' => '1' ) ), $jay->ID, '' );
}
// A My Profile page exists (customers can't use wp-admin's profile screen any more).
set_transient( 'paw_pg_' . md5( Profile::TAG ), '/tests/e2e/out/account.html#profile-in' );
$member_menu = UserMenu::render();
$profile_in  = Profile::render();
$bookings    = MyBookings::render();

$config         = Profile::config();
$config['rest'] = '/mock/';

$page = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pawfectly account (e2e)</title><link rel="stylesheet" href="/assets/css/account.css">
<style>body{margin:0;font-family:system-ui;background:#f4f4f4} header{display:flex;justify-content:space-between;padding:16px 24px;background:#fff} main{padding:24px} section{margin-bottom:32px}</style>
<style>/* Elementor Site Settings → Form Fields (kit rule, specificity 0,3,1). */ .elementor-kit-5 input:not([type="button"]):not([type="submit"]),.elementor-kit-5 textarea,.elementor-kit-5 select{border:2px solid #000;border-radius:6px;background:#fff}</style>
</head><body class="elementor-kit-5">
<header><div id="guest-menu">' . $guest_menu . '</div><div id="member-menu">' . $member_menu . '</div></header>
<main>
<section id="profile-out">' . $profile_out . '</section>
<section id="profile-in">' . $profile_in . '</section>
<section id="bookings">' . $bookings . '</section>
<p><a href="#" id="outside">Somewhere else</a></p>
</main>
<script>window.PAW_ACCOUNT = ' . json_encode( $config ) . ';</script>
<script src="/assets/js/user-menu.js"></script>
<script src="/assets/js/account.js"></script>
<script src="/assets/js/password.js"></script>
</body></html>';
@mkdir( __DIR__ . '/out', 0755, true );
file_put_contents( __DIR__ . '/out/account.html', $page );
echo "wrote tests/e2e/out/account.html\n";
