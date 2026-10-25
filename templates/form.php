<?php
/**
 * The reservation form: a welcome screen, one section per step, a thank-you screen and the bottom nav.
 *
 * @package Pawfectly
 *
 * @var array  $settings  Settings::get().
 * @var array  $steps     Fields::steps().
 * @var array  $countries Fields::countries().
 * @var string $logo      Logo URL.
 * @var array  $account   Shortcode::account().
 */

use Pawfectly\Fields;
use Pawfectly\Services;

defined( 'ABSPATH' ) || exit;

$paw_label = static function ( array $field ): string {
	$req = $field['required'] ? '<span class="paw-req" aria-hidden="true">*</span>' : '';
	return esc_html( $field['label'] ) . $req;
};
// One field, named $name, with element ids starting $dom.
$paw_field = static function ( array $paw_f, string $paw_name, string $paw_dom ) use ( $settings, $countries, $paw_label ): void {
	include PAW_DIR . 'templates/partials/field.php';
};
// One pet's card for a step. $n is the 0-based index ('__N__' in the template), $num the number people read.
$paw_pet_card = static function ( array $step, array $fields, string $n, string $num ) use ( $paw_field ): void {
	/* translators: %s: pet number. JavaScript swaps in the pet's name outside the Pets step. */
	$paw_title = sprintf( __( 'Pet %s', 'pawfectly-reservations' ), $num );
	?>
	<fieldset class="paw-pet" data-paw-pet="<?php echo esc_attr( $n ); ?>">
		<legend class="paw-pet__title">
			<span data-paw-pet-name data-paw-pet-default="<?php echo esc_attr( $paw_title ); ?>"><?php echo esc_html( $paw_title ); ?></span>
			<?php if ( 'pet' === $step['id'] && '0' !== $n ) : ?>
				<button type="button" class="paw-link paw-pet__remove" data-paw-remove-pet><?php esc_html_e( 'Remove', 'pawfectly-reservations' ); ?></button>
			<?php endif; ?>
		</legend>
		<?php
		foreach ( $fields as $paw_f ) {
			$paw_name = 'file' === $paw_f['type'] ? 'vaccination_files_' . $n : 'pets[' . $n . '][' . $paw_f['id'] . ']';
			$paw_field( $paw_f, $paw_name, 'paw-p' . $n . '-' . $paw_f['id'] );
		}
		?>
	</fieldset>
	<?php
};
$paw_count = count( $steps );
?>
<div class="paw-app" data-paw-form>
	<div class="paw-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $paw_count ); ?>" aria-valuenow="0" aria-label="<?php esc_attr_e( 'Progress', 'pawfectly-reservations' ); ?>">
		<span class="paw-progress__fill"></span>
		<svg class="paw-progress__paw" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 13c-2.8 0-5.5 2.6-5.5 5 0 1.4 1 2 2.2 2 1.4 0 2.2-.8 3.3-.8s1.9.8 3.3.8c1.2 0 2.2-.6 2.2-2 0-2.4-2.7-5-5.5-5zM6.3 11.6c1.1-.4 1.5-1.9 1-3.3S5.5 6.1 4.4 6.5s-1.5 1.9-1 3.3 1.8 2.2 2.9 1.8zm11.4 0c1.1.4 2.4-.4 2.9-1.8s.1-2.9-1-3.3-2.4.4-2.9 1.8-.1 2.9 1 3.3zM9.6 8.4c1.2-.2 1.9-1.7 1.6-3.3S9.7 2.4 8.5 2.6 6.6 4.3 6.9 5.9s1.5 2.7 2.7 2.5zm4.8 0c1.2.2 2.4-.9 2.7-2.5s-.4-3.1-1.6-3.3-2.4.9-2.7 2.5.4 3.1 1.6 3.3z"/></svg>
	</div>

	<form class="paw-form" novalidate>
		<section class="paw-step paw-welcome" data-step="0" aria-labelledby="paw-welcome-title">
			<div class="paw-welcome__logo">
				<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $settings['business_name'] ); ?>">
			</div>
			<h1 class="paw-welcome__title" id="paw-welcome-title"><?php esc_html_e( 'Pet Boarding Reservation', 'pawfectly-reservations' ); ?></h1>
			<p class="paw-welcome__intro"><?php echo nl2br( esc_html( $settings['intro'] ) ); ?></p>
			<div class="paw-welcome__choice">
				<?php
				foreach ( Fields::welcome() as $paw_f ) {
					$paw_field( $paw_f + array( 'step' => 0 ), $paw_f['id'], 'paw-' . $paw_f['id'] );
				}
				?>
			</div>
			<?php include PAW_DIR . 'templates/partials/service-menu.php'; ?>
			<p class="paw-draft" data-paw-draft hidden>
				<?php esc_html_e( 'We restored your progress.', 'pawfectly-reservations' ); ?>
				<button type="button" class="paw-link" data-paw-restart><?php esc_html_e( 'Start over', 'pawfectly-reservations' ); ?></button>
			</p>
		</section>

		<?php foreach ( $steps as $paw_i => $paw_step ) : ?>
			<?php $paw_n = $paw_i + 1; ?>
			<section class="paw-step" data-step="<?php echo esc_attr( (string) $paw_n ); ?>" id="paw-step-<?php echo esc_attr( (string) $paw_n ); ?>" aria-labelledby="paw-step-<?php echo esc_attr( (string) $paw_n ); ?>-title" hidden>
				<h2 class="paw-step__title" id="paw-step-<?php echo esc_attr( (string) $paw_n ); ?>-title" tabindex="-1"<?php echo isset( $paw_step['title_grooming'] ) ? ' data-title="' . esc_attr( $paw_step['title'] ) . '" data-title-grooming="' . esc_attr( $paw_step['title_grooming'] ) . '"' : ''; ?>><?php echo esc_html( $paw_step['title'] ); ?></h2>

				<?php if ( 'waiver' === $paw_step['id'] ) : ?>
					<p class="paw-step__intro"><?php echo esc_html( $paw_step['intro'] ); ?></p>
					<div class="paw-waiver" data-paw-waiver="boarding"><?php echo wp_kses_post( $settings['waiver'] ); ?></div>
					<div class="paw-waiver" data-paw-waiver="grooming" hidden><?php echo wp_kses_post( $settings['grooming_waiver'] ); ?></div>
				<?php endif; ?>

				<?php if ( 'owner' === $paw_step['id'] ) : ?>
					<?php if ( $account['staff'] ) : ?>
						<p class="paw-account paw-account--in" data-paw-account>
							<?php esc_html_e( 'Signed in as', 'pawfectly-reservations' ); ?> <strong><?php echo esc_html( $account['name'] ); ?></strong> (<?php esc_html_e( 'staff', 'pawfectly-reservations' ); ?>).
							<?php esc_html_e( "You're booking for a customer: enter their details. Nothing is saved to your account.", 'pawfectly-reservations' ); ?>
						</p>
					<?php elseif ( $account['signedIn'] ) : ?>
						<p class="paw-account paw-account--in" data-paw-account>
							<?php esc_html_e( 'Signed in as', 'pawfectly-reservations' ); ?> <strong><?php echo esc_html( $account['name'] ); ?></strong>.
							<a href="<?php echo esc_url( $account['logoutUrl'] ); ?>" data-paw-logout><?php esc_html_e( 'Not you? Log out', 'pawfectly-reservations' ); ?></a>
						</p>
					<?php else : ?>
						<div class="paw-account" data-paw-account>
							<p class="paw-account__lead">
								<?php esc_html_e( 'Already have an account?', 'pawfectly-reservations' ); ?>
								<button type="button" class="paw-link" data-paw-signin-toggle aria-expanded="false" aria-controls="paw-signin"><?php esc_html_e( 'Sign in', 'pawfectly-reservations' ); ?></button>
								<?php esc_html_e( 'to use your saved details.', 'pawfectly-reservations' ); ?>
							</p>
							<div class="paw-signin" id="paw-signin" hidden>
								<div class="paw-signin__row">
									<label class="paw-label" for="paw-login-email"><?php esc_html_e( 'Email', 'pawfectly-reservations' ); ?></label>
									<input class="paw-input" type="email" id="paw-login-email" autocomplete="username">
								</div>
								<div class="paw-signin__row">
									<label class="paw-label" for="paw-login-password"><?php esc_html_e( 'Password', 'pawfectly-reservations' ); ?></label>
									<input class="paw-input" type="password" id="paw-login-password" autocomplete="current-password">
								</div>
								<p class="paw-error" data-paw-signin-error role="alert" hidden></p>
								<div class="paw-signin__actions">
									<button type="button" class="paw-nav__btn" data-paw-signin><?php esc_html_e( 'Sign in', 'pawfectly-reservations' ); ?></button>
									<a class="paw-link" href="<?php echo esc_url( $account['lostUrl'] ); ?>"><?php esc_html_e( 'Forgot your password?', 'pawfectly-reservations' ); ?></a>
								</div>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<div class="paw-card">
					<?php if ( 'stay' === $paw_step['id'] ) : ?>
						<p class="paw-form-error" data-paw-step-error role="alert" hidden></p>
					<?php endif; ?>
					<?php
					// Booking answers before the pet cards, the cards (one per pet, plus a template for more), then the rest.
					$paw_pet_fields = array_filter( $paw_step['fields'], static fn( array $f ): bool => $f['per_pet'] );
					$paw_first_pet  = $paw_pet_fields ? array_key_first( $paw_pet_fields ) : PHP_INT_MAX;
					foreach ( $paw_step['fields'] as $paw_k => $paw_f ) {
						if ( ! $paw_f['per_pet'] && $paw_k < $paw_first_pet ) {
							$paw_field( $paw_f, $paw_f['id'], 'paw-' . $paw_f['id'] );
						}
					}
					?>
					<?php if ( $paw_pet_fields ) : ?>
						<div class="paw-pets" data-paw-pets="<?php echo esc_attr( $paw_step['id'] ); ?>">
							<?php $paw_pet_card( $paw_step, $paw_pet_fields, '0', '1' ); ?>
						</div>
						<template data-paw-pet-template="<?php echo esc_attr( $paw_step['id'] ); ?>"><?php $paw_pet_card( $paw_step, $paw_pet_fields, '__N__', '__NUM__' ); ?></template>
						<?php if ( 'pet' === $paw_step['id'] ) : ?>
							<button type="button" class="paw-add-pet" data-paw-add-pet><?php esc_html_e( '+ Add another pet', 'pawfectly-reservations' ); ?></button>
						<?php endif; ?>
					<?php endif; ?>
					<?php
					foreach ( $paw_step['fields'] as $paw_k => $paw_f ) {
						if ( ! $paw_f['per_pet'] && $paw_k > $paw_first_pet ) {
							$paw_field( $paw_f, $paw_f['id'], 'paw-' . $paw_f['id'] );
						}
					}
					?>

					<?php if ( 'stay' === $paw_step['id'] ) : ?>
						<div class="paw-estimate" data-paw-estimate aria-live="polite" hidden>
							<p class="paw-estimate__title"><?php esc_html_e( 'Estimated total', 'pawfectly-reservations' ); ?></p>
							<ul class="paw-estimate__lines" data-paw-estimate-lines></ul>
							<p class="paw-estimate__total"><span><?php esc_html_e( 'Total', 'pawfectly-reservations' ); ?></span> <strong data-paw-estimate-total></strong></p>
							<p class="paw-estimate__note"><?php esc_html_e( 'Estimate. Payment is taken at Pawfectly; staff confirm the final price.', 'pawfectly-reservations' ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( 'owner' === $paw_step['id'] && ! $account['signedIn'] ) : ?>
						<fieldset class="paw-field paw-field--agree" data-field="create_account">
							<legend class="paw-label"><?php esc_html_e( 'Account (optional)', 'pawfectly-reservations' ); ?></legend>
							<p class="paw-help"><?php esc_html_e( 'Save your details for next time and see your reservations online.', 'pawfectly-reservations' ); ?></p>
							<div class="paw-options">
								<label class="paw-option">
									<input type="checkbox" name="create_account" value="1">
									<span class="paw-option__text"><?php esc_html_e( 'Create an account with these details', 'pawfectly-reservations' ); ?></span>
									<span class="paw-option__mark" aria-hidden="true"></span>
								</label>
							</div>
						</fieldset>
						<div class="paw-field paw-field--password" data-field="account_password" hidden>
							<label class="paw-label" for="paw-account_password"><?php esc_html_e( 'Choose a password', 'pawfectly-reservations' ); ?><span class="paw-req" aria-hidden="true">*</span></label>
							<p class="paw-help" id="paw-account_password-help"><?php esc_html_e( 'At least 8 characters.', 'pawfectly-reservations' ); ?></p>
							<input class="paw-input" type="password" id="paw-account_password" name="account_password" autocomplete="new-password" aria-describedby="paw-account_password-error paw-account_password-help">
							<p class="paw-error" id="paw-account_password-error" role="alert" hidden></p>
						</div>
						<div class="paw-field paw-field--password" data-field="account_password_confirm" hidden>
							<label class="paw-label" for="paw-account_password_confirm"><?php esc_html_e( 'Confirm password', 'pawfectly-reservations' ); ?><span class="paw-req" aria-hidden="true">*</span></label>
							<input class="paw-input" type="password" id="paw-account_password_confirm" name="account_password_confirm" autocomplete="new-password" aria-describedby="paw-account_password_confirm-error">
							<p class="paw-error" id="paw-account_password_confirm-error" role="alert" hidden></p>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $paw_n === $paw_count ) : ?>
					<p class="paw-form-error" data-paw-form-error role="alert" hidden></p>
					<button type="submit" class="paw-submit" data-paw-submit><?php esc_html_e( 'Submit reservation', 'pawfectly-reservations' ); ?></button>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>

		<section class="paw-step paw-done" data-step="done" aria-labelledby="paw-done-title" hidden>
			<div class="paw-done__mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" focusable="false"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
			</div>
			<h2 class="paw-done__title" id="paw-done-title" tabindex="-1"><?php esc_html_e( 'Thank you!', 'pawfectly-reservations' ); ?></h2>
			<p class="paw-done__text">
				<?php esc_html_e( 'Your reservation request has been received. Your reference is', 'pawfectly-reservations' ); ?>
				<strong class="paw-done__ref" data-paw-reference></strong>.
				<?php esc_html_e( "We'll email you once it's confirmed.", 'pawfectly-reservations' ); ?>
			</p>
			<div class="paw-done__estimate" data-paw-estimate-done hidden></div>
		</section>

		<nav class="paw-nav" aria-label="<?php esc_attr_e( 'Form steps', 'pawfectly-reservations' ); ?>" data-paw-nav>
			<button type="button" class="paw-nav__btn paw-nav__btn--back" data-paw-prev hidden>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 11H7.8l5.6-5.6L12 4l-8 8 8 8 1.4-1.4L7.8 13H20z"/></svg>
				<?php esc_html_e( 'Back', 'pawfectly-reservations' ); ?>
			</button>
			<button type="button" class="paw-nav__btn paw-nav__btn--next" data-paw-next>
				<?php esc_html_e( 'Next', 'pawfectly-reservations' ); ?>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 11h12.2l-5.6-5.6L12 4l8 8-8 8-1.4-1.4 5.6-5.6H4z"/></svg>
			</button>
		</nav>

		<div class="paw-hp" aria-hidden="true">
			<label for="paw-website"><?php esc_html_e( 'Leave this field empty', 'pawfectly-reservations' ); ?></label>
			<input type="text" id="paw-website" name="website" tabindex="-1" autocomplete="off">
		</div>
	</form>


	<noscript><p class="paw-noscript"><?php esc_html_e( 'This form needs JavaScript to save your answers and capture your signature. Please turn JavaScript on, or call us to book.', 'pawfectly-reservations' ); ?></p></noscript>
</div>
