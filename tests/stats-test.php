<?php
/**
 * Dashboard overview numbers.
 * Run: php tests/stats-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Stats;

$r    = fn( string $status, string $in, string $out ) => array( 'status' => $status, 'check_in' => $in, 'check_out' => $out );
$rows = array(
	$r( 'checked_in', '2026-10-20', '2026-10-27' ), // boarding across today, leaves in 2 days
	$r( 'confirmed', '2026-10-25', '2026-10-28' ),  // arrives today
	$r( 'checked_in', '2026-10-22', '2026-10-25' ), // leaves today (not boarding tonight)
	$r( 'pending', '2026-10-25', '2026-10-26' ),    // pending, arriving today
	$r( 'cancelled', '2026-10-24', '2026-10-30' ),  // ignored
	$r( 'declined', '2026-10-25', '2026-10-26' ),   // ignored
	$r( 'confirmed', '2026-11-05', '2026-11-07' ),  // inside the 14 nights
	$r( 'pending', '2026-12-01', '2026-12-03' ),    // pending, far ahead
);
$s = Stats::overview( $rows, '2026-10-25' );

t( 'check-ins today count pending and confirmed arrivals', 2 === $s['check_ins'] );
t( 'check-outs today', 1 === $s['check_outs'] );
t( 'boarding tonight: confirmed and checked-in stays covering tonight', 2 === $s['boarding'] );
t( 'pending requests, any date', 2 === $s['pending'] );
t( 'fourteen nights', 14 === count( $s['nights'] ) && '2026-10-25' === $s['nights'][0]['date'] && '2026-11-07' === $s['nights'][13]['date'] );
t( 'tonight in the chart matches boarding', 2 === $s['nights'][0]['count'] );
t( 'night of 27th: only the stay arriving today', 1 === $s['nights'][2]['count'] );
t( 'night of Nov 5 and 6 counted, check-out night not', 1 === $s['nights'][11]['count'] && 1 === $s['nights'][12]['count'] && 0 === $s['nights'][13]['count'] );
t( 'empty input', 0 === Stats::overview( array(), '2026-10-25' )['boarding'] );

done();
