<?php
/**
 * Pawfectly's grooming services and memberships: the catalog, prices by dog size (from Settings),
 * boarding rates, and the estimated total the form, emails and staff screens show.
 * The only place that reads prices.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Services {

	public const MONTHLY = array( 'nail_membership', 'bath_membership' );
	public const SIZES   = array( 'small', 'large' );

	private const MAX_PRICE = 99999.99;

	/**
	 * The catalog as stored in Settings, in the order the business lists it. Prices are strings
	 * ('' = price on request); "included" holds one bullet per line.
	 *
	 * @return array<string, array{enabled: bool, name: string, time: string, small: string, large: string, included: string}>
	 */
	public static function defaults(): array {
		$s = static fn( string $name, string $time, string $small, string $large, array $included ): array => array(
			'enabled'  => true,
			'name'     => $name,
			'time'     => $time,
			'small'    => $small,
			'large'    => $large,
			'included' => implode( "\n", $included ),
		);
		return array(
			'nail_trim'       => $s(
				__( 'Nail Trimming', 'pawfectly-reservations' ),
				__( 'Usually under 15 minutes', 'pawfectly-reservations' ),
				'15.00',
				'20.00',
				array(
					__( 'Nail trimming to proper length', 'pawfectly-reservations' ),
					__( 'Nail filing for smooth edges', 'pawfectly-reservations' ),
					__( 'Paw pad inspection', 'pawfectly-reservations' ),
					__( 'Gentle handling for nervous dogs', 'pawfectly-reservations' ),
				)
			),
			'nail_membership' => $s(
				__( 'Nail Membership', 'pawfectly-reservations' ),
				__( 'Come in as often as you like', 'pawfectly-reservations' ),
				'15.00',
				'20.00',
				array(
					__( 'Professional nail trimming', 'pawfectly-reservations' ),
					__( 'Nail filing for smooth edges', 'pawfectly-reservations' ),
					__( 'Come in as often as you like', 'pawfectly-reservations' ),
					__( 'Priority walk-in service', 'pawfectly-reservations' ),
					__( 'Calm, low-stress handling', 'pawfectly-reservations' ),
					__( 'No contract — cancel anytime', 'pawfectly-reservations' ),
				)
			),
			'bath_membership' => $s(
				__( 'Bath Membership', 'pawfectly-reservations' ),
				__( 'Unlimited baths', 'pawfectly-reservations' ),
				'59.00',
				'59.00',
				array(
					__( 'Professional bathing for your dog', 'pawfectly-reservations' ),
					__( 'Unlimited visits', 'pawfectly-reservations' ),
					__( 'No sign-up fee', 'pawfectly-reservations' ),
					__( 'Cancel anytime', 'pawfectly-reservations' ),
				)
			),
			'basic_bath'      => $s(
				__( 'Basic Bath', 'pawfectly-reservations' ),
				'',
				'',
				'',
				array(
					__( 'Thorough shampoo with premium Reliqpet all-natural products', 'pawfectly-reservations' ),
					__( 'Complete rinse to remove all residue', 'pawfectly-reservations' ),
					__( 'Towel dry (blow dry available as add-on)', 'pawfectly-reservations' ),
					__( 'Nail trimming & filing', 'pawfectly-reservations' ),
					__( 'Ear cleaning', 'pawfectly-reservations' ),
					__( 'Gland expression', 'pawfectly-reservations' ),
				)
			),
			'deluxe_bath'     => $s(
				__( 'Deluxe Bath', 'pawfectly-reservations' ),
				__( 'About 2 hours', 'pawfectly-reservations' ),
				'',
				'',
				array(
					__( 'Double-wash with premium Reliqpet all-natural shampoo', 'pawfectly-reservations' ),
					__( 'Deep conditioning treatment', 'pawfectly-reservations' ),
					__( 'Professional blow dry with brushing', 'pawfectly-reservations' ),
					__( 'Light brushing & de-shedding', 'pawfectly-reservations' ),
					__( 'Face trim, feet trim, sanitary trim', 'pawfectly-reservations' ),
					__( 'Nail trimming & filing', 'pawfectly-reservations' ),
					__( 'Ear cleaning', 'pawfectly-reservations' ),
					__( 'Gland expression', 'pawfectly-reservations' ),
					__( 'Finishing spray & cologne', 'pawfectly-reservations' ),
				)
			),
			'basic_haircut'   => $s(
				__( 'Basic Haircuts', 'pawfectly-reservations' ),
				__( '2–3 hours', 'pawfectly-reservations' ),
				'',
				'',
				array(
					__( 'Full bath with premium shampoo', 'pawfectly-reservations' ),
					__( 'Professional blow dry', 'pawfectly-reservations' ),
					__( 'Breed-standard or owner-requested haircut', 'pawfectly-reservations' ),
					__( 'Face, feet, and sanitary trimming', 'pawfectly-reservations' ),
					__( 'Nail trimming & filing', 'pawfectly-reservations' ),
					__( 'Ear cleaning', 'pawfectly-reservations' ),
					__( 'Gland expression', 'pawfectly-reservations' ),
				)
			),
			'premium_haircut' => $s(
				__( 'Premium Haircuts', 'pawfectly-reservations' ),
				__( 'About 3 hours', 'pawfectly-reservations' ),
				'',
				'',
				array(
					__( 'Thorough pre-brush & de-matting', 'pawfectly-reservations' ),
					__( 'Luxurious bath with premium shampoo', 'pawfectly-reservations' ),
					__( 'Deep conditioning treatment', 'pawfectly-reservations' ),
					__( 'Professional blow dry with continuous brushing', 'pawfectly-reservations' ),
					__( 'Custom or breed-standard haircut', 'pawfectly-reservations' ),
					__( 'Face styling & scissor work', 'pawfectly-reservations' ),
					__( 'Paw pad trimming', 'pawfectly-reservations' ),
					__( 'Nail trim & filing', 'pawfectly-reservations' ),
					__( 'Ear cleaning', 'pawfectly-reservations' ),
					__( 'Gland expression', 'pawfectly-reservations' ),
					__( 'Sanitary trim', 'pawfectly-reservations' ),
					__( 'Finishing cologne & bandana', 'pawfectly-reservations' ),
				)
			),
		);
	}

	/**
	 * Every service with the saved settings applied; unknown ids in the settings are ignored.
	 *
	 * @return array<string, array{id: string, name: string, time: string, monthly: bool, small: ?float, large: ?float, included: list<string>, enabled: bool}>
	 */
	public static function catalog(): array {
		$saved = (array) ( Settings::get()['services'] ?? array() );
		$out   = array();
		foreach ( self::defaults() as $id => $default ) {
			$s          = array_merge( $default, is_array( $saved[ $id ] ?? null ) ? $saved[ $id ] : array() );
			$out[ $id ] = array(
				'id'       => $id,
				'name'     => (string) $s['name'],
				'time'     => (string) $s['time'],
				'monthly'  => in_array( $id, self::MONTHLY, true ),
				'small'    => self::amount( $s['small'] ),
				'large'    => self::amount( $s['large'] ),
				'included' => array_values( array_filter( array_map( 'trim', explode( "\n", (string) $s['included'] ) ), 'strlen' ) ),
				'enabled'  => (bool) $s['enabled'],
			);
		}
		return $out;
	}

	/** The services the form offers. */
	public static function enabled(): array {
		return array_filter( self::catalog(), static fn( array $s ): bool => $s['enabled'] );
	}

	/** Price for one dog size; null when unknown, turned off, or on request. */
	public static function price( string $id, string $size ): ?float {
		$s = self::enabled()[ $id ] ?? null;
		return $s && in_array( $size, self::SIZES, true ) ? $s[ $size ] : null;
	}

	/** Nightly boarding rate: dogs by size, cats and other pets share one. */
	public static function rate( string $pet_type, string $size ): ?float {
		$rates = (array) ( Settings::get()['boarding_rates'] ?? array() );
		$key   = 'dog' === $pet_type ? ( in_array( $size, self::SIZES, true ) ? $size : '' ) : 'other';
		return '' !== $key ? self::amount( $rates[ $key ] ?? '' ) : null;
	}

	public static function money( float $amount, ?string $currency = null ): string {
		$currency = $currency ?? (string) ( Settings::get()['currency'] ?? '$' );
		$decimals = abs( $amount - round( $amount ) ) < 0.005 ? 0 : 2;
		return $currency . number_format( $amount, $decimals );
	}

	/** A price typed into Settings: '' (on request), a normalised "12.50", or null when invalid. */
	public static function clean_price( $raw ): ?string {
		if ( ! is_scalar( $raw ) ) {
			return null;
		}
		$value = trim( (string) $raw );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $value ) || (float) $value > self::MAX_PRICE ) {
			return null;
		}
		return number_format( (float) $value, 2, '.', '' );
	}

	/**
	 * What the owner will roughly pay. Display only: staff confirm the final price.
	 *
	 * @param array<string,string> $v Form values (booking_type, pet_type, dog_size, check_in, check_out, service).
	 * @return array{lines: list<array{label: string, amount: ?float, monthly: bool}>, total: float, on_request: bool, currency: string}
	 */
	public static function estimate( array $v ): array {
		$type  = (string) ( $v['booking_type'] ?? 'boarding' );
		$size  = (string) ( $v['dog_size'] ?? '' );
		$lines = array();

		if ( in_array( $type, array( 'boarding', 'both' ), true ) ) {
			$nights = self::nights( (string) ( $v['check_in'] ?? '' ), (string) ( $v['check_out'] ?? '' ) );
			$rate   = self::rate( (string) ( $v['pet_type'] ?? '' ), $size );
			$lines[] = array(
				/* translators: %d: number of nights */
				'label'   => sprintf( _n( 'Boarding: %d night', 'Boarding: %d nights', $nights, 'pawfectly-reservations' ), $nights ),
				'amount'  => null === $rate ? null : round( $rate * $nights, 2 ),
				'monthly' => false,
			);
		}
		$service = self::catalog()[ (string) ( $v['service'] ?? '' ) ] ?? null;
		if ( $service && in_array( $type, array( 'grooming', 'both' ), true ) ) {
			$lines[] = array(
				'label'   => $service['name'],
				'amount'  => self::price( $service['id'], $size ),
				'monthly' => $service['monthly'],
			);
		}

		$total = 0.0;
		$open  = false;
		foreach ( $lines as $line ) {
			if ( null === $line['amount'] ) {
				$open = true;
			} else {
				$total += $line['amount'];
			}
		}
		return array(
			'lines'      => $lines,
			'total'      => round( $total, 2 ),
			'on_request' => $open,
			'currency'   => (string) ( Settings::get()['currency'] ?? '$' ),
		);
	}

	/** One line of a stored estimate as a person reads it. */
	public static function amount_text( ?float $amount, bool $monthly, string $currency ): string {
		if ( null === $amount ) {
			return __( 'Price on request', 'pawfectly-reservations' );
		}
		return self::money( $amount, $currency ) . ( $monthly ? ' ' . __( '/ month', 'pawfectly-reservations' ) : '' );
	}

	/** A stored estimate's total: "$100", "$80 + price on request", "Price on request", or '' without one. */
	public static function total_text( array $pricing ): string {
		if ( empty( $pricing['lines'] ) ) {
			return '';
		}
		$currency = (string) ( $pricing['currency'] ?? '$' );
		$total    = (float) ( $pricing['total'] ?? 0 );
		$known    = array_filter( (array) $pricing['lines'], static fn( $l ): bool => null !== ( $l['amount'] ?? null ) );
		if ( ! $known ) {
			return __( 'Price on request', 'pawfectly-reservations' );
		}
		return self::money( $total, $currency ) . ( ! empty( $pricing['on_request'] ) ? ' + ' . __( 'price on request', 'pawfectly-reservations' ) : '' );
	}

	private static function nights( string $in, string $out ): int {
		$a = \DateTimeImmutable::createFromFormat( '!Y-m-d', $in );
		$b = \DateTimeImmutable::createFromFormat( '!Y-m-d', $out );
		return $a && $b && $b > $a ? (int) $a->diff( $b )->days : 0;
	}

	private static function amount( $stored ): ?float {
		$clean = self::clean_price( $stored );
		return null === $clean || '' === $clean ? null : (float) $clean;
	}
}
