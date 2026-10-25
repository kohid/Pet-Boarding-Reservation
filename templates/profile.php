<?php
/**
 * My Profile: sign in / create account, or the signed-in owner's details and password.
 * Forms post JSON to the account REST routes (account.js); fields carry data-paw-field for errors.
 *
 * @package Pawfectly
 *
 * @var \WP_User $user
 * @var array    $links lost, staff, bookings.
 * @var string   $phone
 */

use Pawfectly\Profile;

defined( 'ABSPATH' ) || exit;

$paw_field = static function ( string $name, string $label, string $type, string $value = '', string $auto = '', string $form = '' ): void {
	$id = 'paw-acct-' . ( $form ? $form . '-' : '' ) . $name;
	?>
	<div class="paw-acct__field" data-paw-field="<?php echo esc_attr( $name ); ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $auto ? ' autocomplete="' . esc_attr( $auto ) . '"' : ''; ?>>
		<p class="paw-acct__error" role="alert" hidden></p>
	</div>
	<?php
};
?>
<div class="paw-acct paw-acct--profile" data-paw-acct>
	<?php if ( ! $user->exists() ) : ?>
		<?php echo Profile::notices( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
		<div class="paw-acct__card">
			<div class="paw-acct__tabs" role="tablist">
				<button type="button" class="paw-acct__tab" role="tab" id="paw-tab-signin" aria-controls="paw-panel-signin" aria-selected="true" data-paw-tab="signin"><?php esc_html_e( 'Sign in', 'pawfectly-reservations' ); ?></button>
				<button type="button" class="paw-acct__tab" role="tab" id="paw-tab-signup" aria-controls="paw-panel-signup" aria-selected="false" data-paw-tab="signup"><?php esc_html_e( 'Create account', 'pawfectly-reservations' ); ?></button>
			</div>

			<form class="paw-acct__panel" id="paw-panel-signin" role="tabpanel" aria-labelledby="paw-tab-signin" data-paw-form="account/login" novalidate>
				<h3><?php esc_html_e( 'Sign in', 'pawfectly-reservations' ); ?></h3>
				<div class="paw-acct__grid">
					<?php $paw_field( 'email', __( 'Email', 'pawfectly-reservations' ), 'email', '', 'username', 'in' ); ?>
					<?php $paw_field( 'password', __( 'Password', 'pawfectly-reservations' ), 'password', '', 'current-password', 'in' ); ?>
				</div>
				<p class="paw-acct__msg is-error" data-paw-msg role="alert" hidden></p>
				<div class="paw-acct__actions">
					<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Sign in', 'pawfectly-reservations' ); ?></button>
					<a class="paw-acct__link" href="<?php echo esc_url( $links['lost'] ); ?>"><?php esc_html_e( 'Forgot your password?', 'pawfectly-reservations' ); ?></a>
				</div>
				<p class="paw-acct__muted"><?php esc_html_e( 'Pawfectly staff:', 'pawfectly-reservations' ); ?> <a class="paw-acct__link" href="<?php echo esc_url( $links['staff'] ); ?>"><?php esc_html_e( 'use the staff sign-in', 'pawfectly-reservations' ); ?></a></p>
			</form>

			<form class="paw-acct__panel" id="paw-panel-signup" role="tabpanel" aria-labelledby="paw-tab-signup" data-paw-form="account/signup" novalidate hidden>
				<h3><?php esc_html_e( 'Create account', 'pawfectly-reservations' ); ?></h3>
				<div class="paw-acct__grid paw-acct__grid--2">
					<?php $paw_field( 'first_name', __( 'First name', 'pawfectly-reservations' ), 'text', '', 'given-name', 'up' ); ?>
					<?php $paw_field( 'last_name', __( 'Last name', 'pawfectly-reservations' ), 'text', '', 'family-name', 'up' ); ?>
					<?php $paw_field( 'email', __( 'Email', 'pawfectly-reservations' ), 'email', '', 'email', 'up' ); ?>
					<?php $paw_field( 'phone', __( 'Phone (optional)', 'pawfectly-reservations' ), 'tel', '', 'tel', 'up' ); ?>
					<?php $paw_field( 'password', __( 'Password (at least 8 characters)', 'pawfectly-reservations' ), 'password', '', 'new-password', 'up' ); ?>
				</div>
				<div class="paw-acct-hp" aria-hidden="true" style="position:absolute;left:-9999px"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
				<p class="paw-acct__msg is-error" data-paw-msg role="alert" hidden></p>
				<div class="paw-acct__actions">
					<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Create account', 'pawfectly-reservations' ); ?></button>
				</div>
			</form>
		</div>
	<?php else : ?>
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
	<?php endif; ?>
</div>
