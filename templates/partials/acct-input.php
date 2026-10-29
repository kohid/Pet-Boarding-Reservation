<?php
/**
 * One booking-form field (Fields) as an account-form control: text, phone, text area, or a
 * dropdown for choices and yes/no. Same error slot as acct-field.php, so account.js shows errors.
 * Usage: $paw_input = include PAW_DIR . 'templates/partials/acct-input.php';
 *
 * @package Pawfectly
 */

use Pawfectly\Fields;

defined( 'ABSPATH' ) || exit;

return static function ( array $f, string $value ): void {
	$id      = 'paw-acct-' . $f['id'];
	$options = 'country' === $f['type'] ? Fields::countries() : (array) $f['options'];
	?>
	<div class="paw-acct__field<?php echo 'textarea' === $f['type'] ? ' paw-acct__field--wide' : ''; ?>" data-paw-field="<?php echo esc_attr( $f['id'] ); ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
		<?php if ( 'textarea' === $f['type'] ) : ?>
			<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $f['id'] ); ?>" rows="3" maxlength="<?php echo esc_attr( (string) $f['max'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		<?php elseif ( $options ) : ?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $f['id'] ); ?>">
				<option value="">—</option>
				<?php foreach ( $options as $paw_key => $paw_label ) : ?>
					<option value="<?php echo esc_attr( (string) $paw_key ); ?>"<?php selected( $value, (string) $paw_key ); ?>><?php echo esc_html( (string) $paw_label ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<input type="<?php echo 'tel' === $f['type'] ? 'tel' : 'text'; ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $f['id'] ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo esc_attr( (string) $f['max'] ); ?>"<?php echo '' !== (string) $f['placeholder'] ? ' placeholder="' . esc_attr( (string) $f['placeholder'] ) . '"' : ''; ?>>
		<?php endif; ?>
		<p class="paw-acct__error" role="alert" hidden></p>
	</div>
	<?php
};
