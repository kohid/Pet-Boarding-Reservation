<?php
/**
 * The login page: where people land after signing in, and when wp-login.php hands over to it.
 * Run: php tests/login-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
function get_permalink( $id = 0 ) { return 'https://example.test/login/'; }
function shortcode_atts( $defaults, $atts, $tag = '' ) { return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) ); }
function wp_enqueue_style( ...$a ) {}
function wp_enqueue_script( ...$a ) {}
function wp_add_inline_script( ...$a ) {}

use Pawfectly\Activator;
use Pawfectly\Dashboard;
use Pawfectly\Login;
use Pawfectly\MyBookings;

$page = static function ( string $tag, string $url ): void {
	set_transient( 'paw_pg_' . md5( $tag ), $url );
};
$page( Login::TAG, 'https://example.test/login/' );
$page( Dashboard::TAG, 'https://example.test/dashboard/' );
$page( MyBookings::TAG, 'https://example.test/my-bookings/' );

$cust  = paw_test_user( 'jay@example.com', 'pass word1', array( 'paw_customer' ) );
$staff = paw_test_user( 'desk@example.com', 'pass word1', array( 'paw_staff' ), array( Activator::CAPABILITY => true ) );

t( 'staff land on the dashboard', 'https://example.test/dashboard/' === Login::home_for( $staff ) );
t( 'owners land on My Bookings', 'https://example.test/my-bookings/' === Login::home_for( $cust ) );
t( 'redirect_to on this site wins', 'https://example.test/book/' === Login::target( 'https://example.test/book/', $cust ) );
t( 'redirect_to on another site is ignored', 'https://example.test/my-bookings/' === Login::target( 'https://evil.test/x', $cust ) );
t( 'empty redirect_to → home', 'https://example.test/dashboard/' === Login::target( '', $staff ) );

t( 'login url carries redirect_to', str_starts_with( Login::url_for( 'https://example.test/book/' ), 'https://example.test/login/?redirect_to=' ) );

$to = Login::wp_login_redirect( array(), 'GET' );
t( 'wp-login.php → login page', is_string( $to ) && str_starts_with( $to, 'https://example.test/login/' ) );
t( 'redirect_to is carried over', str_contains( (string) Login::wp_login_redirect( array( 'redirect_to' => 'https://example.test/wp-admin/' ), 'GET' ), 'redirect_to=' . rawurlencode( 'https://example.test/wp-admin/' ) ) );
t( 'register opens Create account', str_contains( (string) Login::wp_login_redirect( array( 'action' => 'register' ), 'GET' ), 'tab=signup' ) );
foreach ( array( 'lostpassword', 'rp', 'resetpass', 'logout', 'postpass', 'confirmaction' ) as $action ) {
	t( "wp-login.php?action=$action keeps working", null === Login::wp_login_redirect( array( 'action' => $action ), 'GET' ) );
}
t( 'posted logins (other plugins, the app) are left alone', null === Login::wp_login_redirect( array(), 'POST' ) );
t( 'interim login (session expired in wp-admin) is left alone', null === Login::wp_login_redirect( array( 'interim-login' => '1' ), 'GET' ) );
$GLOBALS['paw_filters']['paw_redirect_wp_login'] = fn() => false;
t( 'a filter can turn the redirect off (two-factor plugins)', null === Login::wp_login_redirect( array(), 'GET' ) );
t( 'the filter also leaves wp_login_url() alone', 'https://example.test/wp-login.php' === Login::login_url( 'https://example.test/wp-login.php', '' ) );
unset( $GLOBALS['paw_filters']['paw_redirect_wp_login'] );
t( 'wp_login_url() points to the login page', str_starts_with( Login::login_url( 'https://example.test/wp-login.php', 'https://example.test/wp-admin/' ), 'https://example.test/login/?redirect_to=' ) );

$out = Login::render();
t( 'signed out: sign in and create account tabs', str_contains( $out, 'data-paw-tab="signin"' ) && str_contains( $out, 'data-paw-tab="signup"' ) );
t( 'no separate staff sign-in line', ! str_contains( $out, 'Pawfectly staff' ) && ! str_contains( $out, 'staff sign-in' ) );
t( 'remember me', str_contains( $out, 'name="remember"' ) );
t( 'email or username', str_contains( $out, 'Email or username' ) );
$_GET['tab'] = 'signup';
t( '?tab=signup opens Create account', str_contains( Login::render(), 'data-paw-start="signup"' ) );
$_GET['redirect_to'] = 'https://evil.test/"><script>';
t( 'redirect_to is passed on escaped', ! str_contains( Login::render(), '"><script>' ) );
unset( $_GET['tab'], $_GET['redirect_to'] );

$menu = \Pawfectly\UserMenu::render();
t( 'account menu: Sign In goes to the login page', str_contains( $menu, 'href="https://example.test/login/?redirect_to=' ) && ! str_contains( $menu, 'wp-login.php' ) );
t( 'account menu: Sign Up opens Create account', str_contains( $menu, 'tab=signup' ) );
$GLOBALS['paw_options']['admin_email'] = 'boss@example.com';
$boss = paw_test_user( 'boss@example.com', 'pass word1', array( 'administrator' ) );
wp_set_current_user( $boss->ID );
$menu = \Pawfectly\UserMenu::render();
t( 'account menu: Admin Panel for the admin-email account', str_contains( $menu, '>Admin Panel</a>' ) && str_contains( $menu, 'href="https://example.test/wp-admin/"' ) );
t( 'account menu: Settings opens the dashboard view', str_contains( $menu, 'dashboard/?paw_view=settings' ) );
wp_set_current_user( $staff->ID );
t( 'account menu: no Admin Panel for staff', ! str_contains( \Pawfectly\UserMenu::render(), 'Admin Panel' ) );

wp_set_current_user( $cust->ID );
$in = Login::render();
t( 'signed in: continue link and no forms', str_contains( $in, 'https://example.test/my-bookings/' ) && ! str_contains( $in, 'data-paw-form' ) );

$page( Login::TAG, '' );
wp_set_current_user( 0 );
t( 'no login page: the menu has no Sign Up (wp-login.php cannot open it)', ! str_contains( \Pawfectly\UserMenu::render(), 'Sign Up' ) );
t( 'no login page: wp-login.php is left alone', null === Login::wp_login_redirect( array(), 'GET' ) );
t( 'no login page: url_for falls back to wp-login.php', str_starts_with( Login::url_for( '' ), 'https://example.test/wp-login.php' ) );

done();
