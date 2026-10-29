<?php
/**
 * EventDates: the date line, time ranges, summary, price text and Add to calendar links.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';

use Pawfectly\EventDates;

$ev = array(
	'id'       => 7,
	'title'    => 'X-Mas Party',
	'tagline'  => 'A jingle-bark holiday party',
	'start'    => '2026-12-19 10:00:00',
	'end'      => '2026-12-19 14:00:00',
	'venue'    => 'Paw-radise Indoor Dog Park',
	'address'  => 'Estero, FL',
	'price'    => '15.00',
	'free'     => false,
	'url'      => 'https://example.test/event/x-mas-party/',
);
$long = array_merge( $ev, array( 'start' => '2026-12-31 18:00:00', 'end' => '2027-01-02 12:00:00' ) );
$free = array_merge( $ev, array( 'price' => '', 'free' => true ) );

t( 'date line: one day', 'One Day Only · Saturday, December 19th, 2026' === EventDates::date_line( $ev ) );
t( 'date line: several days in one year', 'Friday, December 18th – Sunday, December 20th, 2026' === EventDates::date_line( array_merge( $ev, array( 'start' => '2026-12-18 10:00:00', 'end' => '2026-12-20 14:00:00' ) ) ) );
t( 'date line: across a new year', 'Thursday, December 31st, 2026 – Saturday, January 2nd, 2027' === EventDates::date_line( $long ) );
t( 'day', 'Saturday, December 19th, 2026' === EventDates::day( $ev ) );
t( 'time range', '10:00 AM – 2:00 PM' === EventDates::time_range( $ev ) );
t( 'times: same half of the day shows AM or PM once', '12:00 – 2:00 PM' === EventDates::times( '12:00', '14:00' ) && '10:00 AM – 12:00 PM' === EventDates::times( '10:00', '12:00' ) );
t( 'price text', '$15 per dog' === EventDates::price_text( $ev ) && 'Free' === EventDates::price_text( $free ) && '' === EventDates::price_text( array_merge( $ev, array( 'price' => '' ) ) ) );
t( 'summary', 'Saturday, December 19th, 2026, 10:00 AM – 2:00 PM — $15 per dog' === EventDates::summary( $ev ) && str_ends_with( EventDates::summary( $free ), '— Free' ) );
t( 'summary across days', 'Thursday, December 31st, 2026, 6:00 PM – Saturday, January 2nd, 2027, 12:00 PM — $15 per dog' === EventDates::summary( $long ) );
t( 'fill {price}', 'Everything Your $15 Ticket Includes' === EventDates::fill( 'Everything Your {price} Ticket Includes', $ev ) && 'Everything Your Free Ticket Includes' === EventDates::fill( 'Everything Your {price} Ticket Includes', $free ) );

$links = EventDates::links( $ev );
t( 'google link', str_starts_with( $links['google'], 'https://calendar.google.com/calendar/render?' ) && str_contains( $links['google'], 'dates=20261219T100000%2F20261219T140000' ) && str_contains( $links['google'], 'text=X-Mas+Party' ) && str_contains( $links['google'], 'ctz=UTC' ) );
t( 'outlook link', str_starts_with( $links['outlook'], 'https://outlook.live.com/calendar/0/deeplink/compose?' ) && str_contains( $links['outlook'], 'startdt=2026-12-19T10%3A00%3A00' ) );
t( 'ics link', 'https://example.test/event/x-mas-party/?paw_ics=1' === $links['ics'] );

$ics = EventDates::ics( $ev );
t( 'ics: one event in the site timezone', str_starts_with( $ics, "BEGIN:VCALENDAR\r\n" ) && str_contains( $ics, "DTSTART;TZID=UTC:20261219T100000\r\n" ) && str_contains( $ics, "DTEND;TZID=UTC:20261219T140000\r\n" ) && str_ends_with( $ics, "END:VCALENDAR\r\n" ) );
t( 'ics: text escaped, uid and url', str_contains( $ics, "LOCATION:Paw-radise Indoor Dog Park\\, Estero\\, FL\r\n" ) && str_contains( $ics, "UID:paw-event-7@example.test\r\n" ) && str_contains( $ics, "URL:https://example.test/event/x-mas-party/\r\n" ) );

$html = EventDates::phone_links( esc_html( 'Call or text 239-420-7418 or (239) 420-7418 — since 2026.' ) );
t( 'phone links', 2 === substr_count( $html, 'href="tel:2394207418"' ) && str_contains( $html, 'since 2026' ) && ! str_contains( $html, 'tel:2026' ) );

// Sites set to a UTC offset ("UTC-5") instead of a city: calendar apps get UTC times.
$GLOBALS['paw_tz'] = '-05:00';
$off   = EventDates::links( $ev );
$offic = EventDates::ics( $ev );
t( 'offset timezone: Google gets UTC times and no ctz', str_contains( $off['google'], 'dates=20261219T150000Z%2F20261219T190000Z' ) && ! str_contains( $off['google'], 'ctz=' ) );
t( 'offset timezone: the .ics uses UTC, not TZID=-05:00', str_contains( $offic, "DTSTART:20261219T150000Z\r\n" ) && ! str_contains( $offic, 'TZID' ) );
$GLOBALS['paw_tz'] = 'America/New_York';
t( 'named timezone: kept as is', str_contains( EventDates::links( $ev )['google'], 'ctz=America%2FNew_York' ) && str_contains( EventDates::ics( $ev ), 'DTSTART;TZID=America/New_York:20261219T100000' ) );
unset( $GLOBALS['paw_tz'] );
t( 'ics link with plain permalinks', 'https://example.test/?paw_event=x-mas&paw_ics=1' === EventDates::links( array_merge( $ev, array( 'url' => 'https://example.test/?paw_event=x-mas' ) ) )['ics'] );

done();
