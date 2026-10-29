<?php
/**
 * The event sign-up form, in the event page's closing block. signup.js sends it to
 * POST /events/<id>/signup after fetching a fresh nonce (pages may be cached).
 * The members' code is never printed: only whether the event has one.
 *
 * @package Pawfectly
 *
 * @var array $paw_ev The event (Events::get()).
 */

use Pawfectly\Rest;
use Pawfectly\Services;
use Pawfectly\Signups;

defined( 'ABSPATH' ) || exit;

$paw_labels   = Signups::session_labels( $paw_ev );
$paw_user     = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
$paw_me       = $paw_user && $paw_user->exists() ? array(
	'first_name' => (string) $paw_user->first_name,
	'last_name'  => (string) $paw_user->last_name,
	'email'      => (string) $paw_user->user_email,
	'phone'      => (string) get_user_meta( $paw_user->ID, 'paw_phone', true ),
) : array( 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '' );
$paw_su_config = array(
	'route'    => rest_url( Rest::NS . '/events/' . (int) $paw_ev['id'] . '/signup' ),
	'nonceUrl' => rest_url( Rest::NS . '/nonce' ),
	'price'    => $paw_ev['free'] || '' === (string) $paw_ev['price'] ? null : (float) $paw_ev['price'],
	'free'     => (bool) $paw_ev['free'],
	'currency' => (string) ( \Pawfectly\Settings::get()['currency'] ?? '$' ),
	'maxDogs'  => Signups::MAX_DOGS,
	'i18n'     => array(
		/* translators: 1: number of dogs, 2: price each, 3: total */
		'total'      => __( '%1$d × %2$s = %3$s, paid at the event', 'pawfectly-reservations' ),
		'free'       => __( 'Free', 'pawfectly-reservations' ),
		'onRequest'  => __( 'Price on request', 'pawfectly-reservations' ),
		'codeNote'   => __( 'Member codes are checked when you send.', 'pawfectly-reservations' ),
		/* translators: %d: dog number */
		'dogN'       => __( 'Dog %d', 'pawfectly-reservations' ),
		'sending'    => __( 'Sending…', 'pawfectly-reservations' ),
		'failed'     => __( "We couldn't reach the server. Your answers are still here — please try again.", 'pawfectly-reservations' ),
		'fixErrors'  => __( 'Please check the highlighted fields.', 'pawfectly-reservations' ),
	),
);
$paw_field = static function ( string $key, string $label, string $control, bool $required = true ): void {
	?>
	<div class="paw-event__su-field" data-paw-su-key="<?php echo esc_attr( $key ); ?>">
		<label><span><?php echo esc_html( $label ); ?><?php echo $required ? '<span class="paw-event__su-req" aria-hidden="true">*</span>' : ''; ?></span>
			<?php echo $control; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts by the caller. ?>
		</label>
	</div>
	<?php
};
$paw_dog = static function ( string $n, string $num ) use ( $paw_field ): void {
	?>
	<fieldset class="paw-event__su-dog" data-paw-su-dog>
		<legend><span data-paw-su-dog-title><?php /* translators: %s: dog number */ echo esc_html( sprintf( __( 'Dog %s', 'pawfectly-reservations' ), $num ) ); ?></span>
			<button type="button" class="paw-event__su-remove" data-paw-su-remove><?php esc_html_e( 'Remove', 'pawfectly-reservations' ); ?></button></legend>
		<div class="paw-event__su-grid paw-event__su-grid--dog">
			<?php
			$paw_field( "dogs.$n.name", __( 'Name', 'pawfectly-reservations' ), '<input type="text" data-paw-su-dog-field="name" maxlength="60" autocomplete="off">' );
			$paw_field( "dogs.$n.breed", __( 'Breed', 'pawfectly-reservations' ), '<input type="text" data-paw-su-dog-field="breed" maxlength="60" autocomplete="off">', false );
			$paw_field( "dogs.$n.weight", __( 'Weight', 'pawfectly-reservations' ), '<input type="text" data-paw-su-dog-field="weight" maxlength="20" placeholder="' . esc_attr__( 'e.g. 25 lbs', 'pawfectly-reservations' ) . '" autocomplete="off">', false );
			?>
		</div>
	</fieldset>
	<?php
};
?>
<form class="paw-event__signup" data-paw-signup novalidate data-config="<?php echo esc_attr( (string) wp_json_encode( $paw_su_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?>">
	<p class="paw-event__su-alert" data-paw-su-alert role="alert" hidden></p>

	<?php if ( $paw_labels ) : ?>
		<fieldset class="paw-event__su-block" data-paw-su-key="session">
			<legend class="paw-event__su-title"><?php esc_html_e( 'Pick your session', 'pawfectly-reservations' ); ?><span class="paw-event__su-req" aria-hidden="true">*</span></legend>
			<div class="paw-event__su-sessions">
				<?php foreach ( $paw_labels as $paw_n => $paw_label ) : ?>
					<?php
					$paw_left = Signups::spots_left( $paw_ev, (int) $paw_n );
					$paw_row  = $paw_ev['sections']['sessions']['rows'][ $paw_n ];
					?>
					<label class="paw-event__su-session<?php echo 0 === $paw_left ? ' is-full' : ''; ?>">
						<input type="radio" name="session" value="<?php echo esc_attr( (string) $paw_n ); ?>"<?php echo 0 === $paw_left ? ' disabled' : ''; ?>>
						<span class="paw-event__su-session-time"><?php echo esc_html( \Pawfectly\EventDates::times( $paw_row['from'], $paw_row['to'] ) ); ?></span>
						<span class="paw-event__su-session-name"><?php echo esc_html( implode( ': ', array_filter( array( $paw_row['name'], $paw_row['who'] ), 'strlen' ) ) ); ?></span>
						<?php if ( null !== $paw_left ) : ?>
							<span class="paw-event__su-left">
								<?php
								echo esc_html(
									0 === $paw_left ? __( 'Full', 'pawfectly-reservations' )
									/* translators: %d: spots left */
									: sprintf( _n( '%d spot left', '%d spots left', $paw_left, 'pawfectly-reservations' ), $paw_left )
								);
								?>
							</span>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endif; ?>

	<fieldset class="paw-event__su-block">
		<legend class="paw-event__su-title"><?php esc_html_e( 'Your details', 'pawfectly-reservations' ); ?></legend>
		<div class="paw-event__su-grid">
			<?php
			$paw_field( 'first_name', __( 'First name', 'pawfectly-reservations' ), '<input type="text" name="first_name" maxlength="60" autocomplete="given-name" value="' . esc_attr( $paw_me['first_name'] ) . '">' );
			$paw_field( 'last_name', __( 'Last name', 'pawfectly-reservations' ), '<input type="text" name="last_name" maxlength="60" autocomplete="family-name" value="' . esc_attr( $paw_me['last_name'] ) . '">' );
			$paw_field( 'email', __( 'Email', 'pawfectly-reservations' ), '<input type="email" name="email" maxlength="100" autocomplete="email" value="' . esc_attr( $paw_me['email'] ) . '">' );
			$paw_field( 'phone', __( 'Phone', 'pawfectly-reservations' ), '<input type="tel" name="phone" maxlength="30" autocomplete="tel" value="' . esc_attr( $paw_me['phone'] ) . '">' );
			?>
		</div>
	</fieldset>

	<div class="paw-event__su-block" data-paw-su-key="dogs">
		<p class="paw-event__su-title"><?php esc_html_e( 'Your dogs', 'pawfectly-reservations' ); ?></p>
		<div class="paw-event__su-dogs" data-paw-su-dogs><?php $paw_dog( '0', '1' ); ?></div>
		<template data-paw-su-dog-template><?php $paw_dog( '__N__', '__NUM__' ); ?></template>
		<button type="button" class="paw-event__su-add" data-paw-su-add><?php esc_html_e( '+ Add another dog', 'pawfectly-reservations' ); ?></button>
	</div>

	<div class="paw-event__su-block">
		<div class="paw-event__su-grid">
			<?php
			if ( ! $paw_ev['free'] && '' !== (string) $paw_ev['coupon'] ) {
				$paw_field( 'coupon', __( 'Member code (optional)', 'pawfectly-reservations' ), '<input type="text" name="coupon" maxlength="30" autocomplete="off" autocapitalize="characters">', false );
			}
			$paw_field( 'notes', __( 'Notes (optional)', 'pawfectly-reservations' ), '<textarea name="notes" rows="2" maxlength="500"></textarea>', false );
			?>
		</div>
		<div class="paw-event__su-field paw-event__su-agree" data-paw-su-key="agree">
			<label><input type="checkbox" name="agree" value="1"> <span><?php esc_html_e( 'My dogs are up to date on their vaccinations and are friendly with other dogs.', 'pawfectly-reservations' ); ?></span></label>
		</div>
		<div class="paw-event__su-hp" aria-hidden="true"><label><?php esc_html_e( 'Leave this empty', 'pawfectly-reservations' ); ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
	</div>

	<div class="paw-event__su-submit">
		<p class="paw-event__su-total" data-paw-su-total aria-live="polite"></p>
		<button type="submit" class="paw-event__btn"><?php esc_html_e( 'Reserve Your Spot', 'pawfectly-reservations' ); ?></button>
	</div>
</form>

<div class="paw-event__thanks" data-paw-su-thanks tabindex="-1" hidden>
	<p class="paw-event__thanks-emoji" aria-hidden="true">🎉</p>
	<h3 class="paw-event__thanks-title"><?php esc_html_e( "You're signed up!", 'pawfectly-reservations' ); ?></h3>
	<p class="paw-event__thanks-ref"><?php esc_html_e( 'Reference', 'pawfectly-reservations' ); ?> <strong data-paw-su-ref></strong></p>
	<dl class="paw-event__thanks-list" data-paw-su-summary></dl>
	<p class="paw-event__thanks-note"><?php esc_html_e( "We've emailed you a confirmation.", 'pawfectly-reservations' ); ?></p>
	<p class="paw-event__thanks-cal" data-paw-su-cal></p>
</div>
