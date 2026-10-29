<?php
/**
 * Elementor Pro Dynamic Tags for event templates: a "Pawfectly Event" group with one tag per
 * Add Event field (text, links, the cover photo, the category colour). Values come from
 * EventFields for the event being shown; the tag classes live in includes/elementor/tags.php,
 * loaded only here, once Elementor's base classes exist.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class ElementorTags {

	public const GROUP = 'pawfectly-event';

	public static function init(): void {
		add_action( 'elementor/dynamic_tags/register', array( self::class, 'register' ) );
	}

	/** @param object $manager Elementor's Dynamic Tags manager. */
	public static function register( $manager ): void {
		if ( ! class_exists( '\ElementorPro\Plugin' ) ) {
			return; // Dynamic Tags are an Elementor Pro feature.
		}
		require_once PAW_DIR . 'includes/elementor/tags.php';
		$manager->register_group( self::GROUP, array( 'title' => __( 'Pawfectly Event', 'pawfectly-reservations' ) ) );
		foreach ( Elementor\TAGS as $class ) {
			$manager->register( new $class() );
		}
	}

	/** Each field's label in Elementor's Dynamic Tags menu. */
	public static function title( string $field ): string {
		$titles = array(
			'title'           => __( 'Event title', 'pawfectly-reservations' ),
			'tagline'         => __( 'Tagline', 'pawfectly-reservations' ),
			'emoji'           => __( 'Emoji', 'pawfectly-reservations' ),
			'category'        => __( 'Category', 'pawfectly-reservations' ),
			'start_date'      => __( 'Start date', 'pawfectly-reservations' ),
			'start_time'      => __( 'Start time', 'pawfectly-reservations' ),
			'end_date'        => __( 'End date', 'pawfectly-reservations' ),
			'end_time'        => __( 'End time', 'pawfectly-reservations' ),
			'date_line'       => __( 'Date line', 'pawfectly-reservations' ),
			'day'             => __( 'Day', 'pawfectly-reservations' ),
			'time_range'      => __( 'Time range', 'pawfectly-reservations' ),
			'summary'         => __( 'Summary line', 'pawfectly-reservations' ),
			'venue'           => __( 'Venue', 'pawfectly-reservations' ),
			'address'         => __( 'Address', 'pawfectly-reservations' ),
			'where'           => __( 'Venue + address', 'pawfectly-reservations' ),
			'price'           => __( 'Price (number)', 'pawfectly-reservations' ),
			'price_text'      => __( 'Price text', 'pawfectly-reservations' ),
			'free'            => __( 'Free badge', 'pawfectly-reservations' ),
			'ticket_text'     => __( 'Ticket button text', 'pawfectly-reservations' ),
			'note'            => __( 'Highlight note', 'pawfectly-reservations' ),
			'description'     => __( 'Description (plain text)', 'pawfectly-reservations' ),
			'description_html' => __( 'Description (formatted)', 'pawfectly-reservations' ),
			'spots'           => __( 'Spots per session', 'pawfectly-reservations' ),
			'spots_left'      => __( 'Spots left', 'pawfectly-reservations' ),
			'section_heading' => __( 'Section heading', 'pawfectly-reservations' ),
			'section_intro'   => __( 'Section intro', 'pawfectly-reservations' ),
			'section_rows'    => __( 'Section rows (list)', 'pawfectly-reservations' ),
			'tickets'         => __( 'Tickets / sign-up link', 'pawfectly-reservations' ),
			'ticket_url'      => __( 'Ticket link', 'pawfectly-reservations' ),
			'url'             => __( 'Event page link', 'pawfectly-reservations' ),
			'google'          => __( 'Google Calendar link', 'pawfectly-reservations' ),
			'outlook'         => __( 'Outlook link', 'pawfectly-reservations' ),
			'ics'             => __( 'Apple / iCal (.ics) link', 'pawfectly-reservations' ),
			'map'             => __( 'Map link', 'pawfectly-reservations' ),
			'photo'           => __( 'Cover photo', 'pawfectly-reservations' ),
			'color'           => __( 'Category colour', 'pawfectly-reservations' ),
		);
		return $titles[ $field ] ?? $field;
	}

	/** The Section control's choices. */
	public static function sections(): array {
		return array(
			'sessions'     => __( 'Sessions', 'pawfectly-reservations' ),
			'includes'     => __( 'Ticket includes', 'pawfectly-reservations' ),
			'goodies'      => __( 'Extra goodies', 'pawfectly-reservations' ),
			'good_to_know' => __( 'Good to know', 'pawfectly-reservations' ),
			'closing'      => __( 'Closing call to action', 'pawfectly-reservations' ),
		);
	}
}
