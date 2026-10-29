<?php
/**
 * One event card in [pawfectly_events].
 *
 * @package Pawfectly
 *
 * @var array $paw_ev The event (Events::get()).
 */

use Pawfectly\EventDates;

defined( 'ABSPATH' ) || exit;

$paw_start = EventDates::at( $paw_ev['start'] );
$paw_one   = $paw_start->format( 'Y-m-d' ) === EventDates::at( $paw_ev['end'] )->format( 'Y-m-d' );
$paw_price = EventDates::price_text( $paw_ev );
?>
<a class="paw-events__card" href="<?php echo esc_url( $paw_ev['url'] ); ?>" style="--paw-ev:<?php echo esc_attr( $paw_ev['color'] ); ?>">
	<?php if ( '' !== $paw_ev['photo_url'] ) : ?>
		<span class="paw-events__media" style="background-image:url('<?php echo esc_url( $paw_ev['photo_url'] ); ?>')" aria-hidden="true"></span>
	<?php else : ?>
		<span class="paw-events__media paw-events__media--emoji" aria-hidden="true"><span class="paw-events__emoji"><?php echo esc_html( '' !== $paw_ev['emoji'] ? $paw_ev['emoji'] : '🐾' ); ?></span></span>
	<?php endif; ?>
	<span class="paw-events__date" aria-hidden="true"><span class="paw-events__mon"><?php echo esc_html( strtoupper( $paw_start->format( 'M' ) ) ); ?></span><span class="paw-events__day"><?php echo esc_html( $paw_start->format( 'j' ) ); ?></span></span>
	<span class="paw-events__body">
		<?php if ( '' !== $paw_ev['category_name'] ) : ?>
			<span class="paw-events__cat"><?php echo esc_html( $paw_ev['category_name'] ); ?></span>
		<?php endif; ?>
		<span class="paw-events__title"><?php echo esc_html( $paw_ev['title'] ); ?></span>
		<span class="paw-events__when"><?php echo esc_html( $paw_one ? EventDates::day( $paw_ev ) . ' · ' . EventDates::time_range( $paw_ev ) : EventDates::date_line( $paw_ev ) ); ?></span>
		<span class="paw-events__venue"><?php echo esc_html( $paw_ev['venue'] ); ?></span>
		<?php if ( '' !== $paw_price ) : ?>
			<span class="paw-events__price"><?php echo esc_html( $paw_price ); ?></span>
		<?php endif; ?>
	</span>
</a>
