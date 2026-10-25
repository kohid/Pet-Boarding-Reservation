<?php
/**
 * Sign-up and profile validation.
 * Run: php tests/account-rules-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\AccountRules;

$good = array( 'first_name' => 'Zoë', 'last_name' => "O'Brien", 'email' => 'zoe@example.com', 'phone' => '555 010 2030', 'password' => 'correct horse', 'confirm' => 'correct horse' );

$r = AccountRules::signup( $good );
t( 'good sign-up passes', array() === $r['errors'] );
t( 'clean values kept as typed', "O'Brien" === $r['clean']['last_name'] && 'zoe@example.com' === $r['clean']['email'] );
t( 'password not echoed into clean data', ! isset( $r['clean']['password'] ) );
t( 'first name required', isset( AccountRules::signup( array_merge( $good, array( 'first_name' => '' ) ) )['errors']['first_name'] ) );
t( 'bad email', 'Please enter a valid email address.' === ( AccountRules::signup( array_merge( $good, array( 'email' => 'nope' ) ) )['errors']['email'] ?? '' ) );
t( 'phone optional', array() === AccountRules::signup( array_merge( $good, array( 'phone' => '' ) ) )['errors'] );
t( 'bad phone', isset( AccountRules::signup( array_merge( $good, array( 'phone' => '12' ) ) )['errors']['phone'] ) );
t( 'confirm is required: a missing one does not match', "The two passwords don't match." === ( AccountRules::signup( array_diff_key( $good, array( 'confirm' => 1 ) ) )['errors']['password'] ?? '' ) );
t( 'confirm must match', "The two passwords don't match." === ( AccountRules::signup( array_merge( $good, array( 'confirm' => 'something else' ) ) )['errors']['password'] ?? '' ) );
t( 'short password', 'Use at least 8 characters.' === ( AccountRules::signup( array_merge( $good, array( 'password' => 'short' ) ) )['errors']['password'] ?? '' ) );
t( 'confirmation must match', "The two passwords don't match." === AccountRules::password( 'correct horse', 'correct house' ) );
t( 'no confirmation is fine', null === AccountRules::password( 'correct horse', null ) );
t( 'details skip the password', array() === AccountRules::details( array_diff_key( $good, array( 'password' => 1 ) ) )['errors'] );
t( 'arrays are not text', isset( AccountRules::signup( array_merge( $good, array( 'email' => array( 'x' ) ) ) )['errors']['email'] ) );

done();
