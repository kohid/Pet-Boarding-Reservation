<?php
/**
 * [pawfectly_dashboard]: visitors are asked to sign in, non-staff see "No access", staff get the app shell.
 * Run: php tests/dashboard-render-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';

use Pawfectly\Accounts;
use Pawfectly\Activator;
use Pawfectly\Dashboard;

function get_permalink( $id = 0 ) { return 'https://example.test/dashboard/'; }
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
$GLOBALS['paw_inline'] = '';
function wp_add_inline_script( $h, $js, $pos = 'after' ) { $GLOBALS['paw_inline'] .= $js; }

$GLOBALS['paw_options']['admin_email'] = 'desk@example.com';

$html = Dashboard::render();
t( 'visitor: sign-in link to the WordPress login', str_contains( $html, 'wp-login.php' ) && ! str_contains( $html, 'data-paw-dash' ) );

$owner = paw_test_user( 'zoe@example.com', 'some pass1', array( Accounts::ROLE ) );
wp_set_current_user( $owner->ID );
t( 'owner: no access', str_contains( Dashboard::render(), 'No access' ) );

$desk = paw_test_user( 'desk@example.com', 'desk pass1', array( Accounts::STAFF_ROLE ), array( Activator::CAPABILITY => true ) );
$desk->display_name = 'Desk <Staff>';
wp_set_current_user( $desk->ID );
$html = Dashboard::render( array( 'view' => 'reservations' ) );
t( 'staff: app shell', str_contains( $html, 'data-paw-dash' ) && str_contains( $html, 'paw-dash--full' ) );
t( 'side menu links', str_contains( $html, 'data-paw-nav="overview"' ) && str_contains( $html, 'data-paw-nav="reservations"' ) );
t( 'staff without manage_options: no Settings link', ! str_contains( $html, 'data-paw-nav="settings"' ) );
t( 'staff name escaped', str_contains( $html, 'Desk &lt;Staff&gt;' ) );
t( 'config carries the view and REST base', str_contains( $GLOBALS['paw_inline'], '"view":"reservations"' ) && str_contains( $GLOBALS['paw_inline'], 'pawfectly\/v1\/' ) );
t( 'fullscreen="no" stays in the page column', ! str_contains( Dashboard::render( array( 'fullscreen' => 'no' ) ), 'paw-dash--full' ) );
t( 'unknown view falls back to overview', str_contains( ( function () { $GLOBALS['paw_inline'] = ''; Dashboard::render( array( 'view' => 'nope' ) ); return $GLOBALS['paw_inline']; } )(), '"view":"overview"' ) );

$boss = paw_test_user( 'boss@example.com', 'admin pass', array( 'administrator' ) );
wp_set_current_user( $boss->ID );
t( 'admins get the Settings link', str_contains( Dashboard::render(), 'data-paw-nav="settings"' ) );

done();
