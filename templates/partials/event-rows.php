<?php
/**
 * One section's rows: sessions, includes, goodies or good_to_know. The event page draws them
 * styled; [pawfectly_event] draws them plain, with paw-ev-* classes to style in a page builder.
 *
 * @package Pawfectly
 *
 * @var array  $paw_ev      The event (Events::get()).
 * @var string $paw_section The section key.
 * @var bool   $paw_plain   True for the shortcode's plain list.
 */

use Pawfectly\EventDates;

defined( 'ABSPATH' ) || exit;

$paw_rows = $paw_ev['sections'][ $paw_section ]['rows'] ?? array();
if ( ! $paw_rows ) {
	return;
}
$paw_text  = static fn( string $text ): string => EventDates::phone_links( esc_html( $text ) ); // Escaped, then phone numbers linked.
$paw_emoji = static function ( array $row ) use ( $paw_plain ): string {
	if ( $paw_plain ) {
		return '' !== $row['emoji'] ? '<span class="paw-ev-row__emoji" aria-hidden="true">' . esc_html( $row['emoji'] ) . '</span>' : '';
	}
	return esc_html( '' !== $row['emoji'] ? $row['emoji'] : '🐾' );
};
// phpcs:disable WordPress.Security.EscapeOutput -- every piece is escaped in $paw_text / $paw_emoji or below.
?>
<?php if ( $paw_plain ) : ?>
	<ul class="paw-ev-list paw-ev-list--<?php echo esc_attr( $paw_section ); ?>">
		<?php foreach ( $paw_rows as $paw_row ) : ?>
			<li class="paw-ev-row">
				<?php if ( 'sessions' === $paw_section ) : ?>
					<span class="paw-ev-row__time"><?php echo esc_html( EventDates::times( $paw_row['from'], $paw_row['to'] ) ); ?></span>
					<?php if ( '' !== $paw_row['name'] ) : ?>
						<span class="paw-ev-row__name"><?php echo esc_html( $paw_row['name'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $paw_row['who'] ) : ?>
						<span class="paw-ev-row__who"><?php echo esc_html( $paw_row['who'] ); ?></span>
					<?php endif; ?>
				<?php else : ?>
					<?php echo $paw_emoji( $paw_row ); ?>
					<?php if ( 'goodies' === $paw_section ) : ?>
						<strong class="paw-ev-row__title"><?php echo esc_html( $paw_row['title'] ); ?></strong>
					<?php endif; ?>
					<?php if ( '' !== $paw_row['text'] ) : ?>
						<span class="paw-ev-row__text"><?php echo $paw_text( $paw_row['text'] ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php elseif ( 'sessions' === $paw_section ) : ?>
	<ul class="paw-event__sessions">
		<?php foreach ( $paw_rows as $paw_row ) : ?>
			<li class="paw-event__session">
				<span class="paw-event__session-time"><?php echo esc_html( EventDates::times( $paw_row['from'], $paw_row['to'] ) ); ?></span>
				<span class="paw-event__session-name"><?php echo esc_html( implode( ': ', array_filter( array( $paw_row['name'], $paw_row['who'] ), 'strlen' ) ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
<?php elseif ( 'includes' === $paw_section ) : ?>
	<ul class="paw-event__includes">
		<?php foreach ( $paw_rows as $paw_row ) : ?>
			<li class="paw-event__include"><span class="paw-event__include-emoji" aria-hidden="true"><?php echo $paw_emoji( $paw_row ); ?></span><span><?php echo $paw_text( $paw_row['text'] ); ?></span></li>
		<?php endforeach; ?>
	</ul>
<?php elseif ( 'goodies' === $paw_section ) : ?>
	<div class="paw-event__goodies">
		<?php foreach ( $paw_rows as $paw_row ) : ?>
			<div class="paw-event__goodie">
				<span class="paw-event__goodie-emoji" aria-hidden="true"><?php echo $paw_emoji( $paw_row ); ?></span>
				<h3 class="paw-event__goodie-title"><?php echo esc_html( $paw_row['title'] ); ?></h3>
				<?php if ( '' !== $paw_row['text'] ) : ?>
					<p><?php echo $paw_text( $paw_row['text'] ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php else : ?>
	<ul class="paw-event__knows">
		<?php foreach ( $paw_rows as $paw_row ) : ?>
			<li class="paw-event__know"><span class="paw-event__know-emoji" aria-hidden="true"><?php echo $paw_emoji( $paw_row ); ?></span><span><?php echo $paw_text( $paw_row['text'] ); ?></span></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
<?php
// phpcs:enable
