<?php
/**
 * The services catalog, prices by dog size, and the estimate the form and emails show.
 * Run: php tests/services-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Services;
use Pawfectly\Settings;

$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';

t( 'catalog in the order the business lists them', array( 'nail_trim', 'nail_membership', 'bath_membership', 'basic_bath', 'deluxe_bath', 'basic_haircut', 'premium_haircut' ) === array_keys( Services::catalog() ) );
$c = Services::catalog();
t( 'names as listed', 'Nail Trimming' === $c['nail_trim']['name'] && 'Premium Haircuts' === $c['premium_haircut']['name'] && 'Bath Membership' === $c['bath_membership']['name'] );
t( 'memberships are monthly', $c['nail_membership']['monthly'] && $c['bath_membership']['monthly'] && ! $c['nail_trim']['monthly'] );
t( "what's included, one item per line", 'Nail trimming to proper length' === $c['nail_trim']['included'][0] && 12 === count( $c['premium_haircut']['included'] ) );
t( 'nail trim: $15 small, $20 large', 15.0 === Services::price( 'nail_trim', 'small' ) && 20.0 === Services::price( 'nail_trim', 'large' ) );
t( 'bath membership: $59 both sizes', 59.0 === Services::price( 'bath_membership', 'small' ) && 59.0 === Services::price( 'bath_membership', 'large' ) );
t( 'no price given yet: on request', null === Services::price( 'basic_bath', 'small' ) );
t( 'unknown service or size', null === Services::price( 'teeth', 'small' ) && null === Services::price( 'nail_trim', 'huge' ) );

t( 'money: whole amounts without cents', '$15' === Services::money( 15.0 ) );
t( 'money: cents when needed', '$12.50' === Services::money( 12.5 ) );

t( 'price input: blank stays blank (on request)', '' === Services::clean_price( '' ) );
t( 'price input: normalised', '15.00' === Services::clean_price( '15' ) && '12.50' === Services::clean_price( ' 12.5 ' ) && '0.00' === Services::clean_price( '0' ) );
t( 'price input: not a number', null === Services::clean_price( 'abc' ) && null === Services::clean_price( '12,50' ) && null === Services::clean_price( array() ) );
t( 'price input: negative or too big', null === Services::clean_price( '-1' ) && null === Services::clean_price( '100000' ) );
t( 'price input: at most two decimals', null === Services::clean_price( '1.234' ) );

$GLOBALS['paw_options'][ Settings::OPTION ] = array(
	'boarding_rates' => array( 'small' => '30.00', 'large' => '40.00', 'other' => '' ),
	'services'       => array( 'nail_trim' => array( 'enabled' => false ) + Services::defaults()['nail_trim'] ),
);
t( 'a turned-off service has no price and is not offered', null === Services::price( 'nail_trim', 'small' ) && ! isset( Services::enabled()['nail_trim'] ) && isset( Services::enabled()['basic_bath'] ) );
t( 'boarding rate by dog size, cats and others share one', 40.0 === Services::rate( 'dog', 'large' ) && null === Services::rate( 'cat', '' ) );

$both = Services::estimate( array( 'booking_type' => 'both', 'pet_type' => 'dog', 'dog_size' => 'large', 'check_in' => '2026-11-01', 'check_out' => '2026-11-04', 'service' => 'bath_membership' ) );
t( 'estimate: boarding nights × rate, then the service', 2 === count( $both['lines'] ) && 120.0 === $both['lines'][0]['amount'] && 'Boarding: 3 nights' === $both['lines'][0]['label'] && 59.0 === $both['lines'][1]['amount'] );
t( 'estimate: total and flags', 179.0 === $both['total'] && false === $both['on_request'] && true === $both['lines'][1]['monthly'] && '$' === $both['currency'] );

$groom = Services::estimate( array( 'booking_type' => 'grooming', 'pet_type' => 'dog', 'dog_size' => 'small', 'service' => 'basic_bath' ) );
t( 'estimate: grooming only, price on request', 1 === count( $groom['lines'] ) && 'Basic Bath' === $groom['lines'][0]['label'] && null === $groom['lines'][0]['amount'] && 0.0 === $groom['total'] && true === $groom['on_request'] );

$cat = Services::estimate( array( 'booking_type' => 'boarding', 'pet_type' => 'cat', 'check_in' => '2026-11-01', 'check_out' => '2026-11-02' ) );
t( 'estimate: a cat with no rate set', 'Boarding: 1 night' === $cat['lines'][0]['label'] && true === $cat['on_request'] );

done();
