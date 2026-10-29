<?php
/**
 * The Dynamic Tag classes (see ElementorTags). Elementor re-creates a tag from its class name, so
 * each tag is a small named class over three bases: Text_Tag, Link_Tag and the image and colour tags.
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
		return ElementorTags::GROUP;
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
		return ElementorTags::GROUP;
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
		return ElementorTags::GROUP;
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
		return ElementorTags::GROUP;
	}

	public function get_categories() {
		return array( Module::COLOR_CATEGORY );
	}

	public function get_value( array $options = array() ) {
		$ev = EventFields::current();
		return $ev ? EventFields::color( $ev ) : '';
	}
}
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

/** Every tag, in the order Elementor lists them. */
const TAGS = array(
	Title::class,
	Tagline::class,
	Emoji::class,
	Category::class,
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
	Price::class,
	PriceText::class,
	Free::class,
	TicketText::class,
	Note::class,
	Description::class,
	Spots::class,
	SpotsLeft::class,
	SectionHeading::class,
	SectionIntro::class,
	Tickets_Link::class,
	TicketUrl_Link::class,
	Url_Link::class,
	Google_Link::class,
	Outlook_Link::class,
	Ics_Link::class,
	Map_Link::class,
	Photo::class,
	Color::class,
);
