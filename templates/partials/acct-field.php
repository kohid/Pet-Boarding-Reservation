<?php
/**
 * One labelled input for the account forms, with an error slot account.js fills.
 * Usage: $paw_field = include PAW_DIR . 'templates/partials/acct-field.php';
 *
 * @package Pawfectly
 */

defined( 'ABSPATH' ) || exit;

return static function ( string $name, string $label, string $type, string $value = '', string $auto = '', string $form = '' ): void {
	$id = 'paw-acct-' . ( $form ? $form . '-' : '' ) . $name;
	?>
	<div class="paw-acct__field" data-paw-field="<?php echo esc_attr( $name ); ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $auto ? ' autocomplete="' . esc_attr( $auto ) . '"' : ''; ?>>
		<p class="paw-acct__error" role="alert" hidden></p>
	</div>
	<?php
};
