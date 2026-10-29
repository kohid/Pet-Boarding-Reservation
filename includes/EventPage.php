<?php
/**
 * The page at /event/<slug>/: the body (through the_content, so the theme's header and footer
 * stay), JSON-LD and share tags in <head>, the .ics download, and draft previews for staff.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class EventPage {

	private const CURRENCIES = array(
		'$' => 'USD',
		'£' => 'GBP',
		'€' => 'EUR',
	);

	public static function init(): void {
		add_filter( 'the_content', array( self::class, 'content' ), 20 );
		add_action( 'wp_head', array( self::class, 'head' ) );
		add_action( 'template_redirect', array( self::class, 'ics' ) );
		add_action( 'pre_get_posts', array( self::class, 'allow_preview' ) );
		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				if ( is_singular( Events::TYPE ) ) {
					wp_enqueue_style( 'paw-calendar', PAW_URL . 'assets/css/calendar.css', array(), PAW_VERSION );
				}
			}
		);
	}

	/**
	 * the_content: an event's own page gets the event body. Matched on the queried post, not
	 * in_the_loop(): block themes before WordPress 6.4 render the content outside the loop.
	 */
	public static function content( $content ) {
		static $busy = false;
		if ( $busy || ! is_singular( Events::TYPE ) || ! is_main_query() || (int) get_the_ID() !== (int) get_queried_object_id() ) {
			return $content;
		}
		$ev = Events::get( (int) get_the_ID() );
		if ( ! $ev ) {
			return $content;
		}
		$busy = true; // The body never runs the_content again, but a theme hook might.
		$out  = self::body( $ev );
		$busy = false;
		return $out;
	}

	/** The page body, in the spec's order. */
	public static function body( array $ev, ?\DateTimeImmutable $now = null ): string {
		$paw_ev   = $ev;
		$paw_past = 'past' === Events::status( $ev, $now );
		ob_start();
		include PAW_DIR . 'templates/event.php';
		return (string) ob_get_clean();
	}

	/** schema.org Event, for search engines. */
	public static function json_ld( array $ev ): array {
		$ld = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => $ev['title'],
			'startDate'           => EventDates::at( $ev['start'] )->format( 'c' ),
			'endDate'             => EventDates::at( $ev['end'] )->format( 'c' ),
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'location'            => array(
				'@type'   => 'Place',
				'name'    => $ev['venue'],
				'address' => '' !== $ev['address'] ? $ev['address'] : $ev['venue'],
			),
			'description'         => '' !== $ev['tagline'] ? $ev['tagline'] : wp_strip_all_tags( (string) $ev['description'] ),
			'url'                 => $ev['url'],
		);
		if ( '' !== $ev['photo_url'] ) {
			$ld['image'] = array( $ev['photo_url'] );
		}
		if ( $ev['free'] || '' !== $ev['price'] ) {
			$currency     = self::CURRENCIES[ (string) ( Settings::get()['currency'] ?? '$' ) ] ?? '';
			$ld['offers'] = array_filter(
				array(
					'@type'         => 'Offer',
					'price'         => $ev['free'] ? '0' : $ev['price'],
					'priceCurrency' => $currency,
					'url'           => '' !== $ev['ticket_url'] ? $ev['ticket_url'] : $ev['url'],
					'availability'  => 'https://schema.org/InStock',
				),
				'strlen'
			);
		}
		return $ld;
	}

	/** wp_head: JSON-LD, and share tags unless an SEO plugin prints its own. */
	public static function head(): void {
		if ( ! is_singular( Events::TYPE ) ) {
			return;
		}
		$ev = Events::get( (int) get_queried_object_id() );
		if ( ! $ev ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( self::json_ld( $ev ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' ) ) {
			return;
		}
		$tags = array(
			'og:type'        => 'website',
			'og:title'       => trim( $ev['emoji'] . ' ' . $ev['title'] ),
			'og:description' => EventDates::summary( $ev ) . ( '' !== $ev['tagline'] ? ' · ' . $ev['tagline'] : '' ),
			'og:url'         => $ev['url'],
			'og:image'       => $ev['photo_url'],
		);
		foreach ( array_filter( $tags, 'strlen' ) as $property => $value ) {
			printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $value ) );
		}
	}

	/** template_redirect: ?paw_ics=1 on an event page downloads it as an .ics file. */
	public static function ics(): void {
		if ( ! isset( $_GET['paw_ics'] ) || ! is_singular( Events::TYPE ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- a public download.
			return;
		}
		$ev = Events::get( (int) get_queried_object_id() );
		if ( ! $ev || 'publish' !== $ev['status'] ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( sanitize_title( $ev['title'] ) . '.ics' ) . '"' );
		echo EventDates::ics( $ev ); // phpcs:ignore WordPress.Security.EscapeOutput -- iCalendar text, escaped for its format.
		exit;
	}

	/** pre_get_posts: staff may open a draft event with ?paw_preview=1. Everyone else gets a 404. */
	public static function allow_preview( $query ): void {
		if ( ! isset( $_GET['paw_preview'] ) || ! $query->is_main_query() || Events::TYPE !== $query->get( 'post_type' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only, capability-checked.
			return;
		}
		if ( current_user_can( Activator::CAPABILITY ) ) {
			$query->set( 'post_status', array( 'publish', 'draft' ) );
		}
	}

	public static function preview_url( int $id ): string {
		return add_query_arg(
			array(
				'post_type'   => Events::TYPE,
				'p'           => $id,
				'paw_preview' => 1,
			),
			home_url( '/' )
		);
	}
}
