<?php
/**
 * Renders the real form template into tests/e2e/out/index.html with the real CSS and JS,
 * so form-e2e.js can drive it in Chromium with the REST endpoint mocked.
 * Run: php tests/e2e/build.php
 */
define( 'PAW_URL', '/' );
require dirname( __DIR__ ) . '/bootstrap.php';

use Pawfectly\Settings;
use Pawfectly\Shortcode;

$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';

$config            = Shortcode::config();
$config['restUrl'] = '/mock/reservations';
$config['nonceUrl'] = '/mock/nonce';
$config['today']   = gmdate( 'Y-m-d' );
$config['maxTotal'] = 200000; // Small, so the e2e test can exercise the combined-size check.

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
<body>
' . $html . '
<style>/* A theme that styles every input, like the one in the screenshot. */ input[type=text],input[type=email],input[type=tel],select,textarea{border:1px solid #000;background:#fff;border-radius:2px}</style>
<script>window.PAW_FORM = ' . json_encode( $config ) . ';</script>
<script src="/assets/vendor/flatpickr/flatpickr.min.js"></script>
<script src="/assets/js/signature-pad.js"></script>
<script src="/assets/js/form.js"></script>
</body>
</html>
';

@mkdir( __DIR__ . '/out', 0755, true );
file_put_contents( __DIR__ . '/out/index.html', $page );
echo "wrote tests/e2e/out/index.html\n";
