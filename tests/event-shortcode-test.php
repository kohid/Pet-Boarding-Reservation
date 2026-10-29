<?php
/**
 * [pawfectly_event part="…"]: each part's plain markup, empty parts, the sign-up part's three
 * states, id=, drafts, and the member code that never shows.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
function get_the_ID() { return $GLOBALS['paw_the_id']; }
function shortcode_atts( $pairs, $atts, $tag = '' ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function wp_enqueue_style( ...$a ) { $GLOBALS['paw_enqueued'][] = $a[0]; }
function wp_enqueue_script( ...$a ) { $GLOBALS['paw_enqueued'][] = $a[0]; }
function wp_add_inline_script( ...$a ) {}
function is_singular( $t = '' ) { return $GLOBALS['paw_singular'] ?? false; }

use Pawfectly\EventShortcode;
use Pawfectly\Events;

$base = array(
	'title' => 'X-Mas Party', 'emoji' => '🎄', 'start_date' => '2030-12-19', 'start_time' => '10:00', 'end_date' => '2030-12-19', 'end_time' => '14:00',
	'venue' => 'Paw-radise', 'price' => '15', 'coupon' => 'PawPark', 'status' => 'publish',
	'description' => '<p>Deck the <strong>paws</strong>!</p><script>alert(1)</script>',
	'sections' => array(
		'sessions'     => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs' ), array( 'from' => '12:00', 'to' => '14:00', 'name' => 'Session 2', 'who' => '' ) ) ),
		'includes'     => array( 'rows' => array( array( 'emoji' => '📸', 'text' => 'Photo booth' ), array( 'emoji' => '', 'text' => 'Text 239-420-7418' ) ) ),
		'goodies'      => array( 'rows' => array( array( 'emoji' => '🎅', 'title' => 'Holiday Photo Booth', 'text' => 'Props' ) ) ),
		'good_to_know' => array( 'rows' => array( array( 'emoji' => '🐾', 'text' => 'Bite policy' ) ) ),
	),
);
$ev = Events::save( Events::sanitize( $base ) );
$GLOBALS['paw_the_id'] = $ev;
$sc = static fn( array $atts ): string => EventShortcode::render( $atts );

$s = $sc( array( 'part' => 'sessions' ) );
t( 'sessions: a plain list with time, name and who', str_contains( $s, '<ul class="paw-ev-list paw-ev-list--sessions">' ) && 2 === substr_count( $s, '<li class="paw-ev-row">' ) && str_contains( $s, '<span class="paw-ev-row__time">10:00 AM – 12:00 PM</span>' ) && str_contains( $s, '<span class="paw-ev-row__name">Session 1</span>' ) && str_contains( $s, '<span class="paw-ev-row__who">Small dogs</span>' ) && 1 === substr_count( $s, 'paw-ev-row__who' ) );
$i = $sc( array( 'part' => 'includes' ) );
t( 'includes: emoji and text; no emoji span when empty; phone links', str_contains( $i, 'paw-ev-list--includes' ) && str_contains( $i, '<span class="paw-ev-row__emoji" aria-hidden="true">📸</span>' ) && 1 === substr_count( $i, 'paw-ev-row__emoji' ) && str_contains( $i, '<a href="tel:2394207418">239-420-7418</a>' ) );
$g = $sc( array( 'part' => 'goodies' ) );
t( 'goodies: emoji, title and text', str_contains( $g, 'paw-ev-list--goodies' ) && str_contains( $g, '<strong class="paw-ev-row__title">Holiday Photo Booth</strong>' ) && str_contains( $g, '<span class="paw-ev-row__text">Props</span>' ) );
t( 'good to know', str_contains( $sc( array( 'part' => 'good_to_know' ) ), 'paw-ev-list--good_to_know' ) );
t( 'plain lists carry none of the page\'s styled classes', ! str_contains( $s . $i . $g, 'paw-event__' ) );
$d = $sc( array( 'part' => 'description' ) );
t( 'description: formatted, filtered', str_contains( $d, '<strong>paws</strong>' ) && ! str_contains( $d, '<script' ) && str_contains( $d, 'class="paw-ev-description"' ) );
$a = $sc( array( 'part' => 'addcal' ) );
t( 'add to calendar: the menu inside a part wrapper', str_contains( $a, 'class="paw-event paw-event--part"' ) && str_contains( $a, 'paw-event__addcal' ) && str_contains( $a, 'calendar.google.com' ) );

$f = $sc( array( 'part' => 'signup' ) );
t( 'signup: the form, at #paw-event-reserve', str_contains( $f, 'id="paw-event-reserve"' ) && str_contains( $f, 'data-paw-signup' ) && str_contains( $f, 'class="paw-event paw-event--part"' ) );
t( 'signup: never the member code', ! str_contains( $f, 'PawPark' ) && str_contains( $f, 'name="coupon"' ) );
t( 'signup: off an event page, it loads its own CSS and script', in_array( 'paw-calendar', $GLOBALS['paw_enqueued'] ?? array(), true ) && in_array( 'paw-signup', $GLOBALS['paw_enqueued'], true ) );
$past = Events::save( Events::sanitize( array_merge( $base, array( 'title' => 'Old', 'start_date' => '2020-01-01', 'end_date' => '2020-01-01' ) ) ) );
t( 'signup: ended', str_contains( $sc( array( 'part' => 'signup', 'id' => (string) $past ) ), 'This event has ended' ) && ! str_contains( $sc( array( 'part' => 'signup', 'id' => (string) $past ) ), 'data-paw-signup' ) );
$ticket = Events::save( Events::sanitize( array_merge( $base, array( 'title' => 'Gala', 'ticket_url' => 'https://tickets.example/gala' ) ) ) );
t( 'signup: nothing when tickets are sold elsewhere', '' === $sc( array( 'part' => 'signup', 'id' => (string) $ticket ) ) );

// Empty and unknown.
$bare = Events::save( Events::sanitize( array( 'title' => 'Walk', 'start_date' => '2030-12-20', 'start_time' => '08:00', 'end_date' => '2030-12-20', 'end_time' => '09:00', 'venue' => 'Park', 'status' => 'publish' ) ) );
t( 'empty parts give nothing', '' === $sc( array( 'part' => 'sessions', 'id' => (string) $bare ) ) && '' === $sc( array( 'part' => 'goodies', 'id' => (string) $bare ) ) && '' === $sc( array( 'part' => 'description', 'id' => (string) $bare ) ) );
t( 'an unknown part gives nothing', '' === $sc( array( 'part' => 'coupon' ) ) && '' === $sc( array() ) );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Home', 'post_status' => 'publish' ) );
$GLOBALS['paw_the_id'] = $page;
$GLOBALS['paw_enqueued'] = array();
$sc( array( 'part' => 'includes', 'id' => (string) $ev ) );
t( 'lists off an event page load the CSS, not the form script', array( 'paw-calendar' ) === $GLOBALS['paw_enqueued'] );
// In the Elementor editor an empty part says so (visitors never see it).
eval( 'namespace Elementor; class Plugin { public static $instance; }' );
\Elementor\Plugin::$instance = (object) array( 'editor' => new class() { public function is_edit_mode() { return false; } }, 'preview' => new class() { public function is_preview_mode() { return true; } } );
$hint = $sc( array( 'part' => 'goodies', 'id' => (string) $bare ) );
t( 'editor: an empty section shows a hint', str_contains( $hint, 'paw-ev-empty' ) && str_contains( $hint, 'No extra goodies for this event' ) );
t( 'editor: parts with rows show no hint', ! str_contains( $sc( array( 'part' => 'sessions', 'id' => (string) $ev ) ), 'paw-ev-empty' ) );
\Elementor\Plugin::$instance = null;
t( 'not on an event: nothing', '' === $sc( array( 'part' => 'sessions' ) ) );
t( 'id= picks the event', str_contains( $sc( array( 'part' => 'sessions', 'id' => (string) $ev ) ), 'Session 1' ) );
$draft = Events::save( Events::sanitize( array_merge( $base, array( 'title' => 'Secret', 'status' => 'draft' ) ) ) );
t( 'a draft shows nothing to visitors', '' === $sc( array( 'part' => 'sessions', 'id' => (string) $draft ) ) );

done();
