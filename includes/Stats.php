<?php
/**
 * The staff dashboard's overview numbers. Pure: give it reservation rows and today's date.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Stats {

	/** Statuses that mean a pet is (or will be) staying with us. */
	private const STAYING = array( 'confirmed', 'checked_in' );

	public const NIGHTS = 14;

	/**
	 * Rows without booking_type (from before grooming) count as boarding.
	 *
	 * @param array<int, array{status: string, check_in: string, check_out: string, booking_type?: string, appointment_date?: ?string}> $rows
	 * @return array{check_ins: int, check_outs: int, boarding: int, pending: int, grooming: int, nights: array<int, array{date: string, count: int}>}
	 */
	public static function overview( array $rows, string $today ): array {
		$out = array( 'check_ins' => 0, 'check_outs' => 0, 'boarding' => 0, 'pending' => 0, 'grooming' => 0, 'nights' => array() );

		foreach ( $rows as $r ) {
			$staying = in_array( $r['status'], self::STAYING, true );
			$type    = $r['booking_type'] ?? 'boarding';
			$groomed = max( 1, count( array_filter( explode( ',', (string) ( $r['service'] ?? '' ) ) ) ) );
			if ( 'pending' === $r['status'] ) {
				++$out['pending'];
			}
			if ( 'boarding' !== $type && ( $r['appointment_date'] ?? '' ) === $today && ( $staying || 'pending' === $r['status'] ) ) {
				$out['grooming'] += $groomed;
			}
			if ( 'grooming' === $type ) {
				continue; // A grooming visit is not a stay.
			}
			if ( $r['check_in'] === $today && ( $staying || 'pending' === $r['status'] ) ) {
				++$out['check_ins'];
			}
			if ( $staying && $r['check_out'] === $today ) {
				++$out['check_outs'];
			}
		}

		$day = new \DateTimeImmutable( $today );
		for ( $i = 0; $i < self::NIGHTS; $i++ ) {
			$date  = $day->modify( "+{$i} days" )->format( 'Y-m-d' );
			$count = 0;
			foreach ( $rows as $r ) {
				if ( 'grooming' !== ( $r['booking_type'] ?? 'boarding' ) && in_array( $r['status'], self::STAYING, true ) && $r['check_in'] <= $date && $date < $r['check_out'] ) {
					$count += max( 1, (int) ( $r['pet_count'] ?? 1 ) );
				}
			}
			$out['nights'][] = array( 'date' => $date, 'count' => $count );
		}
		$out['boarding'] = $out['nights'][0]['count'];
		return $out;
	}
}
