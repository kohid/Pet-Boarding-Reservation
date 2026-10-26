<?php
/**
 * One form field: label, help, control and error slot. Used for booking answers and, inside each
 * pet card, for per-pet answers (names like pets[0][pet_name]).
 *
 * @package Pawfectly
 *
 * @var array  $paw_f     The field (Fields::all() entry).
 * @var string $paw_name  Input name.
 * @var string $paw_dom   Id prefix for the control, its help and its error.
 * @var array  $settings  Settings::get().
 * @var array  $countries Fields::countries().
 * @var callable $paw_label Label with the required mark.
 */

use Pawfectly\Services;

defined( 'ABSPATH' ) || exit;
?>
	<?php
	$paw_id    = $paw_f['id'];
	$paw_attrs = ' data-field="' . esc_attr( $paw_id ) . '"';
	if ( $paw_f['show_if'] ) {
		$paw_attrs .= ' data-show-if="' . esc_attr( wp_json_encode( $paw_f['show_if'] ) ) . '" hidden';
	}
	$paw_class = 'paw-field paw-field--' . $paw_f['type'] . ( $paw_f['half'] ? ' paw-field--half' : '' );
	$paw_desc  = $paw_dom . '-error' . ( $paw_f['help'] ? ' ' . $paw_dom . '-help' : '' );
	$paw_aria  = ' aria-describedby="' . esc_attr( $paw_desc ) . '"' . ( $paw_f['required'] ? ' aria-required="true"' : '' );
	$paw_group = in_array( $paw_f['type'], array( 'choice', 'yesno', 'agree', 'service' ), true ) && ! $paw_f['dropdown'];
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
			<input class="paw-input" type="<?php echo esc_attr( $paw_f['type'] ); ?>" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_name ); ?>" maxlength="<?php echo esc_attr( (string) $paw_f['max'] ); ?>" autocomplete="<?php echo esc_attr( $paw_auto ); ?>" placeholder="<?php echo esc_attr( $paw_f['placeholder'] ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php break; ?>
<?php case 'textarea': ?>
			<textarea class="paw-textarea" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_name ); ?>" rows="4" maxlength="<?php echo esc_attr( (string) $paw_f['max'] ); ?>" placeholder="<?php echo esc_attr( $paw_f['placeholder'] ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>></textarea>
			<?php break; ?>
<?php case 'date': ?>
			<div class="paw-date">
				<input class="paw-input" type="text" inputmode="none" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_name ); ?>" data-paw-date placeholder="<?php esc_attr_e( 'MM/DD/YYYY', 'pawfectly-reservations' ); ?>"<?php echo $paw_f['readonly'] ? ' readonly data-paw-today' : ''; ?><?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<svg class="paw-date__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 2v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm-2 8h14v10H5V10z"/></svg>
			</div>
			<?php break; ?>
<?php case 'country': ?>
			<select class="paw-select" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_name ); ?>" autocomplete="country"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php foreach ( $countries as $paw_code => $paw_country ) : ?>
					<option value="<?php echo esc_attr( $paw_code ); ?>"<?php selected( $settings['default_country'], $paw_code ); ?>><?php echo esc_html( $paw_country ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php break; ?>
<?php case 'choice': case 'agree': ?>
			<?php if ( $paw_f['dropdown'] ) : ?>
			<select class="paw-select" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_name ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php if ( '' === $paw_f['default'] ) : ?>
				<option value=""><?php esc_html_e( 'Choose…', 'pawfectly-reservations' ); ?></option>
				<?php endif; ?>
				<?php foreach ( $paw_f['options'] as $paw_value => $paw_text ) : ?>
				<option value="<?php echo esc_attr( (string) $paw_value ); ?>"<?php echo (string) $paw_value === $paw_f['default'] ? ' selected' : ''; ?>><?php echo esc_html( $paw_text ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php break; ?>
			<?php endif; ?>
			<div class="paw-options">
				<?php foreach ( $paw_f['options'] as $paw_value => $paw_text ) : ?>
					<label class="paw-option"<?php echo in_array( (string) $paw_value, $paw_f['dog_only'], true ) ? ' data-paw-dog-only' : ''; ?>>
						<input type="<?php echo 'agree' === $paw_f['type'] ? 'checkbox' : 'radio'; ?>" name="<?php echo esc_attr( $paw_name ); ?>" value="<?php echo esc_attr( (string) $paw_value ); ?>"<?php echo '' !== $paw_f['default'] && (string) $paw_value === $paw_f['default'] ? ' checked' : ''; ?><?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
						<span class="paw-option__text"><?php echo esc_html( $paw_text ); ?></span>
						<span class="paw-option__mark" aria-hidden="true"></span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php break; ?>
<?php case 'service': ?>
			<div class="paw-svc-list">
				<?php foreach ( Services::enabled() as $paw_svc ) : ?>
					<?php
					$paw_prices = Services::price_range( $paw_svc );
					$paw_range  = '' !== $paw_prices ? $paw_prices : __( 'Price on request', 'pawfectly-reservations' );
					?>
					<div class="paw-svc" data-paw-svc="<?php echo esc_attr( $paw_svc['id'] ); ?>">
						<label class="paw-svc__pick">
							<input type="radio" name="<?php echo esc_attr( $paw_name ); ?>" value="<?php echo esc_attr( $paw_svc['id'] ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
							<span class="paw-option__mark" aria-hidden="true"></span>
							<span class="paw-svc__main">
								<span class="paw-svc__name"><?php echo esc_html( $paw_svc['name'] ); ?></span>
								<?php if ( $paw_svc['monthly'] ) : ?>
									<span class="paw-svc__tag"><?php esc_html_e( 'Monthly', 'pawfectly-reservations' ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $paw_svc['time'] ) : ?>
									<span class="paw-svc__time"><?php echo esc_html( $paw_svc['time'] ); ?></span>
								<?php endif; ?>
							</span>
							<span class="paw-svc__price<?php echo '' !== $paw_prices ? '' : ' is-request'; ?>" data-paw-price="<?php echo esc_attr( $paw_svc['id'] ); ?>"><?php echo esc_html( $paw_range ); ?><?php echo $paw_svc['monthly'] && '' !== $paw_prices ? '<small>' . esc_html__( '/ month', 'pawfectly-reservations' ) . '</small>' : ''; ?></span>
						</label>
						<?php if ( $paw_svc['included'] ) : ?>
							<details class="paw-svc__more">
								<summary><?php esc_html_e( "What's included", 'pawfectly-reservations' ); ?></summary>
								<ul>
									<?php foreach ( $paw_svc['included'] as $paw_item ) : ?>
										<li><?php echo esc_html( $paw_item ); ?></li>
									<?php endforeach; ?>
								</ul>
							</details>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				<?php if ( $paw_f['per_pet'] ) : ?>
					<div class="paw-svc paw-svc--none" data-paw-svc="none" data-paw-both-only hidden>
						<label class="paw-svc__pick">
							<input type="radio" name="<?php echo esc_attr( $paw_name ); ?>" value="none"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
							<span class="paw-option__mark" aria-hidden="true"></span>
							<span class="paw-svc__main"><span class="paw-svc__name"><?php esc_html_e( 'No grooming for this pet', 'pawfectly-reservations' ); ?></span></span>
						</label>
					</div>
				<?php endif; ?>
			</div>
			<?php break; ?>
<?php case 'yesno': ?>
			<div class="paw-yesno">
				<?php foreach ( $paw_f['options'] as $paw_value => $paw_text ) : ?>
					<label class="paw-yesno__opt">
						<input type="radio" name="<?php echo esc_attr( $paw_name ); ?>" value="<?php echo esc_attr( (string) $paw_value ); ?>"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
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
				<input class="paw-file__input" type="file" id="<?php echo esc_attr( $paw_dom ); ?>" name="<?php echo esc_attr( $paw_name ); ?>[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-labelledby="<?php echo esc_attr( $paw_dom ); ?>-label"<?php echo $paw_aria; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<label class="paw-file__button" for="<?php echo esc_attr( $paw_dom ); ?>"><?php esc_html_e( 'Choose files', 'pawfectly-reservations' ); ?></label>
				<ul class="paw-file__list" data-paw-file-list></ul>
			</div>
			<?php break; ?>
<?php case 'signature': ?>
			<div class="paw-sig">
				<canvas class="paw-sig__canvas" data-paw-signature role="img" aria-labelledby="<?php echo esc_attr( $paw_dom ); ?>-label"></canvas>
				<span class="paw-sig__hint" aria-hidden="true"><?php esc_html_e( 'Sign here', 'pawfectly-reservations' ); ?></span>
				<input type="hidden" name="<?php echo esc_attr( $paw_name ); ?>" id="<?php echo esc_attr( $paw_dom ); ?>">
				<button type="button" class="paw-link paw-sig__clear" data-paw-signature-clear><?php esc_html_e( 'Clear signature', 'pawfectly-reservations' ); ?></button>
			</div>
			<?php break; ?>
<?php endswitch; ?>

		<p class="paw-error" id="<?php echo esc_attr( $paw_dom ); ?>-error" role="alert" hidden></p>
	<?php echo $paw_group ? '</fieldset>' : '</div>'; ?>
