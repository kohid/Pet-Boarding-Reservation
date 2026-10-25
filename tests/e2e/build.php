<?php
/**
 * Renders the real form template into tests/e2e/out/index.html with the real CSS and JS,
 * so form-e2e.js can drive it in Chromium with the REST endpoint mocked.
 * Run: php tests/e2e/build.php
 */
define( 'PAW_URL', '/' );
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__ ) . '/wp-db-stubs.php';
require dirname( __DIR__ ) . '/user-stubs.php';
function get_permalink( $id = 0 ) { return '/tests/e2e/out/index.html'; }
function home_url( $p = '' ) { return '/' . ltrim( $p, '/' ); }

use Pawfectly\Settings;
use Pawfectly\Shortcode;

$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';

// `php tests/e2e/build.php signed-in` writes out/signed-in.html for a signed-in owner.
$signed_in = in_array( 'signed-in', $argv, true );
if ( $signed_in ) {
	$jay             = paw_test_user( 'jay@example.com', 'some pass1', array( 'paw_customer' ) );
	$jay->first_name = 'Jay';
	$jay->last_name  = 'Miralles';
	$jay->display_name = 'Jay Miralles';
	update_user_meta( $jay->ID, 'paw_phone', '555 000 1111' );
	wp_set_current_user( $jay->ID );
}

$config            = Shortcode::config();
$config['restUrl'] = '/mock/reservations';
$config['nonceUrl'] = '/mock/nonce';
$config['today']   = gmdate( 'Y-m-d' );
$config['maxTotal'] = 200000; // Small, so the e2e test can exercise the combined-size check.
$config['account']['loginRest'] = '/mock/login';
$config['rates'] = array( 'small' => 30.0, 'large' => 40.0, 'other' => null ); // Nightly boarding rates for the estimate checks.

$html = Shortcode::markup( Settings::get() );
$page = '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pawfectly form (e2e)</title>
<link rel="stylesheet" href="/assets/vendor/flatpickr/flatpickr.min.css">
<link rel="stylesheet" href="/assets/css/form.css">
<style>body{margin:0}</style>
</head>
<body class="elementor-kit-5">
' . $html . '
<style>/* A theme that styles every input, like the one in the screenshot. */ input[type=text],input[type=email],input[type=tel],select,textarea{border:1px solid #000;background:#fff;border-radius:2px}</style>
<style>/* Elementor Site Settings → Form Fields (kit rule, specificity 0,3,1). */ .elementor-kit-5 input:not([type="button"]):not([type="submit"]),.elementor-kit-5 textarea,.elementor-kit-5 select{border:2px solid #000;border-radius:6px;background:#fff}</style>
<script>window.PAW_FORM = ' . json_encode( $config ) . ';</script>
<script src="/assets/vendor/flatpickr/flatpickr.min.js"></script>
<script src="/assets/js/signature-pad.js"></script>
<script src="/assets/js/form.js"></script>
<script src="/assets/js/password.js"></script>
</body>
</html>
';

@mkdir( __DIR__ . '/out', 0755, true );
$file = $signed_in ? 'signed-in.html' : 'index.html';
file_put_contents( __DIR__ . '/out/' . $file, $page );
echo "wrote tests/e2e/out/$file\n";
