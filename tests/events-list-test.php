<?php
/**
 * [pawfectly_events]: tabs by status, order, Load more, and what a card shows.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/wp-db-stubs.php';
function wp_enqueue_style( ...$a ) {}
function wp_enqueue_script( ...$a ) {}

use Pawfectly\Events;
use Pawfectly\EventsList;

function ev( string $title, string $start, string $end, array $more = array() ): int {
	[ $sd, $st ] = explode( ' ', $start );
	[ $ed, $et ] = explode( ' ', $end );
	return Events::save( Events::sanitize( array_merge( array( 'title' => $title, 'start_date' => $sd, 'start_time' => $st, 'end_date' => $ed, 'end_time' => $et, 'venue' => 'Paw-radise', 'price' => '15', 'status' => 'publish' ), $more ) ) );
}
function titles( string $html ): array {
	preg_match_all( '/class="paw-events__title">([^<]+)</', $html, $m );
	return $m[1];
}

$now  = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
$day  = static fn( string $mod ): string => $now->modify( $mod )->format( 'Y-m-d' );
ev( 'Past party', $day( '-10 days' ) . ' 10:00', $day( '-10 days' ) . ' 12:00' );
ev( 'Older party', $day( '-20 days' ) . ' 10:00', $day( '-20 days' ) . ' 12:00' );
ev( 'Happening now', $day( '-1 day' ) . ' 10:00', $day( '+1 day' ) . ' 12:00' );
ev( 'Soon', $day( '+3 days' ) . ' 10:00', $day( '+3 days' ) . ' 12:00', array( 'emoji' => '🎄' ) );
ev( 'Later', $day( '+30 days' ) . ' 10:00', $day( '+30 days' ) . ' 12:00', array( 'photo_id' => 9 ) );
ev( 'Draft', $day( '+4 days' ) . ' 10:00', $day( '+4 days' ) . ' 12:00', array( 'status' => 'draft' ) );

t( 'upcoming: soonest first, no drafts', array( 'Soon', 'Later' ) === titles( EventsList::cards( 'upcoming', '', 0 )['html'] ) );
t( 'current', array( 'Happening now' ) === titles( EventsList::cards( 'current', '', 0 )['html'] ) );
t( 'past: most recent first', array( 'Past party', 'Older party' ) === titles( EventsList::cards( 'past', '', 0 )['html'] ) );
t( 'all', 5 === count( titles( EventsList::cards( 'all', '', 0 )['html'] ) ) );

$soon = EventsList::cards( 'upcoming', '', 0 )['html'];
t( 'card: the emoji when there is no photo, the photo otherwise', str_contains( $soon, '<span class="paw-events__emoji">🎄</span>' ) && str_contains( $soon, 'background-image:url(\'https://example.test/logo-9.png\')' ) );
$d = $now->modify( '+3 days' );
t( 'card: date block, time, venue, price and link', str_contains( $soon, '<span class="paw-events__mon">' . strtoupper( $d->format( 'M' ) ) . '</span><span class="paw-events__day">' . $d->format( 'j' ) . '</span>' ) && str_contains( $soon, '10:00 AM – 12:00 PM' ) && str_contains( $soon, 'Paw-radise' ) && str_contains( $soon, '$15 per dog' ) && str_contains( $soon, 'href="https://example.test/event/soon/"' ) );

for ( $i = 1; $i <= 10; $i++ ) {
	ev( "Class $i", $day( '+' . ( 40 + $i ) . ' days' ) . ' 09:00', $day( '+' . ( 40 + $i ) . ' days' ) . ' 10:00' );
}
$first = EventsList::cards( 'upcoming', '', 0 );
$rest  = EventsList::cards( 'upcoming', '', 9 );
t( 'nine at a time, then Load more', 9 === count( titles( $first['html'] ) ) && true === $first['more'] && 3 === count( titles( $rest['html'] ) ) && false === $rest['more'] );
t( 'an empty tab says so', str_contains( EventsList::cards( 'upcoming', 'nope', 0 )['html'], 'No events here yet.' ) );

$_GET['paw_events'] = 'past';
$html = EventsList::render( array() );
t( 'shortcode: tabs, the chosen one current, and Load more data', str_contains( $html, 'class="paw-events"' ) && str_contains( $html, 'aria-current="page">Past' ) && str_contains( $html, 'data-rest="https://example.test/wp-json/pawfectly/v1/events/list"' ) && str_contains( $html, 'Past party' ) );
unset( $_GET['paw_events'] );
t( 'shortcode: show="past" picks the first tab', str_contains( EventsList::render( array( 'show' => 'past' ) ), 'aria-current="page">Past' ) );

$req      = new WP_REST_Request();
$req->url = array( 'tab' => 'upcoming', 'cat' => '', 'offset' => '9' );
t( 'route returns cards', 3 === count( titles( EventsList::route( $req )->get_data()['html'] ) ) );

done();
