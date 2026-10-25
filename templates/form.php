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

defined( 'ABSPATH' ) || exit;

$paw_label = static function ( array $field ): string {
	$req = $field['required'] ? '<span class="paw-req" aria-hidden="true">*</span>' : '';
	return esc_html( $field['label'] ) . $req;
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
			<p class="paw-draft" data-paw-draft hidden>
				<?php esc_html_e( 'We restored your progress.', 'pawfectly-reservations' ); ?>
				<button type="button" class="paw-link" data-paw-restart><?php esc_html_e( 'Start over', 'pawfectly-reservations' ); ?></button>
			</p>
		</section>

		<?php foreach ( $steps as $paw_i => $paw_step ) : ?>
			<?php $paw_n = $paw_i + 1; ?>
			<section class="paw-step" data-step="<?php echo esc_attr( (string) $paw_n ); ?>" id="paw-step-<?php echo esc_attr( (string) $paw_n ); ?>" aria-labelledby="paw-step-<?php echo esc_attr( (string) $paw_n ); ?>-title" hidden>
				<h2 class="paw-step__title" id="paw-step-<?php echo esc_attr( (string) $paw_n ); ?>-title" tabindex="-1"><?php echo esc_html( $paw_step['title'] ); ?></h2>

				<?php if ( 'waiver' === $paw_step['id'] ) : ?>
					<p class="paw-step__intro"><?php echo esc_html( $paw_step['intro'] ); ?></p>
					<div class="paw-waiver"><?php echo wp_kses_post( $settings['waiver'] ); ?></div>
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
					<?php foreach ( $paw_step['fields'] as $paw_f ) : ?>
						<?php
						$paw_id    = $paw_f['id'];
						$paw_dom   = 'paw-' . $paw_id;
						$paw_attrs = ' data-field="' . esc_attr( $paw_id ) . '"';
						if ( $paw_f['show_if'] ) {
							$paw_attrs .= ' data-show-if="' . esc_attr( wp_json_encode( $paw_f['show_if'] ) ) . '" hidden';
						}
						$paw_class = 'paw-field paw-field--' . $paw_f['type'] . ( $paw_f['half'] ? ' paw-field--half' : '' );
						$paw_desc  = $paw_dom . '-error' . ( $paw_f['help'] ? ' ' . $paw_dom . '-help' : '' );
						$paw_aria  = ' aria-describedby="' . esc_attr( $paw_desc ) . '"' . ( $paw_f['required'] ? ' aria-required="true"' : '' );
						$paw_group = in_array( $paw_f['type'], array( 'choice', 'yesno', 'agree' ), true );
						?>
						<?php if ( ! empty( $paw_f['group'] ) ) : ?>
							<p class="paw-group-title"><?php echo esc_html( $paw_f['group'] ); ?><span class="paw-req" aria-hidden="true">*</span></p>
						<?php endif; ?>

						<?php if ( $paw_group ) : ?>
						<fieldset class="<?php echo esc_attr( $paw_class ); ?>"<?php echo $paw_attrs; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above. ?>>
							<legend class="paw-label"><?php echo $paw_label( $paw_f ); // phpcs:ignore WordPress.Security.EscapeOutput ?></legend>
						<?php else : ?>
						<div class="<?php echo esc_attr( $paw_class ); ?>"<?php echo $paw_attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
							<?php if ( 'file' === $paw_f['type'] || 'signature' === $paw_f['type'] ) : ?>
								<p class="paw-label" id="<?php echo esc_attr( $paw_dom ); ?>-label"><?php echo $paw_label( $paw_f ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
							<?php else : ?>
								<label class="paw-label" for="<?php echo esc_attr( $paw_dom ); ?>"><?php echo $paw_label( $paw_f ); // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
							<?php endif; ?>
						<?php endif; ?>

							<?php if ( $paw_f['help'] ) : ?>
								<p class="paw-help" id="<?php echo esc_attr( $paw_dom ); ?>-help"><?php echo esc_html( $paw_f['help'] ); ?></p>
							<?php endif; ?>

							<?php switch ( $paw_f['type'] ) : ?>
<?php case 'text': case 'email': case 'tel': ?>
								<?php
								$paw_auto = array(
									'owner_first_name' => 'given-name',
									'owner_last_name'  => 'family-name',
									'owner_email'      => 'email',
									'owner_phone'      => 'tel',
									'address_street'   => 'address-line1',
									'address_city'     => 'address-level2',
									'address_state'    => 'address-level1',
									'address_postcode' => 'postal-code',
								)[ $paw_id ] ?? 'off';
								?>
								<input class="paw-input" type="<?php echo esc_attr( $paw_f['type'] ); ?>" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_id ); ?>" maxlength="<?php echo esc_attr( (string) $paw_f['max'] ); ?>" autocomplete="<?php echo esc_attr( $paw_auto ); ?>" placeholder="<?php echo esc_attr( $paw_f['placeholder'] ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
								<?php break; ?>
<?php case 'textarea': ?>
								<textarea class="paw-textarea" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_id ); ?>" rows="4" maxlength="<?php echo esc_attr( (string) $paw_f['max'] ); ?>" placeholder="<?php echo esc_attr( $paw_f['placeholder'] ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>></textarea>
								<?php break; ?>
<?php case 'date': ?>
								<div class="paw-date">
									<input class="paw-input" type="text" inputmode="none" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_id ); ?>" data-paw-date placeholder="<?php esc_attr_e( 'MM/DD/YYYY', 'pawfectly-reservations' ); ?>"<?php echo $paw_f['readonly'] ? ' readonly data-paw-today' : ''; ?><?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
									<svg class="paw-date__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 2v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm-2 8h14v10H5V10z"/></svg>
								</div>
								<?php break; ?>
<?php case 'country': ?>
								<select class="paw-select" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_id ); ?>" autocomplete="country"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
									<?php foreach ( $countries as $paw_code => $paw_country ) : ?>
										<option value="<?php echo esc_attr( $paw_code ); ?>"<?php selected( $settings['default_country'], $paw_code ); ?>><?php echo esc_html( $paw_country ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php break; ?>
<?php case 'choice': case 'agree': ?>
								<div class="paw-options">
									<?php foreach ( $paw_f['options'] as $paw_value => $paw_text ) : ?>
										<label class="paw-option">
											<input type="<?php echo 'agree' === $paw_f['type'] ? 'checkbox' : 'radio'; ?>" name="<?php echo esc_attr( $paw_id ); ?>" value="<?php echo esc_attr( (string) $paw_value ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
											<span class="paw-option__text"><?php echo esc_html( $paw_text ); ?></span>
											<span class="paw-option__mark" aria-hidden="true"></span>
										</label>
									<?php endforeach; ?>
								</div>
								<?php break; ?>
<?php case 'yesno': ?>
								<div class="paw-yesno">
									<?php foreach ( $paw_f['options'] as $paw_value => $paw_text ) : ?>
										<label class="paw-yesno__opt">
											<input type="radio" name="<?php echo esc_attr( $paw_id ); ?>" value="<?php echo esc_attr( (string) $paw_value ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
											<span><?php echo esc_html( $paw_text ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<?php foreach ( $paw_f['warn'] as $paw_value => $paw_message ) : ?>
									<p class="paw-alert" role="alert" data-paw-warn="<?php echo esc_attr( $paw_id ); ?>" data-paw-warn-value="<?php echo esc_attr( (string) $paw_value ); ?>" hidden><?php echo esc_html( $paw_message ); ?></p>
								<?php endforeach; ?>
								<?php break; ?>
<?php case 'file': ?>
								<div class="paw-file">
									<input class="paw-file__input" type="file" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_id ); ?>[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-labelledby="<?php echo esc_attr( $paw_dom ); ?>-label"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
									<label class="paw-file__button" for="<?php echo esc_attr( $paw_dom ); ?>"><?php esc_html_e( 'Choose files', 'pawfectly-reservations' ); ?></label>
									<ul class="paw-file__list" data-paw-file-list></ul>
								</div>
								<?php break; ?>
<?php case 'signature': ?>
								<div class="paw-sig">
									<canvas class="paw-sig__canvas" data-paw-signature role="img" aria-labelledby="<?php echo esc_attr( $paw_dom ); ?>-label"></canvas>
									<span class="paw-sig__hint" aria-hidden="true"><?php esc_html_e( 'Sign here', 'pawfectly-reservations' ); ?></span>
									<input type="hidden" name="<?php echo esc_attr( $paw_id ); ?>" id="<?php echo esc_attr( $paw_dom ); ?>">
									<button type="button" class="paw-link paw-sig__clear" data-paw-signature-clear><?php esc_html_e( 'Clear signature', 'pawfectly-reservations' ); ?></button>
								</div>
								<?php break; ?>
<?php endswitch; ?>

							<p class="paw-error" id="<?php echo esc_attr( $paw_dom ); ?>-error" role="alert" hidden></p>
						<?php echo $paw_group ? '</fieldset>' : '</div>'; ?>
					<?php endforeach; ?>

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
