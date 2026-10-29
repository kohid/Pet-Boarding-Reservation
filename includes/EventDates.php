<?php
/**
 * How an event's dates, times and price read on its page, cards and calendar, plus its
 * Add to calendar links (Google, Outlook, .ics). Event times are local to the site.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class EventDates {

	/** "One Day Only · Saturday, December 19th, 2026", or the first and last day. */
	public static function date_line( array $ev ): string {
		$a = self::at( $ev['start'] );
		$b = self::at( $ev['end'] );
		if ( $a->format( 'Y-m-d' ) === $b->format( 'Y-m-d' ) ) {
			/* translators: %s: the event's day, like "Saturday, December 19th, 2026" */
			return sprintf( __( 'One Day Only · %s', 'pawfectly-reservations' ), self::day( $ev ) );
		}
		$first = $a->format( 'Y' ) === $b->format( 'Y' ) ? $a->format( 'l, F jS' ) : $a->format( 'l, F jS, Y' );
		return $first . ' – ' . $b->format( 'l, F jS, Y' );
	}

	/** "Saturday, December 19th, 2026": the day the event starts. */
	public static function day( array $ev ): string {
		return self::at( $ev['start'] )->format( 'l, F jS, Y' );
	}

	/** "10:00 AM – 2:00 PM". */
	public static function time_range( array $ev ): string {
		return self::times( self::at( $ev['start'] )->format( 'H:i' ), self::at( $ev['end'] )->format( 'H:i' ) );
	}

	/** Two 'H:i' times as "10:00 AM – 12:00 PM", or "12:00 – 2:00 PM" when both are in the same half of the day. */
	public static function times( string $from, string $to ): string {
		$a = \DateTimeImmutable::createFromFormat( '!H:i', $from );
		$b = \DateTimeImmutable::createFromFormat( '!H:i', $to );
		if ( ! $a || ! $b ) {
			return '';
		}
		return ( $a->format( 'A' ) === $b->format( 'A' ) ? $a->format( 'g:i' ) : $a->format( 'g:i A' ) ) . ' – ' . $b->format( 'g:i A' );
	}

	/** "$15 per dog", "Free", or '' when the event has no price. */
	public static function price_text( array $ev ): string {
		if ( ! empty( $ev['free'] ) ) {
			return __( 'Free', 'pawfectly-reservations' );
		}
		if ( '' === (string) ( $ev['price'] ?? '' ) ) {
			return '';
		}
		/* translators: %s: price, like "$15" */
		return sprintf( __( '%s per dog', 'pawfectly-reservations' ), Services::money( (float) $ev['price'] ) );
	}

	/** "Saturday, December 19th, 2026, 10:00 AM – 2:00 PM — $15 per dog". */
	public static function summary( array $ev ): string {
		$a    = self::at( $ev['start'] );
		$b    = self::at( $ev['end'] );
		$when = $a->format( 'Y-m-d' ) === $b->format( 'Y-m-d' )
			? self::day( $ev ) . ', ' . self::time_range( $ev )
			: $a->format( 'l, F jS, Y, g:i A' ) . ' – ' . $b->format( 'l, F jS, Y, g:i A' );
		$price = self::price_text( $ev );
		return $when . ( '' !== $price ? ' — ' . $price : '' );
	}

	/** Replaces {price} with "$15", or "Free". */
	public static function fill( string $text, array $ev ): string {
		$price = ! empty( $ev['free'] ) ? __( 'Free', 'pawfectly-reservations' ) : ( '' !== (string) ( $ev['price'] ?? '' ) ? Services::money( (float) $ev['price'] ) : '' );
		return trim( preg_replace( '/\s+/', ' ', str_replace( '{price}', $price, $text ) ) );
	}

	/** @return array{google:string, outlook:string, ics:string} */
	public static function links( array $ev ): array {
		$a     = self::at( $ev['start'] );
		$b     = self::at( $ev['end'] );
		$where = self::where( $ev );
		$about = (string) ( $ev['tagline'] ?? '' );

		$utc    = self::offset_zone();
		$google = array(
			'action'   => 'TEMPLATE',
			'text'     => $ev['title'],
			'dates'    => self::stamp( $a, $utc ) . '/' . self::stamp( $b, $utc ),
			'details'  => trim( $about . "\n" . ( $ev['url'] ?? '' ) ),
			'location' => $where,
		);
		if ( ! $utc ) {
			$google['ctz'] = wp_timezone()->getName();
		}
		$outlook = array(
			'path'     => '/calendar/action/compose',
			'rru'      => 'addevent',
			'subject'  => $ev['title'],
			'startdt'  => $a->format( 'Y-m-d\TH:i:s' ),
			'enddt'    => $b->format( 'Y-m-d\TH:i:s' ),
			'body'     => trim( $about . "\n" . ( $ev['url'] ?? '' ) ),
			'location' => $where,
		);
		return array(
			'google'  => 'https://calendar.google.com/calendar/render?' . http_build_query( $google ),
			'outlook' => 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query( $outlook ),
			'ics'     => ( $ev['url'] ?? '' ) . ( str_contains( (string) ( $ev['url'] ?? '' ), '?' ) ? '&' : '?' ) . 'paw_ics=1',
		);
	}

	/** The event as an .ics file (one VEVENT, times in the site's timezone). */
	public static function ics( array $ev ): string {
		$host = (string) parse_url( (string) ( $ev['url'] ?? '' ), PHP_URL_HOST );
		$esc  = static fn( string $s ): string => str_replace( array( '\\', ',', ';', "\r\n", "\n" ), array( '\\\\', '\\,', '\\;', '\\n', '\\n' ), $s );
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Pawfectly//Events//EN',
			'CALSCALE:GREGORIAN',
			'BEGIN:VEVENT',
			'UID:paw-event-' . (int) ( $ev['id'] ?? 0 ) . '@' . $host,
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			self::ics_time( 'DTSTART', self::at( $ev['start'] ) ),
			self::ics_time( 'DTEND', self::at( $ev['end'] ) ),
			'SUMMARY:' . $esc( (string) $ev['title'] ),
			'LOCATION:' . $esc( self::where( $ev ) ),
			'DESCRIPTION:' . $esc( (string) ( $ev['tagline'] ?? '' ) ),
			'URL:' . ( $ev['url'] ?? '' ),
			'END:VEVENT',
			'END:VCALENDAR',
		);
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/** Links US-style phone numbers ("239-420-7418", "(239) 420-7418") in already-escaped text. */
	public static function phone_links( string $escaped_html ): string {
		return (string) preg_replace_callback(
			'/(?<![\d>])\(?(\d{3})\)?[\s.-]?(\d{3})[\s.-](\d{4})(?!\d)/',
			static fn( array $m ): string => '<a href="tel:' . $m[1] . $m[2] . $m[3] . '">' . $m[0] . '</a>',
			$escaped_html
		);
	}

	/**
	 * True when the site uses a UTC offset ("UTC-5", or the default "UTC+0") instead of a city.
	 * "-05:00" is not a timezone calendar apps know, so those sites send times in UTC.
	 */
	private static function offset_zone(): bool {
		$name = wp_timezone()->getName();
		return '' !== $name && ( '+' === $name[0] || '-' === $name[0] );
	}

	/** Local wall time ("20261219T100000"), or UTC with a Z for offset-only sites. */
	private static function stamp( \DateTimeImmutable $at, bool $utc ): string {
		return $utc ? $at->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' ) : $at->format( 'Ymd\THis' );
	}

	private static function ics_time( string $prop, \DateTimeImmutable $at ): string {
		return self::offset_zone() ? $prop . ':' . self::stamp( $at, true ) : $prop . ';TZID=' . wp_timezone()->getName() . ':' . self::stamp( $at, false );
	}

	/** "Venue, Address", whichever are set. */
	public static function where( array $ev ): string {
		return implode( ', ', array_filter( array( (string) ( $ev['venue'] ?? '' ), (string) ( $ev['address'] ?? '' ) ), 'strlen' ) );
	}

	/** A stored local 'Y-m-d H:i:s' as a moment in the site's timezone. */
	public static function at( string $local ): \DateTimeImmutable {
		return \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $local, wp_timezone() ) ?: new \DateTimeImmutable( 'now', wp_timezone() );
	}
}
