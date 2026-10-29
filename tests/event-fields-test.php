<?php
/**
 * EventFields: every Add Event field as the Elementor tags and the shortcode read it, the event
 * being shown, drafts, and the member code that never comes out.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
function get_the_ID() { return $GLOBALS['paw_the_id']; }

use Pawfectly\Accounts;
use Pawfectly\Activator;
use Pawfectly\EventFields;
use Pawfectly\Events;
use Pawfectly\Signups;

wp_insert_term( 'Holiday party', 'paw_event_cat', array( 'slug' => 'holiday' ) );
update_term_meta( get_term_by( 'slug', 'holiday', 'paw_event_cat' )->term_id, '_paw_color', '#e8a33a' );
$base = array(
	'title' => 'X-Mas Party', 'tagline' => 'A jingle-bark holiday party', 'emoji' => '🎄', 'category' => 'holiday', 'photo_id' => '7',
	'start_date' => '2026-12-19', 'start_time' => '10:00', 'end_date' => '2026-12-19', 'end_time' => '14:00',
	'venue' => 'Paw-radise Indoor Dog Park', 'address' => 'Estero, FL', 'price' => '15', 'spots' => '3', 'coupon' => 'PawPark',
	'note' => 'FREE for members — text 239-420-7418', 'description' => '<p>Deck the <strong>paws</strong>!</p><p>Treats &amp; fun.</p>', 'status' => 'publish',
	'sections' => array(
		'sessions' => array( 'heading' => "Pick Your Pup's Session", 'intro' => 'Two sessions', 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs' ), array( 'from' => '12:00', 'to' => '14:00', 'name' => 'Session 2', 'who' => 'Large dogs' ) ) ),
		'includes' => array( 'heading' => 'Everything Your {price} Ticket Includes', 'intro' => 'One {price} ticket', 'rows' => array( array( 'emoji' => '📸', 'text' => 'Photo booth' ) ) ),
		'goodies'  => array( 'rows' => array( array( 'emoji' => '🎅', 'title' => 'Santa Paws', 'text' => 'Photos with Santa' ) ) ),
		'closing'  => array( 'heading' => 'Reserve Your Spot Today!', 'intro' => 'Register in seconds' ),
	),
);
$ev = Events::get( Events::save( Events::sanitize( $base ) ) );
$v  = static fn( string $f, array $o = array() ): string => EventFields::value( $ev, $f, $o );

// Text.
t( 'basics', 'X-Mas Party' === $v( 'title' ) && 'A jingle-bark holiday party' === $v( 'tagline' ) && '🎄' === $v( 'emoji' ) && 'Holiday party' === $v( 'category' ) );
t( 'dates and times, default formats', 'Saturday, December 19th, 2026' === $v( 'start_date' ) && '10:00 AM' === $v( 'start_time' ) && 'Saturday, December 19th, 2026' === $v( 'end_date' ) && '2:00 PM' === $v( 'end_time' ) );
t( 'dates and times, your format', 'Dec 19' === $v( 'start_date', array( 'format' => 'M j' ) ) && '14:00' === $v( 'end_time', array( 'format' => 'H:i' ) ) );
t( 'the combined lines', 'One Day Only · Saturday, December 19th, 2026' === $v( 'date_line' ) && 'Saturday, December 19th, 2026' === $v( 'day' ) && '10:00 AM – 2:00 PM' === $v( 'time_range' ) && str_contains( $v( 'summary' ), '$15 per dog' ) );
t( 'where', 'Paw-radise Indoor Dog Park' === $v( 'venue' ) && 'Estero, FL' === $v( 'address' ) && 'Paw-radise Indoor Dog Park, Estero, FL' === $v( 'where' ) );
t( 'price', '15' === $v( 'price' ) && '$15 per dog' === $v( 'price_text' ) && '' === $v( 'free' ) && 'Get Tickets — $15' === $v( 'ticket_text' ) );
t( 'note and description as text', 'FREE for members — text 239-420-7418' === $v( 'note' ) && 'Deck the paws! Treats & fun.' === $v( 'description' ) );
t( 'spots per session', '3' === $v( 'spots' ) );
t( 'section heading and intro, {price} filled in', "Pick Your Pup's Session" === $v( 'section_heading', array( 'section' => 'sessions' ) ) && 'Everything Your $15 Ticket Includes' === $v( 'section_heading', array( 'section' => 'includes' ) ) && 'One $15 ticket' === $v( 'section_intro', array( 'section' => 'includes' ) ) && 'Reserve Your Spot Today!' === $v( 'section_heading', array( 'section' => 'closing' ) ) && '' === $v( 'section_heading', array( 'section' => 'nope' ) ) );
t( 'an unknown field is empty', '' === $v( 'nope' ) );

t( 'include and goodie row fields', '📸' === EventFields::row_value( $ev, 'includes', 1, 'emoji' ) && 'Photo booth' === EventFields::row_value( $ev, 'includes', 1, 'text' ) && 'Santa Paws' === EventFields::row_value( $ev, 'goodies', 1, 'title' ) && 'Photos with Santa' === EventFields::row_value( $ev, 'goodies', 1, 'text' ) );

// Spots left.
Signups::create( $ev, Signups::sanitize( $ev, array( 'first_name' => 'Bo', 'last_name' => 'L', 'email' => 'bo@example.com', 'phone' => '239-555-0100', 'session' => '1', 'dogs' => array( array( 'name' => 'A' ), array( 'name' => 'B' ), array( 'name' => 'C' ) ), 'agree' => '1' ) ), 0, '' );
t( 'spots left per session', '3 spots left' === $v( 'spots_left', array( 'session' => 1 ) ) && 'Full' === $v( 'spots_left', array( 'session' => 2 ) ) && '' === $v( 'spots_left', array( 'session' => 5 ) ) );

// Repeater rows, one field at a time (row numbers start at 1).
$r = static fn( string $section, int $row, string $field, array $o = array() ): string => EventFields::row_value( $ev, $section, $row, $field, $o );
t( 'session row fields', '10:00 AM' === $r( 'sessions', 1, 'from' ) && '2:00 PM' === $r( 'sessions', 2, 'to' ) && '14:00' === $r( 'sessions', 2, 'to', array( 'format' => 'H:i' ) ) && '10:00 AM – 12:00 PM' === $r( 'sessions', 1, 'time' ) && 'Session 2' === $r( 'sessions', 2, 'name' ) && 'Large dogs' === $r( 'sessions', 2, 'who' ) );
t( 'session row: spots left', '3 spots left' === $r( 'sessions', 1, 'spots_left' ) );
t( 'a row that is not there, or a field it does not have, is empty', '' === $r( 'sessions', 3, 'name' ) && '' === $r( 'sessions', 1, 'title' ) && '' === $r( 'nope', 1, 'text' ) );
t( 'every repeater lists its row fields', array( 'sessions' => array( 'from', 'to', 'time', 'name', 'who', 'spots_left' ), 'includes' => array( 'emoji', 'text' ), 'goodies' => array( 'emoji', 'title', 'text' ), 'good_to_know' => array( 'emoji', 'text' ) ) === EventFields::ROW_FIELDS );

// Free, no price, no limit.
$free = Events::get( Events::save( Events::sanitize( array_merge( $base, array( 'title' => 'Agility', 'free' => true, 'spots' => '', 'coupon' => '' ) ) ) ) );
t( 'a free event', 'Free' === EventFields::value( $free, 'free' ) && 'Yes!' === EventFields::value( $free, 'free', array( 'text' => 'Yes!' ) ) && '' === EventFields::value( $free, 'price' ) && 'Free' === EventFields::value( $free, 'price_text' ) && '' === EventFields::value( $free, 'spots' ) && '' === EventFields::value( $free, 'spots_left', array( 'session' => 1 ) ) );
$open = Events::get( Events::save( Events::sanitize( array_merge( $base, array( 'title' => 'Walk', 'price' => '', 'photo_id' => '0', 'ticket_url' => 'https://tickets.example/walk' ) ) ) ) );
t( 'no price: the button just says Get Tickets', 'Get Tickets' === EventFields::value( $open, 'ticket_text' ) && '' === EventFields::value( $open, 'price' ) );

// Links, image, colour.
t( 'tickets / sign-up: the form on the page, or the ticket link', 'https://example.test/event/x-mas-party/#paw-event-reserve' === EventFields::link( $ev, 'tickets' ) && 'https://tickets.example/walk' === EventFields::link( $open, 'tickets' ) && '' === EventFields::link( $ev, 'ticket_url' ) && 'https://tickets.example/walk' === EventFields::link( $open, 'ticket_url' ) );
t( 'event page, calendars and map', 'https://example.test/event/x-mas-party/' === EventFields::link( $ev, 'url' ) && str_contains( EventFields::link( $ev, 'google' ), 'calendar.google.com' ) && str_contains( EventFields::link( $ev, 'outlook' ), 'outlook' ) && str_contains( EventFields::link( $ev, 'ics' ), 'paw_ics=1' ) && 'https://www.google.com/maps/search/?api=1&query=Paw-radise+Indoor+Dog+Park%2C+Estero%2C+FL' === EventFields::link( $ev, 'map' ) && '' === EventFields::link( $ev, 'nope' ) );
t( 'cover photo', array( 'id' => 7, 'url' => 'https://example.test/logo-7.png' ) === EventFields::image( $ev ) && array() === EventFields::image( $open ) );
t( 'category colour', '#e8a33a' === EventFields::color( $ev ) );

// The member code never comes out.
$all = array();
foreach ( EventFields::TEXT as $field ) {
	$all[] = $v( $field );
}
foreach ( EventFields::LINKS as $link ) {
	$all[] = EventFields::link( $ev, $link );
}
t( 'no field gives the member code', ! str_contains( implode( '|', $all ), 'PawPark' ) && '' === $v( 'coupon' ) && ! in_array( 'coupon', EventFields::TEXT, true ) );

// The event being shown.
$GLOBALS['paw_the_id'] = $ev['id'];
t( 'current: the event being shown', $ev['id'] === EventFields::current()['id'] );
$page                  = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Home', 'post_status' => 'publish' ) );
$GLOBALS['paw_the_id'] = $page;
t( 'current: not an event', null === EventFields::current() );
$draft                 = Events::save( Events::sanitize( array_merge( $base, array( 'title' => 'Secret', 'status' => 'draft' ) ) ) );
$GLOBALS['paw_the_id'] = $draft;
t( 'current: drafts are hidden from visitors', null === EventFields::current() );
$desk = paw_test_user( 'desk@example.com', 'desk pass1', array( Accounts::STAFF_ROLE ), array( Activator::CAPABILITY => true ) );
wp_set_current_user( $desk->ID );
t( 'current: staff see drafts (for previews)', $draft === EventFields::current()['id'] );
// The Elementor editor draws a Single template: the event is its Preview Settings event.
$tpl = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_title' => 'Single Event', 'post_status' => 'publish' ) );
update_post_meta( $tpl, '_elementor_page_settings', array( 'preview_type' => 'single/paw_event', 'preview_id' => (string) $ev['id'] ) );
$GLOBALS['paw_the_id'] = $tpl;
t( 'editor: the template previews its Preview Settings event', $ev['id'] === EventFields::current()['id'] );
update_post_meta( $tpl, '_elementor_page_settings', array() );
$latest = Events::save( Events::sanitize( array_merge( $base, array( 'title' => 'Latest party' ) ) ) );
t( 'editor: no preview event set, the latest published event', $latest === EventFields::current()['id'] );
update_post_meta( $tpl, '_elementor_page_settings', array( 'preview_id' => (string) $page ) );
t( 'editor: a preview that is not an event falls back to the latest event', $latest === EventFields::current()['id'] );
$GLOBALS['paw_the_id'] = $draft;
t( 'current: a given id', $ev['id'] === EventFields::current( $ev['id'] )['id'] && null === EventFields::current( $page ) );

done();
