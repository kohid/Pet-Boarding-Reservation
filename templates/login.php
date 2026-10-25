<?php
/**
 * The sign-in / create-account panel: [pawfectly_login], and in place on account pages when
 * nobody is signed in. Forms post JSON to the account REST routes (account.js).
 *
 * @package Pawfectly
 *
 * @var string $start       'signin' or 'signup': the tab shown first.
 * @var string $redirect_to Same-site address to return to after signing in, or ''.
 * @var string $lost_url
 */

use Pawfectly\Profile;

defined( 'ABSPATH' ) || exit;

$paw_field = include PAW_DIR . 'templates/partials/acct-field.php';
$paw_in    = 'signin' === $start;
?>
<div class="paw-acct paw-acct--login" data-paw-acct data-paw-start="<?php echo esc_attr( $start ); ?>">
	<?php echo Profile::notices( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
	<div class="paw-acct__card">
		<div class="paw-acct__tabs" role="tablist">
			<button type="button" class="paw-acct__tab" role="tab" id="paw-tab-signin" aria-controls="paw-panel-signin" aria-selected="<?php echo $paw_in ? 'true' : 'false'; ?>" data-paw-tab="signin"><?php esc_html_e( 'Sign in', 'pawfectly-reservations' ); ?></button>
			<button type="button" class="paw-acct__tab" role="tab" id="paw-tab-signup" aria-controls="paw-panel-signup" aria-selected="<?php echo $paw_in ? 'false' : 'true'; ?>" data-paw-tab="signup"><?php esc_html_e( 'Create account', 'pawfectly-reservations' ); ?></button>
		</div>

		<form class="paw-acct__panel" id="paw-panel-signin" role="tabpanel" aria-labelledby="paw-tab-signin" data-paw-form="account/login" novalidate<?php echo $paw_in ? '' : ' hidden'; ?>>
			<h3><?php esc_html_e( 'Sign in', 'pawfectly-reservations' ); ?></h3>
			<div class="paw-acct__grid">
				<?php $paw_field( 'email', __( 'Email or username', 'pawfectly-reservations' ), 'text', '', 'username', 'in' ); ?>
				<?php $paw_field( 'password', __( 'Password', 'pawfectly-reservations' ), 'password', '', 'current-password', 'in' ); ?>
			</div>
			<label class="paw-acct__check"><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Remember me', 'pawfectly-reservations' ); ?></label>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
			<p class="paw-acct__msg is-error" data-paw-msg role="alert" hidden></p>
			<div class="paw-acct__actions">
				<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Sign in', 'pawfectly-reservations' ); ?></button>
				<a class="paw-acct__link" href="<?php echo esc_url( $lost_url ); ?>"><?php esc_html_e( 'Forgot your password?', 'pawfectly-reservations' ); ?></a>
			</div>
		</form>

		<form class="paw-acct__panel" id="paw-panel-signup" role="tabpanel" aria-labelledby="paw-tab-signup" data-paw-form="account/signup" novalidate<?php echo $paw_in ? ' hidden' : ''; ?>>
			<h3><?php esc_html_e( 'Create account', 'pawfectly-reservations' ); ?></h3>
			<div class="paw-acct__grid paw-acct__grid--2">
				<?php $paw_field( 'first_name', __( 'First name', 'pawfectly-reservations' ), 'text', '', 'given-name', 'up' ); ?>
				<?php $paw_field( 'last_name', __( 'Last name', 'pawfectly-reservations' ), 'text', '', 'family-name', 'up' ); ?>
				<?php $paw_field( 'email', __( 'Email', 'pawfectly-reservations' ), 'email', '', 'email', 'up' ); ?>
				<?php $paw_field( 'phone', __( 'Phone (optional)', 'pawfectly-reservations' ), 'tel', '', 'tel', 'up' ); ?>
				<?php $paw_field( 'password', __( 'Password (at least 8 characters)', 'pawfectly-reservations' ), 'password', '', 'new-password', 'up' ); ?>
				<?php $paw_field( 'confirm', __( 'Confirm password', 'pawfectly-reservations' ), 'password', '', 'new-password', 'up' ); ?>
			</div>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
			<div class="paw-acct-hp" aria-hidden="true" style="position:absolute;left:-9999px"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
			<p class="paw-acct__msg is-error" data-paw-msg role="alert" hidden></p>
			<div class="paw-acct__actions">
				<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Create account', 'pawfectly-reservations' ); ?></button>
			</div>
		</form>
	</div>
</div>
