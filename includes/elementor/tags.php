<?php
/**
 * The Dynamic Tag classes (see ElementorTags). Elementor re-creates a tag from its class name, so
 * each tag is a small named class over a few bases: Text_Tag and Link_Tag (one field each),
 * Section_Part_Tag (a section's heading, intro or all its rows), Row_Tag (one field of one
 * repeater row), and the image and colour tags.
 * Loaded only from ElementorTags::register(), once Elementor's base classes exist.
 *
 * @package Pawfectly
 */

namespace Pawfectly\Elementor;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use Pawfectly\ElementorTags;
use Pawfectly\EventFields;
use Pawfectly\EventShortcode;

defined( 'ABSPATH' ) || exit;

/** A text field of the event being shown, with its controls (format, section, session, text). */
abstract class Text_Tag extends Tag {
	protected const FIELD = '';

	public function get_name() {
		return 'paw-event-' . str_replace( '_', '-', static::FIELD );
	}

	public function get_title() {
		return ElementorTags::title( static::FIELD );
	}

	public function get_group() {
		return ElementorTags::group( ElementorTags::field_group( static::FIELD ) );
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$field = static::FIELD;
		if ( in_array( $field, array( 'start_date', 'end_date', 'start_time', 'end_time' ), true ) ) {
			$this->add_control(
				'format',
				array(
					'label'       => __( 'Format', 'pawfectly-reservations' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => str_ends_with( $field, '_date' ) ? EventFields::DATE_FORMAT : EventFields::TIME_FORMAT,
					'description' => __( 'A PHP date format, like "F j, Y" or "g:i A".', 'pawfectly-reservations' ),
				)
			);
		} elseif ( in_array( $field, array( 'section_heading', 'section_intro' ), true ) ) {
			$this->add_control(
				'section',
				array(
					'label'   => __( 'Section', 'pawfectly-reservations' ),
					'type'    => Controls_Manager::SELECT,
					'options' => ElementorTags::sections(),
					'default' => 'sessions',
				)
			);
		} elseif ( 'spots_left' === $field ) {
			$options = array( '0' => __( 'Event without sessions', 'pawfectly-reservations' ) );
			for ( $n = 1; $n <= 8; $n++ ) {
				/* translators: %d: session number */
				$options[ (string) $n ] = sprintf( __( 'Session %d', 'pawfectly-reservations' ), $n );
			}
			$this->add_control(
				'session',
				array(
					'label'   => __( 'Session', 'pawfectly-reservations' ),
					'type'    => Controls_Manager::SELECT,
					'options' => $options,
					'default' => '1',
				)
			);
		} elseif ( 'section_rows' === $field ) {
			$this->add_control(
				'section',
				array(
					'label'   => __( 'Section', 'pawfectly-reservations' ),
					'type'    => Controls_Manager::SELECT,
					'options' => array_diff_key( ElementorTags::sections(), array( 'closing' => true ) ),
					'default' => 'sessions',
				)
			);
		} elseif ( 'free' === $field ) {
			$this->add_control(
				'text',
				array(
					'label'   => __( 'Text', 'pawfectly-reservations' ),
					'type'    => Controls_Manager::TEXT,
					'default' => __( 'Free', 'pawfectly-reservations' ),
				)
			);
		}
	}

	public function render() {
		$ev = EventFields::current();
		if ( $ev ) {
			echo esc_html( EventFields::value( $ev, static::FIELD, (array) $this->get_settings() ) );
		}
	}
}

/** A link of the event being shown. */
abstract class Link_Tag extends Data_Tag {
	protected const FIELD = '';

	public function get_name() {
		return 'paw-event-' . str_replace( '_', '-', static::FIELD );
	}

	public function get_title() {
		return ElementorTags::title( static::FIELD );
	}

	public function get_group() {
		return ElementorTags::group( ElementorTags::field_group( static::FIELD ) );
	}

	public function get_categories() {
		return array( Module::URL_CATEGORY );
	}

	public function get_value( array $options = array() ) {
		$ev = EventFields::current();
		return $ev ? EventFields::link( $ev, static::FIELD ) : '';
	}
}

/** The cover photo. */
final class Photo extends Data_Tag {
	public function get_name() {
		return 'paw-event-photo';
	}

	public function get_title() {
		return ElementorTags::title( 'photo' );
	}

	public function get_group() {
		return ElementorTags::group( 'basics' );
	}

	public function get_categories() {
		return array( Module::IMAGE_CATEGORY );
	}

	/** Image tags get no Fallback from Elementor (only text tags do), so this one has its own. */
	protected function register_controls() {
		$this->add_control(
			'fallback',
			array(
				'label' => __( 'Fallback', 'pawfectly-reservations' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);
	}

	public function get_value( array $options = array() ) {
		$ev    = EventFields::current();
		$image = $ev ? EventFields::image( $ev ) : array();
		if ( $image ) {
			return $image;
		}
		$fallback = $this->get_settings( 'fallback' );
		return is_array( $fallback ) && ! empty( $fallback['url'] ) ? $fallback : array();
	}
}

/** The category colour. */
final class Color extends Data_Tag {
	public function get_name() {
		return 'paw-event-color';
	}

	public function get_title() {
		return ElementorTags::title( 'color' );
	}

	public function get_group() {
		return ElementorTags::group( 'basics' );
	}

	public function get_categories() {
		return array( Module::COLOR_CATEGORY );
	}

	public function get_value( array $options = array() ) {
		$ev = EventFields::current();
		return $ev ? EventFields::color( $ev ) : '';
	}
}
/** A section's rows (the repeater) as a plain list. HTML: use it in a Text Editor widget. */
final class Section_Rows extends Text_Tag {
	protected const FIELD = 'section_rows';

	public function render() {
		$ev      = EventFields::current();
		$section = (string) $this->get_settings( 'section' );
		if ( $ev && in_array( $section, array( 'sessions', 'includes', 'goodies', 'good_to_know' ), true ) ) { // Rows only: a saved value can't draw another part.
			echo EventShortcode::part( $ev, $section ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the rows partial.
		}
	}
}

/** The description with its paragraphs, bold, lists and links. */
final class Description_Html extends Text_Tag {
	protected const FIELD = 'description_html';

	public function render() {
		$ev = EventFields::current();
		if ( $ev ) {
			echo EventShortcode::part( $ev, 'description' ); // phpcs:ignore WordPress.Security.EscapeOutput -- filtered by wp_kses.
		}
	}
}

/** One section's heading, intro or all its rows, in that section's group. */
abstract class Section_Part_Tag extends Tag {
	protected const SECTION = '';
	protected const PART    = ''; // heading, intro or rows.

	public function get_name() {
		return 'paw-event-' . str_replace( '_', '-', static::SECTION ) . '-' . static::PART;
	}

	public function get_title() {
		return ElementorTags::title( 'part_' . static::PART );
	}

	public function get_group() {
		return ElementorTags::group( static::SECTION );
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	public function render() {
		$ev = EventFields::current();
		if ( ! $ev ) {
			return;
		}
		if ( 'rows' === static::PART ) {
			echo EventShortcode::part( $ev, static::SECTION ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the rows partial.
			return;
		}
		echo esc_html( EventFields::value( $ev, 'section_' . static::PART, array( 'section' => static::SECTION ) ) );
	}
}

/** One field of one repeater row: pick the row number and the field. */
abstract class Row_Tag extends Tag {
	protected const SECTION = '';

	public function get_name() {
		return 'paw-event-' . str_replace( '_', '-', static::SECTION ) . '-row';
	}

	public function get_title() {
		return ElementorTags::title( 'part_row' );
	}

	public function get_group() {
		return ElementorTags::group( static::SECTION );
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$rows = array();
		for ( $n = 1; $n <= \Pawfectly\Events::LIMITS[ static::SECTION ]; $n++ ) {
			$rows[ (string) $n ] = (string) $n;
		}
		$fields = ElementorTags::row_fields( static::SECTION );
		$this->add_control(
			'row',
			array(
				'label'   => __( 'Row', 'pawfectly-reservations' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $rows,
				'default' => '1',
			)
		);
		$this->add_control(
			'field',
			array(
				'label'   => __( 'Field', 'pawfectly-reservations' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $fields,
				'default' => (string) array_key_first( $fields ),
			)
		);
		if ( 'sessions' === static::SECTION ) {
			$this->add_control(
				'format',
				array(
					'label'       => __( 'Time format', 'pawfectly-reservations' ),
					'type'        => Controls_Manager::TEXT,
					'default'     => EventFields::TIME_FORMAT,
					'description' => __( 'For From and To, like "g:i A" or "H:i".', 'pawfectly-reservations' ),
					'condition'   => array( 'field' => array( 'from', 'to' ) ),
				)
			);
		}
	}

	public function render() {
		$ev = EventFields::current();
		if ( ! $ev ) {
			return;
		}
		$row   = max( 1, (int) $this->get_settings( 'row' ) );
		$value = EventFields::row_value( $ev, static::SECTION, $row, (string) $this->get_settings( 'field' ), array( 'format' => (string) $this->get_settings( 'format' ) ) );
		if ( '' === $value && EventShortcode::in_editor() && ! isset( $ev['sections'][ static::SECTION ]['rows'][ $row - 1 ] ) ) {
			$value = ElementorTags::missing_row( static::SECTION, $row ); // Editor only, like the empty-list hints.
		}
		echo esc_html( $value );
	}
}

final class Sessions_Heading extends Section_Part_Tag { protected const SECTION = 'sessions'; protected const PART = 'heading'; }
final class Sessions_Intro extends Section_Part_Tag { protected const SECTION = 'sessions'; protected const PART = 'intro'; }
final class Sessions_Rows extends Section_Part_Tag { protected const SECTION = 'sessions'; protected const PART = 'rows'; }
final class Sessions_Row extends Row_Tag { protected const SECTION = 'sessions'; }
final class Includes_Heading extends Section_Part_Tag { protected const SECTION = 'includes'; protected const PART = 'heading'; }
final class Includes_Intro extends Section_Part_Tag { protected const SECTION = 'includes'; protected const PART = 'intro'; }
final class Includes_Rows extends Section_Part_Tag { protected const SECTION = 'includes'; protected const PART = 'rows'; }
final class Includes_Row extends Row_Tag { protected const SECTION = 'includes'; }
final class Goodies_Heading extends Section_Part_Tag { protected const SECTION = 'goodies'; protected const PART = 'heading'; }
final class Goodies_Rows extends Section_Part_Tag { protected const SECTION = 'goodies'; protected const PART = 'rows'; }
final class Goodies_Row extends Row_Tag { protected const SECTION = 'goodies'; }
final class Know_Heading extends Section_Part_Tag { protected const SECTION = 'good_to_know'; protected const PART = 'heading'; }
final class Know_Rows extends Section_Part_Tag { protected const SECTION = 'good_to_know'; protected const PART = 'rows'; }
final class Know_Row extends Row_Tag { protected const SECTION = 'good_to_know'; }
final class Closing_Heading extends Section_Part_Tag { protected const SECTION = 'closing'; protected const PART = 'heading'; }
final class Closing_Intro extends Section_Part_Tag { protected const SECTION = 'closing'; protected const PART = 'intro'; }

final class Title extends Text_Tag { protected const FIELD = 'title'; }
final class Tagline extends Text_Tag { protected const FIELD = 'tagline'; }
final class Emoji extends Text_Tag { protected const FIELD = 'emoji'; }
final class Category extends Text_Tag { protected const FIELD = 'category'; }
final class StartDate extends Text_Tag { protected const FIELD = 'start_date'; }
final class StartTime extends Text_Tag { protected const FIELD = 'start_time'; }
final class EndDate extends Text_Tag { protected const FIELD = 'end_date'; }
final class EndTime extends Text_Tag { protected const FIELD = 'end_time'; }
final class DateLine extends Text_Tag { protected const FIELD = 'date_line'; }
final class Day extends Text_Tag { protected const FIELD = 'day'; }
final class TimeRange extends Text_Tag { protected const FIELD = 'time_range'; }
final class Summary extends Text_Tag { protected const FIELD = 'summary'; }
final class Venue extends Text_Tag { protected const FIELD = 'venue'; }
final class Address extends Text_Tag { protected const FIELD = 'address'; }
final class Where extends Text_Tag { protected const FIELD = 'where'; }
final class Price extends Text_Tag { protected const FIELD = 'price'; }
final class PriceText extends Text_Tag { protected const FIELD = 'price_text'; }
final class Free extends Text_Tag { protected const FIELD = 'free'; }
final class TicketText extends Text_Tag { protected const FIELD = 'ticket_text'; }
final class Note extends Text_Tag { protected const FIELD = 'note'; }
final class Description extends Text_Tag { protected const FIELD = 'description'; }
final class Spots extends Text_Tag { protected const FIELD = 'spots'; }
final class SpotsLeft extends Text_Tag { protected const FIELD = 'spots_left'; }
final class SectionHeading extends Text_Tag { protected const FIELD = 'section_heading'; }
final class SectionIntro extends Text_Tag { protected const FIELD = 'section_intro'; }
final class Tickets_Link extends Link_Tag { protected const FIELD = 'tickets'; }
final class TicketUrl_Link extends Link_Tag { protected const FIELD = 'ticket_url'; }
final class Url_Link extends Link_Tag { protected const FIELD = 'url'; }
final class Google_Link extends Link_Tag { protected const FIELD = 'google'; }
final class Outlook_Link extends Link_Tag { protected const FIELD = 'outlook'; }
final class Ics_Link extends Link_Tag { protected const FIELD = 'ics'; }
final class Map_Link extends Link_Tag { protected const FIELD = 'map'; }

/** Every tag. Elementor lists them by group (ElementorTags::GROUPS), in this order within each. */
const TAGS = array(
	// Event · Basics.
	Emoji::class,
	Title::class,
	Tagline::class,
	Category::class,
	Color::class,
	Photo::class,
	// Event · When & where.
	StartDate::class,
	StartTime::class,
	EndDate::class,
	EndTime::class,
	DateLine::class,
	Day::class,
	TimeRange::class,
	Summary::class,
	Venue::class,
	Address::class,
	Where::class,
	Map_Link::class,
	// Event · Price & tickets.
	Price::class,
	PriceText::class,
	Free::class,
	Spots::class,
	SpotsLeft::class,
	TicketText::class,
	TicketUrl_Link::class,
	Tickets_Link::class,
	Note::class,
	// Event · Description.
	Description::class,
	Description_Html::class,
	// The repeaters and the closing section.
	Sessions_Heading::class,
	Sessions_Intro::class,
	Sessions_Rows::class,
	Sessions_Row::class,
	Includes_Heading::class,
	Includes_Intro::class,
	Includes_Rows::class,
	Includes_Row::class,
	Goodies_Heading::class,
	Goodies_Rows::class,
	Goodies_Row::class,
	Know_Heading::class,
	Know_Rows::class,
	Know_Row::class,
	Closing_Heading::class,
	Closing_Intro::class,
	// Event · Calendar & links.
	Url_Link::class,
	Google_Link::class,
	Outlook_Link::class,
	Ics_Link::class,
	// Event · Any section (pick one): the first tags, kept so templates made with them still work.
	SectionHeading::class,
	SectionIntro::class,
	Section_Rows::class,
);
