<?php
/**
 * Finding the page that holds a shortcode, so menus and emails can link to it.
 * Run: php tests/pages-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';

use Pawfectly\Pages;

$GLOBALS['paw_posts'] = array(
	10 => '<p>[pawfectly_my_profile_extra]</p>',
	11 => '<!-- wp:shortcode -->[pawfectly_my_profile]<!-- /wp:shortcode -->',
);
function get_post( $id ) { return isset( $GLOBALS['paw_posts'][ $id ] ) ? (object) array( 'ID' => $id, 'post_content' => $GLOBALS['paw_posts'][ $id ] ) : null; }
function get_post_meta( $id, $key = '', $single = false ) { return ''; }
function get_permalink( $id = 0 ) { return "https://example.test/page-$id/"; }
function delete_transient( $k ) { unset( $GLOBALS['paw_transients'][ $k ] ); return true; }

$wpdb->col_queue = array( array( 10, 11 ) );
t( 'finds the page with the exact shortcode', 'https://example.test/page-11/' === Pages::url( 'pawfectly_my_profile' ) );
t( 'search covers Elementor data too', str_contains( end( $wpdb->queries ), '_elementor_data' ) );
$n = count( $wpdb->queries );
t( 'cached: no second query', 'https://example.test/page-11/' === Pages::url( 'pawfectly_my_profile' ) && count( $wpdb->queries ) === $n );
$wpdb->col_queue = array( array() );
t( 'no page gives an empty string', '' === Pages::url( 'pawfectly_my_bookings' ) );
$n = count( $wpdb->queries );
Pages::url( 'pawfectly_my_bookings' );
t( 'a missing page is remembered too', count( $wpdb->queries ) === $n );
Pages::forget();
$wpdb->col_queue = array( array( 11 ) );
Pages::url( 'pawfectly_my_bookings' );
t( 'forget clears the cache', count( $wpdb->queries ) === $n + 1 );
t( 'eight shortcodes listed', array( 'pawfectly_reservation_form', 'pawfectly_user_menu', 'pawfectly_login', 'pawfectly_my_profile', 'pawfectly_my_bookings', 'pawfectly_dashboard', 'pawfectly_calendar', 'pawfectly_events' ) === array_keys( Pages::TAGS ) );

t( 'the event calendar and events list are offered as pages', isset( \Pawfectly\Pages::TAGS['pawfectly_calendar'], \Pawfectly\Pages::TAGS['pawfectly_events'] ) && 'Event Calendar' === \Pawfectly\Pages::TAGS['pawfectly_calendar'][0] && 'Events' === \Pawfectly\Pages::TAGS['pawfectly_events'][0] );

done();
