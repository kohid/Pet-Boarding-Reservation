<?php
/**
 * The account menu: which items each kind of visitor sees, and avatar initials.
 * Run: php tests/user-menu-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\UserMenu;

$urls = array( 'login' => '/profile/', 'signup' => '/profile/#signup', 'profile' => '/profile/', 'bookings' => '/my-bookings/', 'dashboard' => '/dashboard/', 'settings' => '/wp-admin/admin.php?page=paw-settings', 'logout' => '/logout' );
$keys = static fn( array $items ): array => array_map( fn( $i ) => $i['key'] ?? '—', $items );

t( 'guest: sign in and sign up', array( 'login', 'signup' ) === $keys( UserMenu::items( array( 'logged_in' => false, 'staff' => false, 'admin' => false, 'urls' => $urls ) ) ) );
t( 'owner: bookings, profile, log out', array( 'bookings', 'profile', '—', 'logout' ) === $keys( UserMenu::items( array( 'logged_in' => true, 'staff' => false, 'admin' => false, 'urls' => $urls ) ) ) );
t( 'staff: dashboard first', array( 'dashboard', 'bookings', 'profile', '—', 'logout' ) === $keys( UserMenu::items( array( 'logged_in' => true, 'staff' => true, 'admin' => false, 'urls' => $urls ) ) ) );
t( 'admin: settings too', array( 'dashboard', 'bookings', 'profile', 'settings', '—', 'logout' ) === $keys( UserMenu::items( array( 'logged_in' => true, 'staff' => true, 'admin' => true, 'urls' => $urls ) ) ) );
t( 'items without a page are left out', array( 'profile', '—', 'logout' ) === $keys( UserMenu::items( array( 'logged_in' => true, 'staff' => false, 'admin' => false, 'urls' => array_merge( $urls, array( 'bookings' => '' ) ) ) ) ) );
t( 'guest without sign-up page', array( 'login' ) === $keys( UserMenu::items( array( 'logged_in' => false, 'staff' => false, 'admin' => false, 'urls' => array_merge( $urls, array( 'signup' => '' ) ) ) ) ) );
$wp = array( 'logged_in' => true, 'staff' => true, 'admin' => true, 'wp_admin' => true, 'urls' => array_merge( $urls, array( 'wp_admin' => '/wp-admin/' ) ) );
t( 'admin-email account / developer: Admin Panel after Settings, before Log Out', array( 'dashboard', 'bookings', 'profile', 'settings', 'wp_admin', '—', 'logout' ) === $keys( UserMenu::items( $wp ) ) );
t( 'Admin Panel label and link', (bool) array_filter( UserMenu::items( $wp ), fn( $i ) => ( $i['key'] ?? '' ) === 'wp_admin' && 'Admin Panel' === $i['label'] && '/wp-admin/' === $i['url'] ) );
t( 'other administrators: no Admin Panel', ! in_array( 'wp_admin', $keys( UserMenu::items( array_merge( $wp, array( 'wp_admin' => false ) ) ) ), true ) );
t( 'labels', 'My Bookings' === UserMenu::items( array( 'logged_in' => true, 'staff' => false, 'admin' => false, 'urls' => $urls ) )[0]['label'] );
t( 'initials: two names', 'JM' === UserMenu::initials( 'Jay Miralles' ) );
t( 'initials: one name, accents', 'Z' === UserMenu::initials( 'zoë' ) );
t( 'initials: three names use first and last', 'AL' === UserMenu::initials( 'Ada King Lovelace' ) );
t( 'initials: empty', '?' === UserMenu::initials( '  ' ) );
t( 'initials: accented first letter', 'ÉO' === UserMenu::initials( "élise o'brien" ) );

done();
