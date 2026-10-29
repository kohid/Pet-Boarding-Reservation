<?php
/**
 * The event page: the X-Mas Party example top to bottom, empty sections, the past ribbon,
 * JSON-LD and draft previews for staff only.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

use Pawfectly\Activator;
use Pawfectly\EventPage;
use Pawfectly\Events;

$in = array(
	'title'       => 'X-Mas Party',
	'tagline'     => 'A jingle-bark holiday party for dogs of every breed',
	'emoji'       => '🎄',
	'start_date'  => '2026-12-19',
	'start_time'  => '10:00',
	'end_date'    => '2026-12-19',
	'end_time'    => '14:00',
	'venue'       => 'Paw-radise Indoor Dog Park',
	'address'     => 'Estero, FL',
	'price'       => '15',
	'note'        => '🐾 FREE for Dog Park Members — call or text 239-420-7418 for your coupon code',
	'description' => '<p>Deck the paws! Join us for a holiday celebration.</p>',
	'status'      => 'publish',
	'sections'    => array(
		'sessions'     => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs (under 30 lbs)' ), array( 'from' => '12:00', 'to' => '14:00', 'name' => 'Session 2', 'who' => 'Large dogs (30+ lbs)' ) ) ),
		'includes'     => array( 'rows' => array( array( 'emoji' => '🐾', 'text' => 'Off-leash holiday play time' ), array( 'emoji' => '📸', 'text' => 'Festive photo booth with holiday props' ), array( 'emoji' => '🍪', 'text' => 'Holiday treat samples for pups' ), array( 'emoji' => '🎁', 'text' => 'Goodie bags' ), array( 'emoji' => '🎟️', 'text' => 'Raffle prizes' ) ) ),
		'goodies'      => array( 'rows' => array( array( 'emoji' => '🎅', 'title' => 'Holiday Photo Booth', 'text' => 'Get your pup’s picture with festive holiday props.' ), array( 'emoji' => '🐾', 'title' => 'Free for Park Members', 'text' => 'Call or text us at 239-420-7418.' ) ) ),
		'good_to_know' => array( 'rows' => array( array( 'emoji' => '🐾', 'text' => 'Please join the session that matches your dog.' ), array( 'emoji' => '', 'text' => 'Standard park entry requirements apply.' ), array( 'emoji' => '🐾', 'text' => 'The 1 Bite Policy applies.' ) ) ),
	),
);
$clean = Events::sanitize( $in );
$ev    = Events::get( Events::save( $clean ) );
$now   = new DateTimeImmutable( '2026-11-01 09:00', new DateTimeZone( 'UTC' ) );
$html  = EventPage::body( $ev, $now );

$order = array( 'One Day Only · Saturday, December 19th, 2026', '🎄', 'X-Mas Party', 'A jingle-bark holiday party', '10:00 AM – 2:00 PM', 'Paw-radise Indoor Dog Park', '$15 per dog', 'Deck the paws!', 'Get Tickets — $15', 'FREE for Dog Park Members', 'Pick Your Pup', 'Everything Your $15 Ticket Includes', 'Extra Goodies You', 'Good to Know', 'Reserve Your Spot Today!' );
$at    = -1;
$inorder = true;
foreach ( $order as $piece ) {
	$p = strpos( $html, $piece, max( 0, $at ) );
	if ( false === $p || $p < $at ) {
		$inorder = false;
		echo "  missing or out of order: $piece\n";
	}
	$at = false === $p ? $at : $p;
}
t( 'the example, top to bottom', $inorder );
t( 'facts row: the day and the map link', str_contains( $html, 'Saturday, December 19th, 2026' ) && str_contains( $html, 'https://www.google.com/maps/search/?api=1&amp;query=Paw-radise+Indoor+Dog+Park%2C+Estero%2C+FL' ) );
t( 'the highlight note links the phone number', str_contains( $html, '<a href="tel:2394207418">239-420-7418</a> for your coupon code' ) );
t( 'sessions: time on top, then name and who', str_contains( $html, '10:00 AM – 12:00 PM' ) && str_contains( $html, '12:00 – 2:00 PM' ) && str_contains( $html, 'Session 1: Small dogs (under 30 lbs)' ) );
t( 'five includes, two goodies, three good-to-know items', 5 === substr_count( $html, 'class="paw-event__include"' ) && 2 === substr_count( $html, 'class="paw-event__goodie"' ) && 3 === substr_count( $html, 'class="paw-event__know"' ) );
t( 'a good-to-know item with no emoji gets the paw', str_contains( $html, '<span class="paw-event__know-emoji" aria-hidden="true">🐾</span><span>Standard park entry' ) );
t( 'the closing summary', str_contains( $html, 'Saturday, December 19th, 2026, 10:00 AM – 2:00 PM — $15 per dog' ) );
t( 'no ticket link yet: buttons go to the closing block, which says sign-ups open soon', str_contains( $html, 'href="#paw-event-reserve" data-paw-ticket' ) && str_contains( $html, 'id="paw-event-reserve"' ) && str_contains( $html, 'Sign-ups open soon' ) );
t( 'add to calendar links', str_contains( $html, 'calendar.google.com' ) && str_contains( $html, '?paw_ics=1' ) );

$linked = $ev;
$linked['ticket_url'] = 'https://tickets.example/xmas';
$lh = EventPage::body( $linked, $now );
t( 'with a ticket link: both buttons go there and no sign-ups note', 2 === substr_count( $lh, 'href="https://tickets.example/xmas" data-paw-ticket' ) && ! str_contains( $lh, 'Sign-ups open soon' ) );

$bare = $ev;
$bare['sections']['goodies']['rows'] = array();
t( 'an empty section is not shown', ! str_contains( EventPage::body( $bare, $now ), 'Extra Goodies' ) );
$free = $ev;
$free['free'] = true;
$free['price'] = '';
t( 'free: "Get Tickets" and "Everything Your Free Ticket Includes"', str_contains( EventPage::body( $free, $now ), '>Get Tickets</a>' ) && str_contains( EventPage::body( $free, $now ), 'Everything Your Free Ticket Includes' ) );

$past = EventPage::body( $ev, new DateTimeImmutable( '2026-12-20 09:00', new DateTimeZone( 'UTC' ) ) );
t( 'a past event: ribbon, no ticket buttons', str_contains( $past, 'This event has ended' ) && ! str_contains( $past, 'data-paw-ticket' ) );

$sneaky = $ev;
$sneaky['tagline'] = '<script>alert(1)</script>';
t( 'text is escaped', str_contains( EventPage::body( $sneaky, $now ), '&lt;script&gt;' ) );

$ld = EventPage::json_ld( $ev );
t( 'JSON-LD', 'Event' === $ld['@type'] && '2026-12-19T10:00:00+00:00' === $ld['startDate'] && '2026-12-19T14:00:00+00:00' === $ld['endDate'] && '15.00' === $ld['offers']['price'] && 'USD' === $ld['offers']['priceCurrency'] && 'Paw-radise Indoor Dog Park' === $ld['location']['name'] && 'X-Mas Party' === $ld['name'] );

// Drafts: a 404 for visitors, a preview for staff.
class Fake_Query {
	public $vars;
	public function __construct( array $v ) { $this->vars = $v; }
	public function is_main_query() { return true; }
	public function get( $k ) { return $this->vars[ $k ] ?? ''; }
	public function set( $k, $v ) { $this->vars[ $k ] = $v; }
}
$_GET['paw_preview'] = '1';
$q = new Fake_Query( array( 'post_type' => 'paw_event', 'p' => 5 ) );
wp_set_current_user( 0 );
EventPage::allow_preview( $q );
t( 'a draft is a 404 for visitors', '' === $q->get( 'post_status' ) );
$desk = paw_test_user( 'desk@example.com', 'desk pass1', array( 'paw_staff' ), array( Activator::CAPABILITY => true ) );
wp_set_current_user( $desk->ID );
EventPage::allow_preview( $q );
t( 'and renders for staff with paw_preview', array( 'publish', 'draft' ) === $q->get( 'post_status' ) );
unset( $_GET['paw_preview'] );
t( 'preview link', 'https://example.test/?post_type=paw_event&p=5&paw_preview=1' === EventPage::preview_url( 5 ) );

// the_content: block themes before WordPress 6.4 render the content outside the loop.
function is_singular( $t = '' ) { return true; }
function is_main_query() { return true; }
function get_the_ID() { return $GLOBALS['paw_the_id']; }
function get_queried_object_id() { return $GLOBALS['paw_queried']; }
$GLOBALS['paw_the_id'] = $ev['id'];
$GLOBALS['paw_queried'] = $ev['id'];
t( 'the_content: the event body, even outside the loop', str_contains( EventPage::content( '<p>raw</p>' ), 'paw-event__dateline' ) );
$GLOBALS['paw_the_id'] = 999;
t( 'the_content: another post on the page keeps its own content', '<p>raw</p>' === EventPage::content( '<p>raw</p>' ) );

done();
