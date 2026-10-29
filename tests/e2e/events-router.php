<?php
/**
 * Router for `php -S` in events-e2e.js: the public event routes run the real plugin code on the
 * fixture events; every other path is served as a file.
 */
$path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$map  = array(
	'/wp-json/pawfectly/v1/events/calendar' => array( \Pawfectly\Calendar::class, 'route' ),
	'/wp-json/pawfectly/v1/events/list'     => array( \Pawfectly\EventsList::class, 'route' ),
	'/wp-json/pawfectly/v1/nonce'           => array( \Pawfectly\Rest::class, 'nonce' ),
);
$url = $_GET;
if ( preg_match( '#^/wp-json/pawfectly/v1/events/(\d+)/signup$#', $path, $m ) ) {
	$map[ $path ] = array( \Pawfectly\SignupRest::class, 'submit' );
	$url['id']    = $m[1];
}
if ( ! isset( $map[ $path ] ) ) {
	return false;
}
require __DIR__ . '/events-fixtures.php';
paw_e2e_seed();
$req          = new WP_REST_Request();
$req->url     = $url;
$req->json    = json_decode( (string) file_get_contents( 'php://input' ), true ) ?: array();
$req->headers = array( 'x_wp_nonce' => $_SERVER['HTTP_X_WP_NONCE'] ?? '' );
$res          = call_user_func( $map[ $path ], $req );
header( 'Content-Type: application/json' );
if ( is_wp_error( $res ) ) {
	http_response_code( $res->get_error_data()['status'] ?? 400 );
	echo json_encode( array( 'code' => $res->get_error_code(), 'message' => $res->get_error_message(), 'data' => $res->get_error_data() ) );
	return true;
}
http_response_code( $res->get_status() );
$html = json_encode( $res->get_data() );
echo str_replace( 'https:\/\/example.test\/', '\/', $html );
