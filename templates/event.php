<?php
/**
 * An event's page body, laid out like the X-Mas Party example: date line, heading, facts,
 * description, tickets, then the sections and the closing call to action.
 *
 * @package Pawfectly
 *
 * @var array $paw_ev   The event (Events::get()).
 * @var bool  $paw_past True once the event has ended.
 */

use Pawfectly\EventDates;
use Pawfectly\Settings;

defined( 'ABSPATH' ) || exit;

$paw_s       = $paw_ev['sections'];
$paw_fill    = static fn( string $text ): string => EventDates::fill( $text, $paw_ev );
$paw_text    = static fn( string $text ): string => EventDates::phone_links( esc_html( $text ) ); // Escaped, then phone numbers linked.
$paw_price   = EventDates::price_text( $paw_ev );
$paw_one_day = EventDates::at( $paw_ev['start'] )->format( 'Y-m-d' ) === EventDates::at( $paw_ev['end'] )->format( 'Y-m-d' );
$paw_ticket  = '' !== $paw_ev['ticket_url'] ? $paw_ev['ticket_url'] : '#paw-event-reserve';
$paw_cta     = $paw_ev['free'] || '' === $paw_ev['price']
	? __( 'Get Tickets', 'pawfectly-reservations' )
	/* translators: %s: ticket price, like "$15" */
	: sprintf( __( 'Get Tickets — %s', 'pawfectly-reservations' ), \Pawfectly\Services::money( (float) $paw_ev['price'] ) );
$paw_where   = EventDates::where( $paw_ev );
$paw_links   = EventDates::links( $paw_ev );
$paw_desc    = wp_kses( (string) $paw_ev['description'], Settings::WAIVER_TAGS + array( 'a' => array( 'href' => true ) ) );
?>
<article class="paw-event" style="--paw-ev:<?php echo esc_attr( $paw_ev['color'] ); ?>">
	<header class="paw-event__hero<?php echo '' !== $paw_ev['photo_url'] ? ' has-photo' : ''; ?>"<?php echo '' !== $paw_ev['photo_url'] ? ' style="background-image:url(\'' . esc_url( $paw_ev['photo_url'] ) . '\')"' : ''; ?>>
		<div class="paw-event__hero-inner">
			<?php if ( $paw_past ) : ?>
				<p class="paw-event__ended"><?php esc_html_e( 'This event has ended', 'pawfectly-reservations' ); ?></p>
			<?php endif; ?>
			<p class="paw-event__dateline"><?php echo esc_html( EventDates::date_line( $paw_ev ) ); ?></p>
			<?php if ( '' !== $paw_ev['emoji'] ) : ?>
				<p class="paw-event__emoji" aria-hidden="true"><?php echo esc_html( $paw_ev['emoji'] ); ?></p>
			<?php endif; ?>
			<h1 class="paw-event__title"><?php echo esc_html( $paw_ev['title'] ); ?></h1>
			<?php if ( '' !== $paw_ev['tagline'] ) : ?>
				<p class="paw-event__tagline"><?php echo esc_html( $paw_ev['tagline'] ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<ul class="paw-event__facts">
		<li><span aria-hidden="true">📅</span> <?php echo esc_html( $paw_one_day ? EventDates::day( $paw_ev ) : EventDates::date_line( $paw_ev ) ); ?></li>
		<li><span aria-hidden="true">🕘</span> <?php echo esc_html( $paw_one_day ? EventDates::time_range( $paw_ev ) : EventDates::at( $paw_ev['start'] )->format( 'g:i A' ) . ' – ' . EventDates::at( $paw_ev['end'] )->format( 'g:i A' ) ); ?></li>
		<li><span aria-hidden="true">📍</span> <a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . urlencode( $paw_where ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $paw_where ); ?></a></li>
		<?php if ( '' !== $paw_price ) : ?>
			<li><span aria-hidden="true">🎟️</span> <?php echo esc_html( $paw_price ); ?></li>
		<?php endif; ?>
	</ul>

	<?php if ( '' !== trim( $paw_desc ) ) : ?>
		<div class="paw-event__description"><?php echo $paw_desc; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered by wp_kses above. ?></div>
	<?php endif; ?>

	<div class="paw-event__tickets">
		<?php if ( ! $paw_past ) : ?>
			<a class="paw-event__btn" href="<?php echo esc_url( $paw_ticket ); ?>" data-paw-ticket><?php echo esc_html( $paw_cta ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $paw_ev['note'] ) : ?>
			<p class="paw-event__note"><?php echo $paw_text( $paw_ev['note'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $paw_text. ?></p>
		<?php endif; ?>
		<details class="paw-event__addcal">
			<summary><?php esc_html_e( 'Add to calendar', 'pawfectly-reservations' ); ?></summary>
			<a href="<?php echo esc_url( $paw_links['google'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Google Calendar', 'pawfectly-reservations' ); ?></a>
			<a href="<?php echo esc_url( $paw_links['outlook'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Outlook', 'pawfectly-reservations' ); ?></a>
			<a href="<?php echo esc_url( $paw_links['ics'] ); ?>"><?php esc_html_e( 'Apple / iCal (.ics)', 'pawfectly-reservations' ); ?></a>
		</details>
	</div>

	<?php if ( $paw_s['sessions']['rows'] ) : ?>
		<section class="paw-event__section">
			<h2 class="paw-event__heading"><?php echo esc_html( $paw_fill( $paw_s['sessions']['heading'] ) ); ?></h2>
			<?php if ( '' !== $paw_s['sessions']['intro'] ) : ?>
				<p class="paw-event__intro"><?php echo esc_html( $paw_fill( $paw_s['sessions']['intro'] ) ); ?></p>
			<?php endif; ?>
			<ul class="paw-event__sessions">
				<?php foreach ( $paw_s['sessions']['rows'] as $paw_row ) : ?>
					<li class="paw-event__session">
						<span class="paw-event__session-time"><?php echo esc_html( EventDates::times( $paw_row['from'], $paw_row['to'] ) ); ?></span>
						<span class="paw-event__session-name"><?php echo esc_html( implode( ': ', array_filter( array( $paw_row['name'], $paw_row['who'] ), 'strlen' ) ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $paw_s['includes']['rows'] ) : ?>
		<section class="paw-event__section">
			<h2 class="paw-event__heading"><?php echo esc_html( $paw_fill( $paw_s['includes']['heading'] ) ); ?></h2>
			<?php if ( '' !== $paw_s['includes']['intro'] ) : ?>
				<p class="paw-event__intro"><?php echo esc_html( $paw_fill( $paw_s['includes']['intro'] ) ); ?></p>
			<?php endif; ?>
			<ul class="paw-event__includes">
				<?php foreach ( $paw_s['includes']['rows'] as $paw_row ) : ?>
					<li class="paw-event__include"><span class="paw-event__include-emoji" aria-hidden="true"><?php echo esc_html( '' !== $paw_row['emoji'] ? $paw_row['emoji'] : '🐾' ); ?></span><span><?php echo $paw_text( $paw_row['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $paw_s['goodies']['rows'] ) : ?>
		<section class="paw-event__section">
			<h2 class="paw-event__heading"><?php echo esc_html( $paw_fill( $paw_s['goodies']['heading'] ) ); ?></h2>
			<div class="paw-event__goodies">
				<?php foreach ( $paw_s['goodies']['rows'] as $paw_row ) : ?>
					<div class="paw-event__goodie">
						<span class="paw-event__goodie-emoji" aria-hidden="true"><?php echo esc_html( '' !== $paw_row['emoji'] ? $paw_row['emoji'] : '🐾' ); ?></span>
						<h3 class="paw-event__goodie-title"><?php echo esc_html( $paw_row['title'] ); ?></h3>
						<?php if ( '' !== $paw_row['text'] ) : ?>
							<p><?php echo $paw_text( $paw_row['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $paw_s['good_to_know']['rows'] ) : ?>
		<section class="paw-event__section">
			<h2 class="paw-event__heading"><?php echo esc_html( $paw_fill( $paw_s['good_to_know']['heading'] ) ); ?></h2>
			<ul class="paw-event__knows">
				<?php foreach ( $paw_s['good_to_know']['rows'] as $paw_row ) : ?>
					<li class="paw-event__know"><span class="paw-event__know-emoji" aria-hidden="true"><?php echo esc_html( '' !== $paw_row['emoji'] ? $paw_row['emoji'] : '🐾' ); ?></span><span><?php echo $paw_text( $paw_row['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<section class="paw-event__closing" id="paw-event-reserve">
		<h2 class="paw-event__heading"><?php echo esc_html( $paw_fill( $paw_s['closing']['heading'] ) ); ?></h2>
		<p class="paw-event__summary"><?php echo esc_html( EventDates::summary( $paw_ev ) ); ?></p>
		<?php if ( '' !== $paw_s['closing']['intro'] ) : ?>
			<p class="paw-event__intro"><?php echo esc_html( $paw_fill( $paw_s['closing']['intro'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( $paw_past ) : ?>
			<p class="paw-event__soon"><?php esc_html_e( 'This event has ended', 'pawfectly-reservations' ); ?></p>
		<?php elseif ( '' !== $paw_ev['ticket_url'] ) : ?>
			<a class="paw-event__btn" href="<?php echo esc_url( $paw_ev['ticket_url'] ); ?>" data-paw-ticket><?php esc_html_e( 'Reserve Your Spot', 'pawfectly-reservations' ); ?></a>
		<?php elseif ( 'publish' === $paw_ev['status'] ) : ?>
			<?php include PAW_DIR . 'templates/partials/event-signup.php'; ?>
		<?php else : ?>
			<p class="paw-event__soon"><?php esc_html_e( 'The sign-up form appears here once the event is published.', 'pawfectly-reservations' ); ?></p>
		<?php endif; ?>
	</section>
</article>
