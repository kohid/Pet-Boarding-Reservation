<?php
/**
 * Renders [pawfectly_dashboard] for a staff member into tests/e2e/out/dashboard.html with the real
 * CSS and JS; dashboard-e2e.js mocks the staff REST routes.
 * Run: php tests/e2e/build-dashboard.php
 */
define( 'PAW_URL', '/' );
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__ ) . '/wp-db-stubs.php';
require dirname( __DIR__ ) . '/user-stubs.php';
function get_permalink( $id = 0 ) { return '/tests/e2e/out/dashboard.html'; }
function home_url( $p = '' ) { return '/' . ltrim( $p, '/' ); }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_add_inline_script() {}

use Pawfectly\Accounts;
use Pawfectly\Activator;
use Pawfectly\Dashboard;

$GLOBALS['paw_options']['admin_email'] = 'desk@example.com';
$desk               = paw_test_user( 'desk@example.com', 'desk pass1', array( 'administrator' ) );
$desk->display_name = 'Dana Desk';
wp_set_current_user( $desk->ID );

$html           = Dashboard::render();
$config         = Dashboard::config( 'overview' );
$config['rest'] = '/mock/';

$page = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pawfectly dashboard (e2e)</title><link rel="stylesheet" href="/assets/css/dashboard.css"><style>body{margin:0}</style></head><body>
' . $html . '
<script>window.PAW_DASH = ' . json_encode( $config ) . ';</script>
<script src="/assets/js/dashboard.js"></script>
</body></html>';
@mkdir( __DIR__ . '/out', 0755, true );
file_put_contents( __DIR__ . '/out/dashboard.html', $page );
echo "wrote tests/e2e/out/dashboard.html\n";
