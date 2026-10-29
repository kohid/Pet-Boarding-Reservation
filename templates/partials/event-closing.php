<?php
/**
 * Where people sign up: "This event has ended" once it's over, the ticket button when tickets are
 * sold elsewhere, otherwise the sign-up form (published events only).
 *
 * @package Pawfectly
 *
 * @var array $paw_ev    The event (Events::get()).
 * @var bool  $paw_past  True once the event has ended.
 * @var bool  $paw_plain True in [pawfectly_event part="signup"]: no ticket button, no draft note.
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( $paw_past ) : ?>
	<p class="paw-event__soon"><?php esc_html_e( 'This event has ended', 'pawfectly-reservations' ); ?></p>
<?php elseif ( '' !== $paw_ev['ticket_url'] ) : ?>
	<?php if ( ! $paw_plain ) : ?>
		<a class="paw-event__btn" href="<?php echo esc_url( $paw_ev['ticket_url'] ); ?>" data-paw-ticket><?php esc_html_e( 'Reserve Your Spot', 'pawfectly-reservations' ); ?></a>
	<?php endif; ?>
<?php elseif ( 'publish' === $paw_ev['status'] ) : ?>
	<?php include PAW_DIR . 'templates/partials/event-signup.php'; ?>
<?php elseif ( ! $paw_plain ) : ?>
	<p class="paw-event__soon"><?php esc_html_e( 'The sign-up form appears here once the event is published.', 'pawfectly-reservations' ); ?></p>
<?php endif; ?>
