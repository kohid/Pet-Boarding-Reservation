<?php
/**
 * The event calendar: whole-week month grid, events placed on every day they cover,
 * "+N more", search, categories, drafts, escaping and the public route.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';
require __DIR__ . '/wp-db-stubs.php';
function wp_enqueue_style( ...$a ) {}
function wp_enqueue_script( ...$a ) {}

use Pawfectly\Calendar;
use Pawfectly\Events;

function ev( string $title, string $start, string $end, array $more = array() ): int {
	[ $sd, $st ] = explode( ' ', $start );
	[ $ed, $et ] = explode( ' ', $end );
	$clean = Events::sanitize( array_merge( array( 'title' => $title, 'start_date' => $sd, 'start_time' => $st, 'end_date' => $ed, 'end_time' => $et, 'venue' => 'Paw-radise', 'status' => 'publish' ), $more ) );
	return Events::save( $clean );
}
function cells( string $grid ): array {
	preg_match_all( '/<div class="paw-cal__cell([^"]*)"[^>]*data-date="([\d-]+)"/', $grid, $m );
	return array_combine( $m[2], $m[1] );
}
function cell( string $grid, string $date ): string {
	return preg_match( '/data-date="' . $date . '">(.*?)<!-- \/cell -->/s', $grid, $m ) ? $m[1] : '';
}

wp_insert_term( 'Holiday party', 'paw_event_cat', array( 'slug' => 'holiday' ) );
ev( 'X-Mas Party', '2026-12-19 10:00', '2026-12-19 14:00', array( 'category' => 'holiday' ) );
ev( 'New Year Pawty', '2026-12-31 18:00', '2027-01-02 12:00' );
ev( 'Secret draft', '2026-12-20 10:00', '2026-12-20 11:00', array( 'status' => 'draft' ) );
ev( 'Tom & Jerry\'s "walk"', '2026-12-05 09:00', '2026-12-05 10:00' );

$dec   = Calendar::view_data( '2026-12-10', '', '' );
$cells = cells( $dec['grid'] );
t( 'grid: whole weeks, Monday first', 35 === count( $cells ) && '2026-11-30' === array_key_first( $cells ) && '2027-01-03' === array_key_last( $cells ) );
t( 'grid: days of other months are muted', str_contains( $cells['2026-11-30'], 'is-other' ) && ! str_contains( $cells['2026-12-01'], 'is-other' ) && str_contains( $cells['2027-01-02'], 'is-other' ) );
t( 'titles and month key', 'December 2026' === $dec['monthTitle'] && 'December 10, 2026' === $dec['dayTitle'] && '2026-12' === $dec['ym'] && '2026-12-10' === $dec['date'] );
t( 'grid: the event on its day, with its time', str_contains( cell( $dec['grid'], '2026-12-19' ), 'X-Mas Party' ) && str_contains( cell( $dec['grid'], '2026-12-19' ), '10:00 AM' ) );
t( 'grid: the event has its category colour', str_contains( cell( $dec['grid'], '2026-12-19' ), '--paw-ev:#ff7f50' ) );
$nye = Calendar::by_day( '2026-11-30', '2027-01-03', '', '' );
t( 'an event over several days is on each day it covers', isset( $nye['2026-12-31'], $nye['2027-01-01'], $nye['2027-01-02'] ) && $nye['2026-12-31'][0]['is_start'] && ! $nye['2027-01-01'][0]['is_start'] && $nye['2027-01-02'][0]['is_end'] );
t( 'its times: start, all day, end', '6:00 PM' === $nye['2026-12-31'][0]['time'] && 'All day' === $nye['2027-01-01'][0]['time'] && 'Until 12:00 PM' === $nye['2027-01-02'][0]['time'] );
$jan = Calendar::view_data( '2027-01-15', '', '' );
t( 'an event crossing months shows in both', str_contains( cell( $dec['grid'], '2026-12-31' ), 'New Year Pawty' ) && str_contains( cell( $jan['grid'], '2027-01-02' ), 'New Year Pawty' ) );
t( 'drafts never appear', ! str_contains( $dec['grid'] . $dec['agenda'], 'Secret draft' ) );
t( 'output is escaped', str_contains( $dec['grid'], 'Tom &amp; Jerry&#039;s &quot;walk&quot;' ) && ! str_contains( $dec['grid'], 'Jerry\'s "walk"' ) );
t( 'list view: this month only, grouped by day', str_contains( $dec['agenda'], 'X-Mas Party' ) && ! str_contains( $dec['agenda'], 'data-day="2027-01-02"' ) && str_contains( $dec['agenda'], 'data-day="2026-12-19"' ) );
t( 'day view', str_contains( Calendar::view_data( '2026-12-19', '', '' )['day'], 'X-Mas Party' ) && str_contains( $dec['day'], 'No events on Thursday, December 10, 2026.' ) );

for ( $i = 1; $i <= 5; $i++ ) {
	ev( "Puppy class $i", '2026-12-08 0' . $i . ':00', '2026-12-08 0' . $i . ':30' );
}
$busy = cell( Calendar::view_data( '2026-12-10', '', '' )['grid'], '2026-12-08' );
t( '+N more after 3 chips', 5 === substr_count( $busy, 'data-paw-cal-event=' ) && 2 === substr_count( $busy, ' hidden>' ) && str_contains( $busy, '+2 more' ) );

$found = Calendar::view_data( '2026-12-10', 'x-mas', '' );
t( 'search', str_contains( $found['grid'], 'X-Mas Party' ) && ! str_contains( $found['grid'], 'New Year Pawty' ) && str_contains( Calendar::view_data( '2026-12-10', 'zzz', '' )['agenda'], 'No events match' ) );
t( 'search for % matches nothing', ! str_contains( Calendar::view_data( '2026-12-10', '%', '' )['grid'], 'data-paw-cal-event=' ) );
$cat = Calendar::view_data( '2026-12-10', '', 'holiday' );
t( 'category filter', str_contains( $cat['grid'], 'X-Mas Party' ) && ! str_contains( $cat['grid'], 'New Year Pawty' ) );

// The site's timezone: an 11:30 PM event stays on its own day.
$GLOBALS['paw_tz'] = 'America/New_York';
ev( 'Late howl', '2026-12-19 23:30', '2026-12-19 23:59' );
$late = Calendar::by_day( '2026-12-01', '2026-12-31', 'late', '' );
t( 'site timezone: late events stay on their day', isset( $late['2026-12-19'] ) && ! isset( $late['2026-12-20'] ) && '11:30 PM' === $late['2026-12-19'][0]['time'] );
unset( $GLOBALS['paw_tz'] );

// The shortcode and the public route.
$html = Calendar::render( array( 'view' => 'list' ) );
t( 'shortcode: toolbar, views, categories and the route', str_contains( $html, 'class="paw-cal"' ) && str_contains( $html, 'data-view="list"' ) && str_contains( $html, 'value="holiday"' ) && str_contains( $html, 'wp-json/pawfectly/v1/events/calendar' ) );
$_GET['paw_cal'] = '2027-01';
t( 'shortcode: ?paw_cal picks the month', str_contains( Calendar::render( array() ), 'data-ym="2027-01"' ) );
unset( $_GET['paw_cal'] );
$req      = new WP_REST_Request();
$req->url = array( 'date' => '2026-12-19', 'q' => '', 'cat' => '' );
$res      = Calendar::route( $req );
t( 'route returns the views', $res instanceof WP_REST_Response && str_contains( $res->get_data()['grid'], 'X-Mas Party' ) && '2026-12-19' === $res->get_data()['date'] );
$req->url['date'] = 'nonsense';
t( 'route: a bad date falls back to today', 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', Calendar::route( $req )->get_data()['date'] ) );

done();
