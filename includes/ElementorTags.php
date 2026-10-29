<?php
/**
 * Elementor Pro Dynamic Tags for event templates: groups that follow the Add Event screen (Basics,
 * When & where, Price & tickets, Description, each repeater, Closing, Calendar & links), with a
 * tag for every field, each repeater's rows as a list, and one field of any row. Values come from
 * EventFields for the event being shown; the tag classes live in includes/elementor/tags.php,
 * loaded only here, once Elementor's base classes exist.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class ElementorTags {

	/** Dynamic Tag groups, as on the Add Event screen; Elementor lists them in this order. */
	private const GROUPS = array( 'basics', 'when', 'price', 'description', 'sessions', 'includes', 'goodies', 'good_to_know', 'closing', 'links', 'any' );
	/** Which group each single-field tag (Text_Tag, Link_Tag) belongs to. */
	private const FIELD_GROUPS = array(
		'basics'      => array( 'title', 'tagline', 'emoji', 'category' ),
		'when'        => array( 'start_date', 'start_time', 'end_date', 'end_time', 'date_line', 'day', 'time_range', 'summary', 'venue', 'address', 'where', 'map' ),
		'price'       => array( 'price', 'price_text', 'free', 'ticket_text', 'note', 'spots', 'spots_left', 'tickets', 'ticket_url' ),
		'description' => array( 'description', 'description_html' ),
		'links'       => array( 'url', 'google', 'outlook', 'ics' ),
		'any'         => array( 'section_heading', 'section_intro', 'section_rows' ),
	);

	public static function init(): void {
		add_action( 'elementor/dynamic_tags/register', array( self::class, 'register' ) );
	}

	/** @param object $manager Elementor's Dynamic Tags manager. */
	public static function register( $manager ): void {
		if ( ! class_exists( '\ElementorPro\Plugin' ) ) {
			return; // Dynamic Tags are an Elementor Pro feature.
		}
		require_once PAW_DIR . 'includes/elementor/tags.php';
		$titles = self::group_titles();
		foreach ( self::GROUPS as $key ) {
			$manager->register_group( self::group( $key ), array( 'title' => $titles[ $key ] ) );
		}
		foreach ( Elementor\TAGS as $class ) {
			$manager->register( new $class() );
		}
	}

	/** A group's Elementor name. */
	public static function group( string $key ): string {
		return 'pawfectly-event-' . str_replace( '_', '-', $key );
	}

	/** The group a single-field tag belongs to. */
	public static function field_group( string $field ): string {
		foreach ( self::FIELD_GROUPS as $key => $fields ) {
			if ( in_array( $field, $fields, true ) ) {
				return $key;
			}
		}
		return 'basics';
	}

	/** The groups' titles; repeaters say so. */
	public static function group_titles(): array {
		return array(
			'basics'       => __( 'Event · Basics', 'pawfectly-reservations' ),
			'when'         => __( 'Event · When & where', 'pawfectly-reservations' ),
			'price'        => __( 'Event · Price & tickets', 'pawfectly-reservations' ),
			'description'  => __( 'Event · Description', 'pawfectly-reservations' ),
			'sessions'     => __( 'Event · Sessions (repeater)', 'pawfectly-reservations' ),
			'includes'     => __( 'Event · Ticket includes (repeater)', 'pawfectly-reservations' ),
			'goodies'      => __( 'Event · Extra goodies (repeater)', 'pawfectly-reservations' ),
			'good_to_know' => __( 'Event · Good to know (repeater)', 'pawfectly-reservations' ),
			'closing'      => __( 'Event · Closing call to action', 'pawfectly-reservations' ),
			'links'        => __( 'Event · Calendar & links', 'pawfectly-reservations' ),
			'any'          => __( 'Event · Any section (pick one)', 'pawfectly-reservations' ),
		);
	}

	/** The Field choices of a repeater's row tag. */
	public static function row_fields( string $section ): array {
		$labels = array(
			'from'       => __( 'From', 'pawfectly-reservations' ),
			'to'         => __( 'To', 'pawfectly-reservations' ),
			'time'       => __( 'Time range', 'pawfectly-reservations' ),
			'name'       => __( 'Name', 'pawfectly-reservations' ),
			'who'        => __( "Who it's for", 'pawfectly-reservations' ),
			'spots_left' => __( 'Spots left', 'pawfectly-reservations' ),
			'emoji'      => __( 'Emoji', 'pawfectly-reservations' ),
			'title'      => __( 'Title', 'pawfectly-reservations' ),
			'text'       => __( 'Text', 'pawfectly-reservations' ),
		);
		return array_intersect_key( $labels, array_flip( EventFields::ROW_FIELDS[ $section ] ?? array() ) );
	}

	/** What a row tag says in the editor when the event has no such row. */
	public static function missing_row( string $section, int $row ): string {
		$names = array(
			/* translators: %d: row number */
			'sessions'     => __( 'No session %d for this event.', 'pawfectly-reservations' ),
			/* translators: %d: row number */
			'includes'     => __( 'No ticket include %d for this event.', 'pawfectly-reservations' ),
			/* translators: %d: row number */
			'goodies'      => __( 'No goodie %d for this event.', 'pawfectly-reservations' ),
			/* translators: %d: row number */
			'good_to_know' => __( 'No good-to-know item %d for this event.', 'pawfectly-reservations' ),
		);
		return sprintf( $names[ $section ] ?? '', $row );
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
			'section_heading' => __( 'Section heading (pick the section)', 'pawfectly-reservations' ),
			'section_intro'   => __( 'Section intro (pick the section)', 'pawfectly-reservations' ),
			'section_rows'    => __( 'Section rows (pick the section)', 'pawfectly-reservations' ),
			'part_heading'    => __( 'Heading', 'pawfectly-reservations' ),
			'part_intro'      => __( 'Intro', 'pawfectly-reservations' ),
			'part_rows'       => __( 'All rows (list)', 'pawfectly-reservations' ),
			'part_row'        => __( 'Row field (pick row and field)', 'pawfectly-reservations' ),
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
