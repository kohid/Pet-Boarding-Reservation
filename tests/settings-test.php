<?php
/**
 * Settings are sanitised on save, fall back to sensible defaults, and grant staff access to the ticked roles.
 * Run: php tests/settings-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Activator;
use Pawfectly\Settings;

$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';

$d = Settings::defaults();
t( 'default business name', 'Pawfectly' === $d['business_name'] );
t( 'default staff email is the admin email', 'owner@example.com' === $d['staff_emails'] );
t( 'default country', 'US' === $d['default_country'] );
t( 'default intro is the mock-up copy', str_starts_with( $d['intro'], "We're excited to care for your furry family member!" ) );
t( 'default waiver is the mock-up copy', str_contains( $d['waiver'], 'I understand that boarding my pet involves inherent risks.' ) && str_starts_with( $d['waiver'], '<p>' ) );
t( 'get() fills missing keys', 'Pawfectly' === Settings::get()['business_name'] );

$GLOBALS['paw_options'][ Settings::OPTION ] = array( 'business_name' => 'Pawfectly Downtown' );
t( 'get() keeps saved values', 'Pawfectly Downtown' === Settings::get()['business_name'] && 'US' === Settings::get()['default_country'] );

$s = Settings::sanitize(
	array(
		'business_name'   => '  <b>Paw</b>fectly ',
		'logo_id'         => '42abc',
		'staff_emails'    => 'a@example.com, nope, b@example.com',
		'intro'           => "Hello\n<script>x</script>there",
		'waiver'          => '<p>Terms <strong>here</strong></p><script>alert(1)</script><iframe></iframe>',
		'default_country' => 'ZZ',
		'roles'           => array( 'editor', 'ghost' ),
	)
);
t( 'business name stripped', 'Pawfectly' === $s['business_name'] );
t( 'logo id is an integer', 42 === $s['logo_id'] );
t( 'invalid emails dropped', 'a@example.com, b@example.com' === $s['staff_emails'] );
t( 'intro tags stripped, lines kept', "Hello\nxthere" === $s['intro'] );
t( 'waiver keeps allowed tags only', '<p>Terms <strong>here</strong></p>alert(1)' === $s['waiver'] );
t( 'unknown country falls back to US', 'US' === $s['default_country'] );
t( 'unknown roles dropped', array( 'editor' ) === $s['roles'] );
t( 'ticked role gets the capability', get_role( 'editor' )->has_cap( Activator::CAPABILITY ) );
t( 'administrator always keeps it', get_role( 'administrator' )->has_cap( Activator::CAPABILITY ) );

$s2 = Settings::sanitize( array( 'roles' => array( 'shop_manager' ) ) );
t( 'unticked role loses the capability', ! get_role( 'editor' )->has_cap( Activator::CAPABILITY ) && get_role( 'shop_manager' )->has_cap( Activator::CAPABILITY ) );
t( 'empty staff emails fall back to the admin email', 'owner@example.com' === $s2['staff_emails'] );
t( 'empty business name falls back to Pawfectly', 'Pawfectly' === $s2['business_name'] );
t( 'staff email list helper', array( 'a@example.com', 'b@example.com' ) === Settings::staff_emails( array( 'staff_emails' => 'a@example.com, b@example.com' ) ) );

done();
