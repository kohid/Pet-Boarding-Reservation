<?php
/**
 * [pawfectly_events]: event cards with Upcoming / Happening now / Past / All tabs and
 * Load more (nine at a time, through a public route).
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class EventsList {

	public const TAG      = 'pawfectly_events';
	public const PER_PAGE = 9;
	private const TABS    = array( 'upcoming', 'current', 'past', 'all' );

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/events/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'route' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @param array|string $atts category="slug", limit="9", show="upcoming|past|all". */
	public static function render( $atts = array() ): string {
		$atts  = is_array( $atts ) ? $atts : array();
		$cat   = sanitize_key( (string) ( $atts['category'] ?? '' ) );
		$asked = isset( $_GET['paw_events'] ) ? sanitize_key( wp_unslash( $_GET['paw_events'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
		$tab   = in_array( $asked, self::TABS, true ) ? $asked : ( in_array( $atts['show'] ?? '', self::TABS, true ) ? $atts['show'] : 'upcoming' );
		$limit = max( 1, min( 48, absint( $atts['limit'] ?? self::PER_PAGE ) ) );
		$cards = self::cards( $tab, $cat, 0, $limit );
		Calendar::assets();

		$labels = array(
			'upcoming' => __( 'Upcoming', 'pawfectly-reservations' ),
			'current'  => __( 'Happening now', 'pawfectly-reservations' ),
			'past'     => __( 'Past', 'pawfectly-reservations' ),
			'all'      => __( 'All', 'pawfectly-reservations' ),
		);
		ob_start();
		?>
<div class="paw-events" data-tab="<?php echo esc_attr( $tab ); ?>" data-cat="<?php echo esc_attr( $cat ); ?>" data-limit="<?php echo esc_attr( (string) $limit ); ?>" data-rest="<?php echo esc_url( rest_url( Rest::NS . '/events/list' ) ); ?>">
	<nav class="paw-events__tabs" aria-label="<?php esc_attr_e( 'Events', 'pawfectly-reservations' ); ?>">
		<?php foreach ( $labels as $paw_key => $paw_label ) : ?>
			<a class="paw-events__tab" href="<?php echo esc_url( add_query_arg( 'paw_events', $paw_key ) ); ?>"<?php echo $tab === $paw_key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $paw_label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<div class="paw-events__grid" data-paw-events-grid><?php echo $cards['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the card template. ?></div>
	<div class="paw-events__more">
		<button type="button" class="paw-events__more-btn" data-paw-events-more<?php echo $cards['more'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Load more', 'pawfectly-reservations' ); ?></button>
	</div>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/** GET /events/list?tab=&cat=&offset=: the next cards. Public, read-only. */
	public static function route( \WP_REST_Request $req ): \WP_REST_Response {
		$tab = sanitize_key( (string) $req->get_param( 'tab' ) );
		return new \WP_REST_Response(
			self::cards(
				in_array( $tab, self::TABS, true ) ? $tab : 'upcoming',
				sanitize_key( (string) $req->get_param( 'cat' ) ),
				absint( $req->get_param( 'offset' ) ),
				max( 1, min( 48, absint( $req->get_param( 'limit' ) ?? self::PER_PAGE ) ?: self::PER_PAGE ) )
			)
		);
	}

	/** @return array{html:string, more:bool} */
	public static function cards( string $tab, string $cat, int $offset, int $limit = self::PER_PAGE ): array {
		$events = self::events( $tab, $cat );
		$page   = array_slice( $events, $offset, $limit );
		if ( ! $page ) {
			return array(
				'html' => 0 === $offset ? '<p class="paw-events__empty"><span aria-hidden="true">🐾</span> ' . esc_html__( 'No events here yet.', 'pawfectly-reservations' ) . '</p>' : '',
				'more' => false,
			);
		}
		ob_start();
		foreach ( $page as $paw_ev ) {
			include PAW_DIR . 'templates/partials/event-card.php';
		}
		return array(
			'html' => (string) ob_get_clean(),
			'more' => count( $events ) > $offset + $limit,
		);
	}

	/** A tab's events: upcoming and current soonest first, past most recent first. */
	private static function events( string $tab, string $cat ): array {
		$now   = new \DateTimeImmutable( 'now', wp_timezone() );
		$today = $now->format( 'Y-m-d' );
		// Only the dates the tab can show, so the list never loads every event ever held.
		$range = array(
			'upcoming' => array( $today, '9999-12-31' ),
			'current'  => array( $today, $today ),
			'past'     => array( '1970-01-01', $today ),
		)[ $tab ] ?? array( '1970-01-01', '9999-12-31' );
		$all = Events::between( $range[0], $range[1], '', $cat );
		if ( 'all' === $tab ) {
			return $all;
		}
		$list = array_values( array_filter( $all, static fn( array $ev ): bool => Events::status( $ev, $now ) === $tab ) );
		return 'past' === $tab ? array_reverse( $list ) : $list;
	}
}
