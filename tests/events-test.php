<?php
/**
 * Events: sanitizing, saving, reading back, date windows, status and duplicating.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/post-stubs.php';

use Pawfectly\Events;

/** A valid event as the dashboard posts it: the X-Mas Party. */
function xmas( array $over = array() ): array {
	return array_replace_recursive(
		array(
			'title'       => 'X-Mas Party',
			'tagline'     => 'A jingle-bark holiday party for dogs of every breed',
			'emoji'       => '🎄',
			'start_date'  => '2026-12-19',
			'start_time'  => '10:00',
			'end_date'    => '2026-12-19',
			'end_time'    => '14:00',
			'venue'       => 'Paw-radise Indoor Dog Park',
			'address'     => 'Estero, FL',
			'price'       => '15',
			'free'        => false,
			'note'        => '🐾 FREE for Dog Park Members — call or text 239-420-7418 for your coupon code',
			'description' => '<p>Deck the paws!</p><script>x</script>',
			'ticket_url'  => '',
			'status'      => 'publish',
			'sections'    => array(
				'sessions' => array( 'rows' => array( array( 'from' => '10:00', 'to' => '12:00', 'name' => 'Session 1', 'who' => 'Small dogs (under 30 lbs)' ) ) ),
				'includes' => array( 'rows' => array( array( 'emoji' => '🐾', 'text' => 'Off-leash holiday play time' ), array( 'emoji' => '📸', 'text' => 'Festive photo booth' ) ) ),
			),
		),
		$over
	);
}

function fields( $r ): array {
	return is_wp_error( $r ) ? array_keys( $r->get_error_data()['fields'] ) : array();
}

Events::register();
t( 'post type and taxonomy registered, kept out of wp-admin', isset( $GLOBALS['paw_registered_types']['paw_event'] ) && false === $GLOBALS['paw_registered_types']['paw_event']['show_ui'] && 'event' === $GLOBALS['paw_registered_types']['paw_event']['rewrite']['slug'] && isset( $GLOBALS['paw_registered_tax']['paw_event_cat'] ) );

// Sanitizing.
$empty = Events::sanitize( array() );
t( 'sanitize: title, start, end and venue are required', array( 'title', 'start', 'end', 'venue' ) === fields( $empty ) );
$back = Events::sanitize( xmas( array( 'end_time' => '09:00' ) ) );
t( 'sanitize: the end must be after the start', array( 'end' ) === fields( $back ) && 'The end must be after the start.' === $back->get_error_data()['fields']['end'] );
t( 'sanitize: limits', array( 'title' ) === fields( Events::sanitize( xmas( array( 'title' => str_repeat( 'a', 121 ) ) ) ) ) );
$nine = xmas();
$nine['sections']['sessions']['rows'] = array_fill( 0, 9, array( 'from' => '10:00', 'to' => '12:00', 'name' => 'S', 'who' => '' ) );
t( 'sanitize: at most 8 sessions', array( 'sections.sessions' ) === fields( Events::sanitize( $nine ) ) );
t( 'sanitize: row text at most 300 characters', array( 'sections.includes.0.text' ) === fields( Events::sanitize( xmas( array( 'sections' => array( 'includes' => array( 'rows' => array( array( 'emoji' => '', 'text' => str_repeat( 'b', 301 ) ) ) ) ) ) ) ) ) );
$ok = Events::sanitize( xmas() );
t( 'sanitize: the X-Mas Party is valid', is_array( $ok ) );
t( 'sanitize: start and end stored as local date-times', '2026-12-19 10:00:00' === $ok['start'] && '2026-12-19 14:00:00' === $ok['end'] );
t( 'sanitize: the price is cleaned like Settings prices', '15.00' === $ok['price'] && array( 'price' ) === fields( Events::sanitize( xmas( array( 'price' => 'abc' ) ) ) ) );
t( 'sanitize: a free event has no price', '' === Events::sanitize( xmas( array( 'free' => true ) ) )['price'] );
t( 'sanitize: a session needs from before to', array( 'sections.sessions.0.to' ) === fields( Events::sanitize( xmas( array( 'sections' => array( 'sessions' => array( 'rows' => array( array( 'from' => '12:00', 'to' => '10:00' ) ) ) ) ) ) ) ) );
t( 'sanitize: a goodie needs a title', array( 'sections.goodies.0.title' ) === fields( Events::sanitize( xmas( array( 'sections' => array( 'goodies' => array( 'rows' => array( array( 'emoji' => '🎅', 'title' => '', 'text' => 'Props' ) ) ) ) ) ) ) ) );
$blank = xmas();
$blank['sections']['includes']['rows'][] = array( 'emoji' => '', 'text' => '  ' );
t( 'sanitize: empty rows are dropped, not refused', 2 === count( Events::sanitize( $blank )['sections']['includes']['rows'] ) );
foreach ( array( '👨‍👩‍👧‍👦', '🐕‍🦺', '👋🏽', '🎄' ) as $e ) {
	t( "sanitize: emoji $e kept intact", $e === Events::sanitize( xmas( array( 'emoji' => $e ) ) )['emoji'] );
}
t( 'sanitize: text is not an emoji', '' === Events::sanitize( xmas( array( 'emoji' => 'abc' ) ) )['emoji'] );
t( 'sanitize: more than 32 bytes of emoji is refused', array( 'emoji' ) === fields( Events::sanitize( xmas( array( 'emoji' => str_repeat( '🎄', 9 ) ) ) ) ) );
$odd = Events::sanitize( xmas( array( 'nonsense' => 'x', 'sections' => array( 'bogus' => array( 'rows' => array() ) ), 'ticket_url' => 'javascript:alert(1)' ) ) );
t( 'sanitize: unknown keys and sections dropped; unsafe links emptied', ! isset( $odd['nonsense'] ) && ! isset( $odd['sections']['bogus'] ) && '' === $odd['ticket_url'] );
t( 'sanitize: the description keeps the allowed tags only', '<p>Deck the paws!</p>x' === $ok['description'] );
t( 'sanitize: default headings filled', "Pick Your Pup's Session" === $ok['sections']['sessions']['heading'] && 'Everything Your {price} Ticket Includes' === $ok['sections']['includes']['heading'] && 'Reserve Your Spot Today!' === $ok['sections']['closing']['heading'] );
t( 'sanitize: status is publish or draft', 'draft' === Events::sanitize( xmas( array( 'status' => 'pending' ) ) )['status'] );

// Saving and reading back.
$GLOBALS['paw_options'] = array();
wp_insert_term( 'Holiday party', 'paw_event_cat', array( 'slug' => 'holiday' ) );
$ok['category'] = 'holiday';
$ok['photo_id'] = 55;
$id = Events::save( $ok );
$ev = Events::get( $id );
t( 'save and get round trip', 'X-Mas Party' === $ev['title'] && '🎄' === $ev['emoji'] && '2026-12-19 10:00:00' === $ev['start'] && '15.00' === $ev['price'] && 'Session 1' === $ev['sections']['sessions']['rows'][0]['name'] && 'publish' === $ev['status'] );
t( 'get: url, photo, category and colour', 'https://example.test/event/x-mas-party/' === $ev['url'] && 55 === $ev['photo_id'] && str_contains( (string) $ev['photo_url'], '55' ) && 'holiday' === $ev['category'] && 'Holiday party' === $ev['category_name'] && '#ff7f50' === $ev['color'] );
t( 'get: not an event', null === Events::get( wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Home' ) ) ) && null === Events::get( 99999 ) );
$fewer = $ok;
$fewer['sections']['includes']['rows'] = array( $ok['sections']['includes']['rows'][1] );
Events::save( $fewer, $id );
t( 'saving fewer rows replaces the old ones', 1 === count( Events::get( $id )['sections']['includes']['rows'] ) && 'Festive photo booth' === Events::get( $id )['sections']['includes']['rows'][0]['text'] );
$fewer['photo_id'] = 0;
Events::save( $fewer, $id );
t( 'removing the photo', 0 === Events::get( $id )['photo_id'] );

// Date windows.
$nye   = Events::save( Events::sanitize( xmas( array( 'title' => 'New Year Pawty', 'start_date' => '2026-12-31', 'end_date' => '2027-01-02' ) ) ) );
$draft = Events::save( Events::sanitize( xmas( array( 'title' => 'Secret draft', 'start_date' => '2026-12-20', 'end_date' => '2026-12-20', 'status' => 'draft' ) ) ) );
$dec   = Events::between( '2026-12-01', '2026-12-31' );
t( 'between: overlap, drafts left out, sorted by start', array( 'X-Mas Party', 'New Year Pawty' ) === array_column( $dec, 'title' ) );
t( 'between: an event crossing into January is in January too', array( 'New Year Pawty' ) === array_column( Events::between( '2027-01-01', '2027-01-31' ), 'title' ) );
t( 'between: search is literal', array() === Events::between( '2026-12-01', '2026-12-31', '%' ) && array( 'X-Mas Party' ) === array_column( Events::between( '2026-12-01', '2026-12-31', 'x-mas' ), 'title' ) );
t( 'between: category', array( 'X-Mas Party' ) === array_column( Events::between( '2026-12-01', '2026-12-31', '', 'holiday' ), 'title' ) );
t( 'between: drafts when asked', 3 === count( Events::between( '2026-12-01', '2026-12-31', '', '', array( 'publish', 'draft' ) ) ) );

// Status.
$tz = new DateTimeZone( 'UTC' );
t( 'status: upcoming, current, past', 'upcoming' === Events::status( $ev, new DateTimeImmutable( '2026-12-18 09:00', $tz ) ) && 'current' === Events::status( $ev, new DateTimeImmutable( '2026-12-19 11:00', $tz ) ) && 'past' === Events::status( $ev, new DateTimeImmutable( '2026-12-19 14:01', $tz ) ) );

// Duplicate and categories.
$copy = Events::get( Events::duplicate( $id ) );
t( 'duplicate: a draft copy with the same sections', 'X-Mas Party (copy)' === $copy['title'] && 'draft' === $copy['status'] && $copy['sections'] === Events::get( $id )['sections'] && 'holiday' === $copy['category'] );
update_term_meta( get_term_by( 'slug', 'holiday', 'paw_event_cat' )->term_id, '_paw_color', '#0f42c0' );
t( 'categories: slug, name and colour', array( array( 'slug' => 'holiday', 'name' => 'Holiday party', 'color' => '#0f42c0' ) ) === Events::categories() && '#0f42c0' === Events::get( $id )['color'] );
t( 'palette starts with coral', 8 === count( Events::PALETTE ) && '#ff7f50' === Events::PALETTE[0] );

// /event/ links work after an update: rewrite rules are refreshed once per plugin version.
$GLOBALS['paw_flushes'] = 0;
function flush_rewrite_rules( $hard = true ) { $GLOBALS['paw_flushes']++; }
Events::maybe_flush();
Events::maybe_flush();
t( 'rewrite rules flushed once per version', 1 === $GLOBALS['paw_flushes'] && PAW_VERSION === get_option( 'paw_rewrite_version' ) );

done();
