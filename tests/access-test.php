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
function get_users( $args = array() ) { return array_values( array_filter( $GLOBALS['paw_users'], fn( $u ) => in_array( $args['role'] ?? '', $u->roles, true ) ) ); }

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

// The admin email must not be claimable: a plain customer account with that email counts only once verified.
$GLOBALS['paw_options']['admin_email'] = 'site@example.com';
$claimer = paw_test_user( 'Site@Example.com', 'pass word1', array( 'paw_customer' ) );
t( 'unverified customer account using the admin email does not count', ! Access::is_admin_user( $claimer ) );
update_user_meta( $claimer->ID, 'paw_email_verified', '1' );
t( 'once that inbox has confirmed it, it counts', Access::is_admin_user( $claimer ) );
$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';

t( 'settings: administrators', Access::can_manage_settings( $admin ) );
t( 'settings: admin-email account', Access::can_manage_settings( $owner ) );
t( 'settings: not customers', ! Access::can_manage_settings( $cust ) );

t( 'gate: other administrator is sent away', is_string( Access::gate_target( $admin, 'index.php', false, false ) ) );
$GLOBALS['paw_options']['admin_email'] = 'nobody-has-this@example.com';
$saved_dev_roles = $dev->roles;
$dev->roles = array( 'administrator' );
t( 'gate: nobody could get in (no admin-email account, no developer): administrators are let through', null === Access::gate_target( $admin, 'index.php', false, false ) );
t( 'gate: but not customers', is_string( Access::gate_target( $cust, 'index.php', false, false ) ) );
$dev->roles = $saved_dev_roles;
$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';
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
