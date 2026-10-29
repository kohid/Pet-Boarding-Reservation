<?php
/**
 * Renders the public events pages into tests/e2e/out/ with the real CSS and JS:
 * calendar.html ([pawfectly_calendar], December 2026), events.html ([pawfectly_events]),
 * event.html (the X-Mas Party page, with its sign-up form), event-ticket.html (an event with a ticket link)
 * and event-parts.html (an Elementor-style page from EventFields values and [pawfectly_event] parts). events-e2e.js serves them with events-router.php.
 * Run: php tests/e2e/build-events.php
 */
require __DIR__ . '/events-fixtures.php';

use Pawfectly\Calendar;
use Pawfectly\EventFields;
use Pawfectly\EventPage;
use Pawfectly\EventShortcode;
use Pawfectly\Events;
use Pawfectly\EventsList;

$xmas = paw_e2e_seed();

$page = static function ( string $title, string $body ): string {
	$body = str_replace( 'https://example.test/', '/', $body );
	return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . ' (e2e)</title>
<link rel="stylesheet" href="/assets/css/calendar.css"><style>body{margin:0;padding:24px 16px;background:#f7f5f2}main{max-width:1100px;margin:0 auto}</style>
</head><body><main>' . $body . '</main><script src="/assets/js/calendar.js"></script><script src="/assets/js/signup.js"></script></body></html>';
};

// The Elementor-style page: the theme's own font and widgets, filled from the tags' values and the shortcode parts.
function get_the_ID() { return $GLOBALS['paw_e2e_xmas']; }
function is_singular( $t = '' ) { return true; }
function current_user_can( $cap ) { return false; }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
$GLOBALS['paw_e2e_xmas'] = $xmas;
$xev   = Events::get( $xmas );
$f     = static fn( string $field, array $o = array() ): string => esc_html( EventFields::value( $xev, $field, $o ) );
$part  = static fn( string $p ): string => EventShortcode::render( array( 'part' => $p ) );
$parts = '<div class="el-page" style="font-family:Georgia,serif;color:#333">'
	. '<p class="el-date">' . $f( 'date_line' ) . '</p><h1 class="el-title">' . $f( 'emoji' ) . ' ' . $f( 'title' ) . '</h1><p>' . $f( 'tagline' ) . '</p>'
	. '<p class="el-facts">' . $f( 'time_range' ) . ' · <a href="' . esc_url( EventFields::link( $xev, 'map' ) ) . '">' . $f( 'where' ) . '</a> · ' . $f( 'price_text' ) . '</p>'
	. '<a class="el-btn" href="' . esc_attr( EventFields::link( $xev, 'tickets' ) ) . '" style="background:' . esc_attr( EventFields::color( $xev ) ) . '">' . $f( 'ticket_text' ) . '</a>'
	. $part( 'description' ) . $part( 'addcal' )
	. '<h2>' . $f( 'section_heading', array( 'section' => 'sessions' ) ) . '</h2>' . $part( 'sessions' )
	. '<h2>' . $f( 'section_heading', array( 'section' => 'includes' ) ) . '</h2>' . $part( 'includes' )
	. '<h2>' . $f( 'section_heading', array( 'section' => 'goodies' ) ) . '</h2>' . $part( 'goodies' )
	. '<h2>' . $f( 'section_heading', array( 'section' => 'good_to_know' ) ) . '</h2>' . $part( 'good_to_know' )
	. '<h2>' . $f( 'section_heading', array( 'section' => 'closing' ) ) . '</h2>' . $part( 'signup' )
	. '</div>';

$_GET['paw_cal'] = '2026-12';
$files           = array(
	'calendar.html' => $page( 'Event Calendar', Calendar::render( array() ) ),
	'events.html'   => $page( 'Events', EventsList::render( array() ) ),
	'event.html'    => $page( 'X-Mas Party', EventPage::body( Events::get( $xmas ) ) ),
	'event-parts.html'  => $page( 'X-Mas Party (Elementor)', $parts ),
	'event-ticket.html' => $page( 'Ticketed gala', EventPage::body( Events::get( $GLOBALS['paw_e2e_gala'] ) ) ),
);
@mkdir( __DIR__ . '/out', 0755, true );
foreach ( $files as $name => $html ) {
	file_put_contents( __DIR__ . '/out/' . $name, $html );
	echo "wrote tests/e2e/out/$name\n";
}
