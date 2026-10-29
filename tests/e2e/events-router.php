<?php
/**
 * Router for `php -S` in events-e2e.js: the public event routes run the real plugin code on the
 * fixture events; every other path is served as a file.
 */
$path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$map  = array(
	'/wp-json/pawfectly/v1/events/calendar' => array( \Pawfectly\Calendar::class, 'route' ),
	'/wp-json/pawfectly/v1/events/list'     => array( \Pawfectly\EventsList::class, 'route' ),
);
if ( ! isset( $map[ $path ] ) ) {
	return false;
}
require __DIR__ . '/events-fixtures.php';
paw_e2e_seed();
$req      = new WP_REST_Request();
$req->url = $_GET;
$res      = call_user_func( $map[ $path ], $req );
header( 'Content-Type: application/json' );
$html = json_encode( $res->get_data() );
echo str_replace( 'https:\/\/example.test\/', '\/', $html );
