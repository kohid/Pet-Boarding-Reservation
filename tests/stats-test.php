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
// Grooming.
$g = fn( string $type, string $status, string $in, string $out, string $day ) => array( 'status' => $status, 'check_in' => $in, 'check_out' => $out, 'booking_type' => $type, 'appointment_date' => $day );
$groom = Stats::overview(
	array(
		$g( 'grooming', 'confirmed', '2026-10-25', '2026-10-25', '2026-10-25' ), // grooming visit today
		$g( 'grooming', 'pending', '2026-10-25', '2026-10-25', '2026-10-25' ),   // requested for today
		$g( 'grooming', 'cancelled', '2026-10-25', '2026-10-25', '2026-10-25' ), // ignored
		$g( 'both', 'checked_in', '2026-10-24', '2026-10-27', '2026-10-25' ),    // boarding and groomed today
		$g( 'grooming', 'confirmed', '2026-10-26', '2026-10-26', '2026-10-26' ), // tomorrow
		$r( 'confirmed', '2026-10-25', '2026-10-26' ),                           // old row: boarding
	),
	'2026-10-25'
);
t( 'grooming today: visits and stays with a grooming day today', 3 === $groom['grooming'] );
t( 'grooming visits are not check-ins or check-outs', 1 === $groom['check_ins'] && 0 === $groom['check_outs'] );
t( 'grooming visits do not count as boarding nights; boarding + grooming does', 2 === $groom['boarding'] && 2 === $groom['nights'][0]['count'] );
t( 'pending grooming still counts as a pending request', 1 === $groom['pending'] );
$byPet = Stats::overview(
	array(
		array( 'status' => 'confirmed', 'check_in' => '2026-10-24', 'check_out' => '2026-10-27', 'booking_type' => 'boarding', 'appointment_date' => null, 'pet_count' => '2', 'service' => '' ),
		array( 'status' => 'confirmed', 'check_in' => '2026-10-25', 'check_out' => '2026-10-25', 'booking_type' => 'grooming', 'appointment_date' => '2026-10-25', 'pet_count' => '2', 'service' => 'nail_trim,basic_bath' ),
	),
	'2026-10-25'
);
t( 'pets boarding tonight counts pets, not bookings', 2 === $byPet['boarding'] && 2 === $byPet['nights'][0]['count'] );
t( 'grooming today counts groomed pets', 2 === $byPet['grooming'] );
t( 'empty input', 0 === Stats::overview( array(), '2026-10-25' )['boarding'] );

done();
