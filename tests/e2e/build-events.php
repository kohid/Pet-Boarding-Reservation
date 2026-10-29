<?php
/**
 * Renders the public events pages into tests/e2e/out/ with the real CSS and JS:
 * calendar.html ([pawfectly_calendar], December 2026), events.html ([pawfectly_events]) and
 * event.html (the X-Mas Party page). events-e2e.js serves them with events-router.php.
 * Run: php tests/e2e/build-events.php
 */
require __DIR__ . '/events-fixtures.php';

use Pawfectly\Calendar;
use Pawfectly\EventPage;
use Pawfectly\Events;
use Pawfectly\EventsList;

$xmas = paw_e2e_seed();

$page = static function ( string $title, string $body ): string {
	$body = str_replace( 'https://example.test/', '/', $body );
	return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . ' (e2e)</title>
<link rel="stylesheet" href="/assets/css/calendar.css"><style>body{margin:0;padding:24px 16px;background:#f7f5f2}main{max-width:1100px;margin:0 auto}</style>
</head><body><main>' . $body . '</main><script src="/assets/js/calendar.js"></script></body></html>';
};

$_GET['paw_cal'] = '2026-12';
$files           = array(
	'calendar.html' => $page( 'Event Calendar', Calendar::render( array() ) ),
	'events.html'   => $page( 'Events', EventsList::render( array() ) ),
	'event.html'    => $page( 'X-Mas Party', EventPage::body( Events::get( $xmas ) ) ),
);
@mkdir( __DIR__ . '/out', 0755, true );
foreach ( $files as $name => $html ) {
	file_put_contents( __DIR__ . '/out/' . $name, $html );
	echo "wrote tests/e2e/out/$name\n";
}
