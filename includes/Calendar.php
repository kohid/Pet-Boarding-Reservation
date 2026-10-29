<?php
/**
 * [pawfectly_calendar]: the event calendar (Month, List and Day views, search, categories),
 * modelled on the Community Business Directory's. view_data() builds every view for one
 * focus date; the shortcode and the public route both use it, so the markup is identical.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Calendar {

	public const TAG         = 'pawfectly_calendar';
	public const MAX_PER_DAY = 3;
	private const VIEWS      = array( 'month', 'list', 'day' );

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	public static function routes(): void {
		register_rest_route(
			Rest::NS,
			'/events/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'route' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function assets(): void {
		$v = PAW_VERSION;
		wp_enqueue_style( 'paw-calendar', PAW_URL . 'assets/css/calendar.css', array(), $v );
		wp_enqueue_script( 'paw-calendar', PAW_URL . 'assets/js/calendar.js', array(), $v, true );
	}

	/** @param array|string $atts category="slug", view="month|list|day". */
	public static function render( $atts = array() ): string {
		$atts = is_array( $atts ) ? $atts : array();
		$cat  = sanitize_key( (string) ( $atts['category'] ?? '' ) );
		$view = in_array( $atts['view'] ?? '', self::VIEWS, true ) ? $atts['view'] : 'month';

		$today = self::today();
		$focus = $today;
		$asked = isset( $_GET['paw_cal'] ) ? sanitize_text_field( wp_unslash( $_GET['paw_cal'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
		if ( preg_match( '/^(\d{4})-(\d{1,2})$/', $asked, $m ) && (int) $m[2] >= 1 && (int) $m[2] <= 12 ) {
			$first = sprintf( '%04d-%02d-01', $m[1], $m[2] );
			$focus = substr( $today, 0, 7 ) === substr( $first, 0, 7 ) ? $today : $first;
		}
		$data = self::view_data( $focus, '', $cat );
		self::assets();

		$i18n = array(
			'viewEvent' => __( 'View event', 'pawfectly-reservations' ),
			'addToCal'  => __( 'Add to calendar', 'pawfectly-reservations' ),
			'google'    => __( 'Google Calendar', 'pawfectly-reservations' ),
			'outlook'   => __( 'Outlook', 'pawfectly-reservations' ),
			'ics'       => __( 'Apple / iCal (.ics)', 'pawfectly-reservations' ),
			'months'    => array_map( static fn( int $n ): string => gmdate( 'F', gmmktime( 0, 0, 0, $n, 1, 2026 ) ), range( 1, 12 ) ),
			'prevMonth' => __( 'Previous month', 'pawfectly-reservations' ),
			'nextMonth' => __( 'Next month', 'pawfectly-reservations' ),
			'failed'    => __( "We couldn't load the calendar. Please try again.", 'pawfectly-reservations' ),
		);
		$views = array(
			'list'  => __( 'List', 'pawfectly-reservations' ),
			'month' => __( 'Month', 'pawfectly-reservations' ),
			'day'   => __( 'Day', 'pawfectly-reservations' ),
		);
		ob_start();
		?>
<div class="paw-cal" data-date="<?php echo esc_attr( $data['date'] ); ?>" data-ym="<?php echo esc_attr( $data['ym'] ); ?>" data-month-title="<?php echo esc_attr( $data['monthTitle'] ); ?>" data-day-title="<?php echo esc_attr( $data['dayTitle'] ); ?>" data-cat="<?php echo esc_attr( $cat ); ?>" data-view="<?php echo esc_attr( $view ); ?>" data-today="<?php echo esc_attr( $today ); ?>" data-rest="<?php echo esc_url( rest_url( Rest::NS . '/events/calendar' ) ); ?>" data-i18n="<?php echo esc_attr( (string) wp_json_encode( $i18n ) ); ?>">
	<div class="paw-cal__toolbar">
		<form class="paw-cal__search" role="search" data-paw-cal-search>
			<label class="screen-reader-text" for="paw-cal-q-<?php echo esc_attr( $data['ym'] ); ?>"><?php esc_html_e( 'Search for events', 'pawfectly-reservations' ); ?></label>
			<svg class="paw-cal__search-ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 4a6 6 0 1 0 3.7 10.7l4.3 4.3 1.4-1.4-4.3-4.3A6 6 0 0 0 10 4zm0 2a4 4 0 1 1 0 8 4 4 0 0 1 0-8z"/></svg>
			<input type="search" id="paw-cal-q-<?php echo esc_attr( $data['ym'] ); ?>" class="paw-cal__q" data-paw-cal-q placeholder="<?php esc_attr_e( 'Search for events', 'pawfectly-reservations' ); ?>" autocomplete="off">
			<button type="submit" class="paw-cal__btn paw-cal__btn--primary"><?php esc_html_e( 'Find Events', 'pawfectly-reservations' ); ?></button>
		</form>
		<?php $paw_cats = Events::categories(); ?>
		<?php if ( $paw_cats ) : ?>
			<label class="paw-cal__cat">
				<span class="screen-reader-text"><?php esc_html_e( 'Category', 'pawfectly-reservations' ); ?></span>
				<select data-paw-cal-cat>
					<option value=""><?php esc_html_e( 'All events', 'pawfectly-reservations' ); ?></option>
					<?php foreach ( $paw_cats as $paw_c ) : ?>
						<option value="<?php echo esc_attr( $paw_c['slug'] ); ?>" data-color="<?php echo esc_attr( $paw_c['color'] ); ?>"<?php selected( $cat, $paw_c['slug'] ); ?>><?php echo esc_html( $paw_c['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>
		<div class="paw-cal__views" role="group" aria-label="<?php esc_attr_e( 'Calendar view', 'pawfectly-reservations' ); ?>">
			<?php foreach ( $views as $paw_v => $paw_label ) : ?>
				<button type="button" class="paw-cal__view<?php echo $view === $paw_v ? ' is-active' : ''; ?>" data-paw-cal-view="<?php echo esc_attr( $paw_v ); ?>" aria-pressed="<?php echo $view === $paw_v ? 'true' : 'false'; ?>"><?php echo esc_html( $paw_label ); ?></button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="paw-cal__nav">
		<div class="paw-cal__arrows">
			<button type="button" class="paw-cal__arrow" data-paw-cal-prev aria-label="<?php esc_attr_e( 'Previous', 'pawfectly-reservations' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4L10.8 12z"/></svg></button>
			<button type="button" class="paw-cal__arrow" data-paw-cal-next aria-label="<?php esc_attr_e( 'Next', 'pawfectly-reservations' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z"/></svg></button>
		</div>
		<button type="button" class="paw-cal__btn" data-paw-cal-today><?php esc_html_e( 'Today', 'pawfectly-reservations' ); ?></button>
		<div class="paw-cal__titlewrap">
			<button type="button" class="paw-cal__title" data-paw-cal-title aria-haspopup="dialog" aria-expanded="false">
				<span data-paw-cal-title-text><?php echo esc_html( 'day' === $view ? $data['dayTitle'] : $data['monthTitle'] ); ?></span>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7.4 8.6 12 13.2l4.6-4.6L18 10l-6 6-6-6z"/></svg>
			</button>
			<div class="paw-cal__picker" data-paw-cal-picker hidden></div>
		</div>
	</div>

	<div class="paw-cal__body" aria-live="polite">
		<div class="paw-cal__month" data-paw-cal-box="month"<?php echo 'month' === $view ? '' : ' hidden'; ?>><?php echo $data['grid']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in grid_html(). ?></div>
		<div class="paw-cal__agenda" data-paw-cal-box="list"<?php echo 'list' === $view ? '' : ' hidden'; ?>><?php echo $data['agenda']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in agenda_html(). ?></div>
		<div class="paw-cal__dayview" data-paw-cal-box="day"<?php echo 'day' === $view ? '' : ' hidden'; ?>><?php echo $data['day']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in day_html(). ?></div>
		<div class="paw-cal__loader" data-paw-cal-loader hidden><span></span></div>
	</div>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/** GET /events/calendar?date=&q=&cat=: every view for that date. Public, read-only. */
	public static function route( \WP_REST_Request $req ): \WP_REST_Response {
		$date = (string) $req->get_param( 'date' );
		$date = self::valid( $date ) ? $date : self::today();
		$q    = mb_substr( sanitize_text_field( (string) $req->get_param( 'q' ) ), 0, 80 );
		$cat  = sanitize_key( (string) $req->get_param( 'cat' ) );
		return new \WP_REST_Response( self::view_data( $date, $q, $cat ) );
	}

	/** @return array{date:string, ym:string, monthTitle:string, dayTitle:string, grid:string, agenda:string, day:string, count:int} */
	public static function view_data( string $date, string $q, string $cat ): array {
		$date  = self::valid( $date ) ? $date : self::today();
		$d     = self::d( $date );
		$first = $d->modify( 'first day of this month' );
		$last  = $d->modify( 'last day of this month' );
		$start = $first->modify( '-' . ( (int) $first->format( 'N' ) - 1 ) . ' days' );
		$end   = $last->modify( '+' . ( 7 - (int) $last->format( 'N' ) ) . ' days' );
		$by    = self::by_day( $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ), $q, $cat );

		return array(
			'date'       => $date,
			'ym'         => $d->format( 'Y-m' ),
			'monthTitle' => $d->format( 'F Y' ),
			'dayTitle'   => $d->format( 'F j, Y' ),
			'grid'       => self::grid_html( $start, $end, $d->format( 'Y-m' ), $by ),
			'agenda'     => self::agenda_html( $d->format( 'Y-m' ), $by, $q ),
			'day'        => self::day_html( $d, $by[ $date ] ?? array() ),
			'count'      => array_sum( array_map( 'count', $by ) ),
		);
	}

	/**
	 * Events in [$from, $to], placed on every date they cover.
	 *
	 * @return array<string, list<array>> 'Y-m-d' => entries for that day.
	 */
	public static function by_day( string $from, string $to, string $q, string $cat ): array {
		$by = array();
		foreach ( Events::between( $from, $to, $q, $cat ) as $ev ) {
			$s     = EventDates::at( $ev['start'] );
			$e     = EventDates::at( $ev['end'] );
			$s_day = $s->format( 'Y-m-d' );
			$e_day = $e->format( 'Y-m-d' );
			$multi = $s_day !== $e_day;
			$card  = array(
				'id'      => (int) $ev['id'],
				'title'   => (string) $ev['title'],
				'url'     => (string) $ev['url'],
				'venue'   => (string) $ev['venue'],
				'emoji'   => (string) $ev['emoji'],
				'thumb'   => (string) $ev['photo_url'],
				'color'   => (string) $ev['color'],
				'range'   => $multi ? $s->format( 'M j, g:i A' ) . ' – ' . $e->format( 'M j, g:i A' ) : EventDates::time_range( $ev ),
				'excerpt' => self::excerpt( $ev ),
				'cal'     => EventDates::links( $ev ),
				'multiday' => $multi,
			);
			$day = self::d( max( $s_day, $from ) );
			$top = min( $e_day, $to );
			for ( ; $day->format( 'Y-m-d' ) <= $top; $day = $day->modify( '+1 day' ) ) {
				$key  = $day->format( 'Y-m-d' );
				$time = $s->format( 'g:i A' );
				if ( $multi ) {
					/* translators: %s: end time */
					$time = $key === $s_day ? $s->format( 'g:i A' ) : ( $key === $e_day ? sprintf( __( 'Until %s', 'pawfectly-reservations' ), $e->format( 'g:i A' ) ) : __( 'All day', 'pawfectly-reservations' ) );
				}
				$by[ $key ][] = $card + array(
					'time'     => $time,
					'is_start' => $key === $s_day,
					'is_end'   => $key === $e_day,
					'sort'     => ( $multi ? '0' : '1' ) . $ev['start'],
				);
			}
		}
		foreach ( $by as &$list ) {
			usort( $list, static fn( array $a, array $b ): int => strcmp( $a['sort'], $b['sort'] ) );
		}
		unset( $list );
		ksort( $by );
		return $by;
	}

	private static function grid_html( \DateTimeImmutable $start, \DateTimeImmutable $end, string $ym, array $by ): string {
		$today = self::today();
		$dows  = array(
			array( __( 'Mon', 'pawfectly-reservations' ), __( 'Monday', 'pawfectly-reservations' ) ),
			array( __( 'Tue', 'pawfectly-reservations' ), __( 'Tuesday', 'pawfectly-reservations' ) ),
			array( __( 'Wed', 'pawfectly-reservations' ), __( 'Wednesday', 'pawfectly-reservations' ) ),
			array( __( 'Thu', 'pawfectly-reservations' ), __( 'Thursday', 'pawfectly-reservations' ) ),
			array( __( 'Fri', 'pawfectly-reservations' ), __( 'Friday', 'pawfectly-reservations' ) ),
			array( __( 'Sat', 'pawfectly-reservations' ), __( 'Saturday', 'pawfectly-reservations' ) ),
			array( __( 'Sun', 'pawfectly-reservations' ), __( 'Sunday', 'pawfectly-reservations' ) ),
		);
		$out = '<div class="paw-cal__grid" role="grid"><div class="paw-cal__week paw-cal__dows" role="row">';
		foreach ( $dows as $dow ) {
			$out .= '<div class="paw-cal__dow" role="columnheader"><abbr title="' . esc_attr( $dow[1] ) . '">' . esc_html( $dow[0] ) . '</abbr></div>';
		}
		$out .= '</div>';
		for ( $day = $start, $col = 0; $day <= $end; $day = $day->modify( '+1 day' ), $col++ ) {
			$key    = $day->format( 'Y-m-d' );
			$events = $by[ $key ] ?? array();
			$class  = ( $day->format( 'Y-m' ) !== $ym ? ' is-other' : '' ) . ( $key === $today ? ' is-today' : '' ) . ( $events ? ' has-events' : '' );
			$out   .= 0 === $col % 7 ? ( $col ? '</div>' : '' ) . '<div class="paw-cal__week" role="row">' : '';
			$out   .= '<div class="paw-cal__cell' . esc_attr( $class ) . '" role="gridcell" data-date="' . esc_attr( $key ) . '">';
			$out   .= '<button type="button" class="paw-cal__daynum" data-paw-cal-goto="' . esc_attr( $key ) . '" aria-label="' . esc_attr( $day->format( 'l, F j, Y' ) ) . '">' . esc_html( $day->format( 'j' ) ) . '</button>';
			if ( $events ) {
				$out .= '<div class="paw-cal__events">';
				foreach ( $events as $n => $ev ) {
					$extra = $n >= self::MAX_PER_DAY;
					$multi = $ev['multiday'] ? ' is-multi' . ( $ev['is_start'] ? ' is-mstart' : '' ) . ( $ev['is_end'] ? ' is-mend' : '' ) : '';
					$out  .= '<a href="' . esc_url( $ev['url'] ) . '" class="paw-cal__ev' . esc_attr( $multi . ( $extra ? ' is-extra' : '' ) ) . '" ' . self::data( $ev ) . ( $extra ? ' hidden' : '' ) . '>'
						. '<span class="paw-cal__ev-time">' . esc_html( $ev['time'] ) . '</span> '
						. '<span class="paw-cal__ev-title">' . esc_html( $ev['title'] ) . '</span></a>';
				}
				if ( count( $events ) > self::MAX_PER_DAY ) {
					/* translators: %d: number of hidden events */
					$out .= '<button type="button" class="paw-cal__more" data-paw-cal-more aria-expanded="false" data-less="' . esc_attr__( 'Show less', 'pawfectly-reservations' ) . '">' . esc_html( sprintf( __( '+%d more', 'pawfectly-reservations' ), count( $events ) - self::MAX_PER_DAY ) ) . '</button>';
				}
				$out .= '</div>';
			}
			$out .= '<!-- /cell --></div>';
		}
		return $out . '</div></div>';
	}

	private static function agenda_html( string $ym, array $by, string $q ): string {
		$days = array_filter( $by, static fn( string $key ): bool => substr( $key, 0, 7 ) === $ym, ARRAY_FILTER_USE_KEY );
		if ( ! $days ) {
			/* translators: %s: search text */
			$msg = '' !== $q ? sprintf( __( 'No events match “%s” this month.', 'pawfectly-reservations' ), $q ) : __( 'No events this month.', 'pawfectly-reservations' );
			return self::empty_html( $msg );
		}
		$out = '<ul class="paw-cal__agenda-list">';
		foreach ( $days as $key => $events ) {
			$d    = self::d( $key );
			$out .= '<li class="paw-cal__agenda-day" data-day="' . esc_attr( $key ) . '"><div class="paw-cal__agenda-date"><span class="paw-cal__agenda-num">' . esc_html( $d->format( 'j' ) ) . '</span><span class="paw-cal__agenda-dow">' . esc_html( $d->format( 'D' ) ) . '</span></div><ul class="paw-cal__agenda-events">';
			foreach ( $events as $ev ) {
				$out .= '<li><a href="' . esc_url( $ev['url'] ) . '" class="paw-cal__agenda-ev" ' . self::data( $ev ) . '>'
					. '<span class="paw-cal__agenda-time">' . esc_html( $ev['multiday'] ? $ev['time'] : $ev['range'] ) . '</span>'
					. '<span class="paw-cal__agenda-info"><span class="paw-cal__agenda-title">' . esc_html( trim( $ev['emoji'] . ' ' . $ev['title'] ) ) . '</span>'
					. ( '' !== $ev['venue'] ? '<span class="paw-cal__agenda-venue">' . esc_html( $ev['venue'] ) . '</span>' : '' )
					. ( '' !== $ev['excerpt'] ? '<span class="paw-cal__agenda-ex">' . esc_html( $ev['excerpt'] ) . '</span>' : '' )
					. '</span></a></li>';
			}
			$out .= '</ul></li>';
		}
		return $out . '</ul>';
	}

	private static function day_html( \DateTimeImmutable $d, array $events ): string {
		if ( ! $events ) {
			/* translators: %s: a day, like "Saturday, December 19, 2026" */
			return self::empty_html( sprintf( __( 'No events on %s.', 'pawfectly-reservations' ), $d->format( 'l, F j, Y' ) ) );
		}
		$out = '<ul class="paw-cal__day-list">';
		foreach ( $events as $ev ) {
			$out .= '<li class="paw-cal__day-item"><span class="paw-cal__day-rail"><span class="paw-cal__day-time">' . esc_html( $ev['time'] ) . '</span><span class="paw-cal__day-dot" style="--paw-ev:' . esc_attr( $ev['color'] ) . '"></span></span>'
				. '<a href="' . esc_url( $ev['url'] ) . '" class="paw-cal__day-card" ' . self::data( $ev ) . '>'
				. '<span class="paw-cal__day-info"><span class="paw-cal__day-range">' . esc_html( $ev['range'] ) . '</span>'
				. '<span class="paw-cal__day-title">' . esc_html( trim( $ev['emoji'] . ' ' . $ev['title'] ) ) . '</span>'
				. ( '' !== $ev['venue'] ? '<span class="paw-cal__day-venue">' . esc_html( $ev['venue'] ) . '</span>' : '' )
				. ( '' !== $ev['excerpt'] ? '<span class="paw-cal__day-ex">' . esc_html( $ev['excerpt'] ) . '</span>' : '' )
				. '</span>'
				. ( '' !== $ev['thumb'] ? '<span class="paw-cal__day-thumb" style="background-image:url(\'' . esc_url( $ev['thumb'] ) . '\')"></span>' : '' )
				. '</a></li>';
		}
		return $out . '</ul>';
	}

	/** The data-* attributes the popover reads. */
	private static function data( array $ev ): string {
		return 'style="--paw-ev:' . esc_attr( $ev['color'] ) . '" data-paw-cal-event="' . (int) $ev['id'] . '"'
			. ' data-title="' . esc_attr( $ev['title'] ) . '" data-time="' . esc_attr( $ev['range'] ) . '" data-venue="' . esc_attr( $ev['venue'] ) . '"'
			. ' data-emoji="' . esc_attr( $ev['emoji'] ) . '" data-thumb="' . esc_url( $ev['thumb'] ) . '" data-excerpt="' . esc_attr( $ev['excerpt'] ) . '"'
			. ' data-cal="' . esc_attr( (string) wp_json_encode( $ev['cal'] ) ) . '"';
	}

	private static function empty_html( string $msg ): string {
		return '<div class="paw-cal__empty"><span aria-hidden="true">📅</span><p>' . esc_html( $msg ) . '</p></div>';
	}

	/** The tagline, or the start of the description, in about two lines. */
	private static function excerpt( array $ev ): string {
		$text = '' !== $ev['tagline'] ? $ev['tagline'] : trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $ev['description'] ) ) );
		return mb_strlen( $text ) > 140 ? rtrim( mb_substr( $text, 0, 139 ) ) . '…' : $text;
	}

	private static function today(): string {
		return ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
	}

	private static function valid( string $date ): bool {
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/** A calendar day for date arithmetic (UTC midnight, so no daylight-saving gaps). */
	private static function d( string $date ): \DateTimeImmutable {
		return new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) );
	}
}
