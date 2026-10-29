<?php
/**
 * [pawfectly_event part="…" id="…"]: one part of an event for page builders (an Elementor Single
 * template, say): the description, a section's rows as a plain list, the sign-up form or the Add
 * to calendar menu. Single values come from the Dynamic Tags (ElementorTags, EventFields).
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class EventShortcode {

	public const TAG   = 'pawfectly_event';
	public const PARTS = array( 'description', 'sessions', 'includes', 'goodies', 'good_to_know', 'signup', 'addcal' );

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/** @param array|string $atts Shortcode attributes; WordPress passes '' when there are none. */
	public static function render( $atts ): string {
		$atts = shortcode_atts( array( 'part' => '', 'id' => '0' ), (array) $atts, self::TAG );
		$part = sanitize_key( (string) $atts['part'] );
		$ev   = in_array( $part, self::PARTS, true ) ? EventFields::current( absint( $atts['id'] ) ) : null;
		if ( ! $ev ) {
			return '';
		}
		return self::part( $ev, $part );
	}

	/**
	 * One part of an event, for the shortcode and the Section rows / Description (formatted)
	 * Dynamic Tags. Empty parts print nothing, except a hint in the Elementor editor.
	 */
	public static function part( array $ev, string $part ): string {
		if ( ! is_singular( Events::TYPE ) ) {
			wp_enqueue_style( 'paw-calendar', PAW_URL . 'assets/css/calendar.css', array(), PAW_VERSION ); // The plain-list reset (EventPage::enqueue covers event pages).
		}
		$paw_ev    = $ev;
		$paw_plain = true;
		ob_start();
		switch ( $part ) {
			case 'description':
				$html = trim( wp_kses( (string) $ev['description'], Settings::WAIVER_TAGS + array( 'a' => array( 'href' => true ) ) ) );
				if ( '' !== $html ) {
					echo '<div class="paw-ev-description">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered by wp_kses.
				}
				break;
			case 'signup':
			case 'addcal':
				$paw_past = 'past' === Events::status( $ev );
				if ( 'signup' === $part && ! $paw_past && ( '' !== $ev['ticket_url'] || 'publish' !== $ev['status'] ) ) {
					break; // Tickets are sold elsewhere, or a draft: nothing to sign up for here.
				}
				self::assets();
				echo '<div class="paw-event paw-event--part"' . ( 'signup' === $part ? ' id="paw-event-reserve"' : '' ) . ' style="--paw-ev:' . esc_attr( $ev['color'] ) . '">';
				include PAW_DIR . 'templates/partials/event-' . ( 'signup' === $part ? 'closing' : 'addcal' ) . '.php';
				echo '</div>';
				break;
			default:
				$paw_section = $part;
				include PAW_DIR . 'templates/partials/event-rows.php';
		}
		$html = trim( (string) ob_get_clean() );
		if ( '' === $html && self::in_editor() && isset( self::empty_hints()[ $part ] ) ) {
			$html = '<p class="paw-ev-empty">' . esc_html( self::empty_hints()[ $part ] ) . '</p>';
		}
		return $html;
	}

	/** What an empty part says in the Elementor editor, so "empty on purpose" isn't mistaken for broken. */
	private static function empty_hints(): array {
		return array(
			'description'  => __( 'No description for this event.', 'pawfectly-reservations' ),
			'sessions'     => __( 'No sessions for this event.', 'pawfectly-reservations' ),
			'includes'     => __( 'No ticket includes for this event.', 'pawfectly-reservations' ),
			'goodies'      => __( 'No extra goodies for this event.', 'pawfectly-reservations' ),
			'good_to_know' => __( 'No good-to-know items for this event.', 'pawfectly-reservations' ),
			'signup'       => __( 'No sign-up form here: this event sells tickets elsewhere, or is a draft.', 'pawfectly-reservations' ),
		);
	}

	/** True while Elementor draws its editor or preview. */
	private static function in_editor(): bool {
		$el = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		if ( ! $el ) {
			return false;
		}
		return ( isset( $el->editor ) && $el->editor->is_edit_mode() ) || ( isset( $el->preview ) && $el->preview->is_preview_mode() );
	}

	/** Off an event page the form still needs its CSS and script (EventPage::enqueue covers event pages). */
	private static function assets(): void {
		if ( ! is_singular( Events::TYPE ) ) {
			EventPage::assets();
		}
	}
}
