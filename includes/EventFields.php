<?php
/**
 * Every Add Event field as text, a link, an image or a colour: what the Elementor Dynamic Tags
 * (ElementorTags) and the event page show. The member code has no field: it never leaves the server.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class EventFields {

	/** Text fields, in the order the tags list them. */
	public const TEXT = array( 'title', 'tagline', 'emoji', 'category', 'start_date', 'start_time', 'end_date', 'end_time', 'date_line', 'day', 'time_range', 'summary', 'venue', 'address', 'where', 'price', 'price_text', 'free', 'ticket_text', 'note', 'description', 'spots', 'spots_left', 'section_heading', 'section_intro' );
	public const LINKS = array( 'tickets', 'ticket_url', 'url', 'google', 'outlook', 'ics', 'map' );
	/** Sections with a heading and an intro. */
	public const SECTIONS = array( 'sessions', 'includes', 'goodies', 'good_to_know', 'closing' );
	public const DATE_FORMAT = 'l, F jS, Y';
	public const TIME_FORMAT = 'g:i A';

	/**
	 * The event being shown (the queried event, or the current post in a loop), or the one with
	 * $id. Null when it isn't an event, or is a draft and the viewer isn't staff.
	 */
	public static function current( int $id = 0 ): ?array {
		$ev = Events::get( $id ? $id : (int) get_the_ID() );
		if ( ! $ev || ( 'publish' !== $ev['status'] && ! current_user_can( Activator::CAPABILITY ) ) ) {
			return null;
		}
		return $ev;
	}

	/**
	 * One text field. $opts: 'format' (dates and times), 'section' (section_heading/intro),
	 * 'session' (spots_left: 1-based, 0 for an event without sessions), 'text' (free).
	 */
	public static function value( array $ev, string $field, array $opts = array() ): string {
		switch ( $field ) {
			case 'title':
			case 'tagline':
			case 'emoji':
			case 'venue':
			case 'address':
			case 'note':
				return (string) $ev[ $field ];
			case 'category':
				return (string) $ev['category_name'];
			case 'start_date':
			case 'end_date':
				return EventDates::at( $ev[ substr( $field, 0, -5 ) ] )->format( self::format( $opts, self::DATE_FORMAT ) );
			case 'start_time':
			case 'end_time':
				return EventDates::at( $ev[ substr( $field, 0, -5 ) ] )->format( self::format( $opts, self::TIME_FORMAT ) );
			case 'date_line':
				return EventDates::date_line( $ev );
			case 'day':
				return EventDates::day( $ev );
			case 'time_range':
				return EventDates::time_range( $ev );
			case 'summary':
				return EventDates::summary( $ev );
			case 'where':
				return EventDates::where( $ev );
			case 'price':
				return $ev['free'] || '' === (string) $ev['price'] ? '' : self::number( (float) $ev['price'] );
			case 'price_text':
				return EventDates::price_text( $ev );
			case 'free':
				return $ev['free'] ? (string) ( $opts['text'] ?? __( 'Free', 'pawfectly-reservations' ) ) : '';
			case 'ticket_text':
				return $ev['free'] || '' === (string) $ev['price']
					? __( 'Get Tickets', 'pawfectly-reservations' )
					/* translators: %s: ticket price, like "$15" */
					: sprintf( __( 'Get Tickets — %s', 'pawfectly-reservations' ), Services::money( (float) $ev['price'] ) );
			case 'description':
				$text = preg_replace( '#</(p|li|h[1-6]|div|blockquote)>|<br\s*/?>#i', ' ', (string) $ev['description'] );
				return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
			case 'spots':
				return (int) $ev['spots'] > 0 ? (string) (int) $ev['spots'] : '';
			case 'spots_left':
				return self::spots_left( $ev, (int) ( $opts['session'] ?? 1 ) );
			case 'section_heading':
			case 'section_intro':
				$section = (string) ( $opts['section'] ?? '' );
				$part    = 'section_heading' === $field ? 'heading' : 'intro';
				return in_array( $section, self::SECTIONS, true ) ? EventDates::fill( (string) ( $ev['sections'][ $section ][ $part ] ?? '' ), $ev ) : '';
		}
		return '';
	}

	/**
	 * One link: tickets (the ticket link, or the event page's sign-up form, so it also works from a
	 * Loop Grid card), ticket_url, url, google, outlook, ics, map.
	 */
	public static function link( array $ev, string $which ): string {
		switch ( $which ) {
			case 'tickets':
				return '' !== $ev['ticket_url'] ? $ev['ticket_url'] : $ev['url'] . '#paw-event-reserve';
			case 'ticket_url':
				return (string) $ev['ticket_url'];
			case 'url':
				return (string) $ev['url'];
			case 'google':
			case 'outlook':
			case 'ics':
				return EventDates::links( $ev )[ $which ];
			case 'map':
				return 'https://www.google.com/maps/search/?api=1&query=' . urlencode( EventDates::where( $ev ) );
		}
		return '';
	}

	/** The cover photo as Elementor's image value, or an empty array. */
	public static function image( array $ev ): array {
		return $ev['photo_id'] && '' !== $ev['photo_url'] ? array( 'id' => (int) $ev['photo_id'], 'url' => $ev['photo_url'] ) : array();
	}

	public static function color( array $ev ): string {
		return (string) $ev['color'];
	}

	private static function spots_left( array $ev, int $session ): string {
		$rows = $ev['sections']['sessions']['rows'];
		$n    = $rows ? $session - 1 : -1;
		if ( $rows && ! isset( $rows[ $n ] ) ) {
			return '';
		}
		$left = Signups::spots_left( $ev, $n );
		if ( null === $left ) {
			return '';
		}
		/* translators: %d: spots left */
		return 0 === $left ? __( 'Full', 'pawfectly-reservations' ) : sprintf( _n( '%d spot left', '%d spots left', $left, 'pawfectly-reservations' ), $left );
	}

	private static function format( array $opts, string $fallback ): string {
		$format = trim( (string) ( $opts['format'] ?? '' ) );
		return '' !== $format ? $format : $fallback;
	}

	/** "15" or "12.50". */
	private static function number( float $n ): string {
		return abs( $n - round( $n ) ) < 0.005 ? (string) (int) round( $n ) : number_format( $n, 2, '.', '' );
	}
}
