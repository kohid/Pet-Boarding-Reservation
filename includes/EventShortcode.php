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
		return trim( (string) ob_get_clean() );
	}

	/** Off an event page the form still needs its CSS and script (EventPage::enqueue covers event pages). */
	private static function assets(): void {
		if ( ! is_singular( Events::TYPE ) ) {
			EventPage::assets();
		}
	}
}
