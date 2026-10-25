<?php
/**
 * My Profile for a signed-in user: details and password. Signed out, Profile shows the login panel.
 * Forms post JSON to the account REST routes (account.js); fields carry data-paw-field for errors.
 *
 * @package Pawfectly
 *
 * @var \WP_User $user
 * @var array    $links bookings.
 * @var string   $phone
 */

use Pawfectly\Profile;

defined( 'ABSPATH' ) || exit;

$paw_field = include PAW_DIR . 'templates/partials/acct-field.php';
?>
<div class="paw-acct paw-acct--profile" data-paw-acct>
	<?php echo Profile::notices( (int) $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
	<form class="paw-acct__card" data-paw-form="account/profile" novalidate>
		<h2><?php esc_html_e( 'My Profile', 'pawfectly-reservations' ); ?></h2>
		<div class="paw-acct__grid paw-acct__grid--2">
			<?php $paw_field( 'first_name', __( 'First name', 'pawfectly-reservations' ), 'text', (string) $user->first_name, 'given-name' ); ?>
			<?php $paw_field( 'last_name', __( 'Last name', 'pawfectly-reservations' ), 'text', (string) $user->last_name, 'family-name' ); ?>
			<?php $paw_field( 'email', __( 'Email', 'pawfectly-reservations' ), 'email', (string) $user->user_email, 'email' ); ?>
			<?php $paw_field( 'phone', __( 'Phone', 'pawfectly-reservations' ), 'tel', $phone, 'tel' ); ?>
		</div>
		<p class="paw-acct__msg" data-paw-msg role="status" hidden></p>
		<div class="paw-acct__actions">
			<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Save details', 'pawfectly-reservations' ); ?></button>
			<?php if ( '' !== $links['bookings'] ) : ?>
				<a class="paw-acct__link" href="<?php echo esc_url( $links['bookings'] ); ?>"><?php esc_html_e( 'My Bookings', 'pawfectly-reservations' ); ?></a>
			<?php endif; ?>
		</div>
	</form>

	<form class="paw-acct__card" data-paw-form="account/password" novalidate>
		<h2><?php esc_html_e( 'Change password', 'pawfectly-reservations' ); ?></h2>
		<div class="paw-acct__grid">
			<?php $paw_field( 'current', __( 'Current password', 'pawfectly-reservations' ), 'password', '', 'current-password' ); ?>
			<?php $paw_field( 'password', __( 'New password (at least 8 characters)', 'pawfectly-reservations' ), 'password', '', 'new-password' ); ?>
			<?php $paw_field( 'confirm', __( 'Repeat new password', 'pawfectly-reservations' ), 'password', '', 'new-password' ); ?>
		</div>
		<p class="paw-acct__msg" data-paw-msg role="status" hidden></p>
		<div class="paw-acct__actions">
			<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Change password', 'pawfectly-reservations' ); ?></button>
		</div>
	</form>
</div>
