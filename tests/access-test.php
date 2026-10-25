<?php
/**
 * Who reaches wp-admin: the admin-email account and Developers only; the escape constant opens it.
 * Run: php tests/access-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
function get_permalink( $id = 0 ) { return ''; }

use Pawfectly\Access;
use Pawfectly\Activator;

$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';

$owner = paw_test_user( 'Owner@Example.com', 'pass word1', array( 'paw_staff' ), array( Activator::CAPABILITY => true ) );
$dev   = paw_test_user( 'dev@example.com', 'pass word1', array( 'developer' ) );
$admin = paw_test_user( 'other-admin@example.com', 'pass word1', array( 'administrator' ) );
$cust  = paw_test_user( 'jay@example.com', 'pass word1', array( 'paw_customer' ) );

t( 'admin-email account counts, whatever its role and letter case', Access::is_admin_user( $owner ) );
t( 'developer counts', Access::is_admin_user( $dev ) );
t( 'another administrator does not', ! Access::is_admin_user( $admin ) );
t( 'a visitor does not', ! Access::is_admin_user( new WP_User() ) );

t( 'settings: administrators', Access::can_manage_settings( $admin ) );
t( 'settings: admin-email account', Access::can_manage_settings( $owner ) );
t( 'settings: not customers', ! Access::can_manage_settings( $cust ) );

t( 'gate: other administrator is sent away', is_string( Access::gate_target( $admin, 'index.php', false, false ) ) );
t( 'gate: ajax passes', null === Access::gate_target( $admin, 'admin-ajax.php', true, false ) );
t( 'gate: admin-post (file downloads) passes', null === Access::gate_target( $cust, 'admin-post.php', false, false ) );
t( 'gate: async-upload (logo picker) passes', null === Access::gate_target( $cust, 'async-upload.php', false, false ) );
t( 'gate: cron passes', null === Access::gate_target( $cust, 'index.php', false, true ) );
t( 'gate: developer passes', null === Access::gate_target( $dev, 'index.php', false, false ) );
t( 'gate: admin-email passes', null === Access::gate_target( $owner, 'index.php', false, false ) );

wp_set_current_user( $cust->ID );
t( 'toolbar hidden for customers', false === Access::toolbar( true ) );
wp_set_current_user( $dev->ID );
t( 'toolbar kept for developers', true === Access::toolbar( true ) );
t( 'toolbar stays off if something else turned it off', false === Access::toolbar( false ) );

get_role( 'administrator' )->add_cap( 'manage_options' );
Access::add_developer_role();
t( 'developer role created with admin capabilities', get_role( 'developer' ) && get_role( 'developer' )->has_cap( 'manage_options' ) && get_role( 'developer' )->has_cap( Activator::CAPABILITY ) );
$GLOBALS['paw_roles']['developer'] = new Fake_Role( 'developer' );
$GLOBALS['paw_roles']['developer']->add_cap( 'read' );
Access::add_developer_role();
t( 'an existing developer role is left alone', ! get_role( 'developer' )->has_cap( 'manage_options' ) );

t( 'not open by default', ! Access::open() );
define( 'PAW_WP_ADMIN_OPEN', true );
t( 'escape constant opens it', Access::open() );
t( 'gate: everyone passes when open', null === Access::gate_target( $cust, 'index.php', false, false ) );
wp_set_current_user( $cust->ID );
t( 'toolbar back when open', true === Access::toolbar( true ) );

done();
