<?php
/**
 * The dashboard's Settings view: general settings and pricing. dashboard.js posts it as JSON to
 * the staff settings route; field names mirror the Settings option's keys.
 *
 * @package Pawfectly
 *
 * @var array $settings Settings::get().
 * @var bool  $lockout  StaffRest::lockout_risk().
 */

use Pawfectly\Fields;
use Pawfectly\Services;

defined( 'ABSPATH' ) || exit;

$paw_logo  = $settings['logo_id'] ? wp_get_attachment_image_url( (int) $settings['logo_id'], 'thumbnail' ) : '';
$paw_rates = array(
	'small' => __( 'Small dog (under 25 lbs)', 'pawfectly-reservations' ),
	'large' => __( 'Large dog (25 lbs & up)', 'pawfectly-reservations' ),
	'other' => __( 'Cat or other pet', 'pawfectly-reservations' ),
);
$paw_saved = (array) $settings['services'];
?>
<form class="paw-dash__settings" data-paw-settings novalidate>
	<?php if ( $lockout ) : ?>
		<p class="paw-dash__warn" role="alert">
			<?php esc_html_e( "Nobody can open the WordPress dashboard: no account uses the site's Administration Email Address and there is no Developer. Add one, or put define( 'PAW_WP_ADMIN_OPEN', true ); in wp-config.php.", 'pawfectly-reservations' ); ?>
		</p>
	<?php endif; ?>

	<section class="paw-dash__card">
		<h3 class="paw-dash__card-title"><?php esc_html_e( 'General', 'pawfectly-reservations' ); ?></h3>
		<div class="paw-dash__fields">
			<label class="paw-dash__field" data-paw-setting="business_name">
				<span><?php esc_html_e( 'Business name', 'pawfectly-reservations' ); ?></span>
				<input type="text" name="business_name" value="<?php echo esc_attr( $settings['business_name'] ); ?>" maxlength="100">
			</label>
			<div class="paw-dash__field paw-dash__logo" data-paw-setting="logo_id">
				<span><?php esc_html_e( 'Logo', 'pawfectly-reservations' ); ?></span>
				<div class="paw-dash__logo-row">
					<img src="<?php echo esc_url( $paw_logo ? $paw_logo : PAW_URL . 'assets/img/paw.svg' ); ?>" alt="" width="56" height="56" data-paw-logo-preview data-placeholder="<?php echo esc_url( PAW_URL . 'assets/img/paw.svg' ); ?>">
					<input type="hidden" name="logo_id" value="<?php echo esc_attr( (string) $settings['logo_id'] ); ?>">
					<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-logo-choose><?php esc_html_e( 'Choose logo', 'pawfectly-reservations' ); ?></button>
					<button type="button" class="paw-dash__link" data-paw-logo-remove><?php esc_html_e( 'Use the paw placeholder', 'pawfectly-reservations' ); ?></button>
				</div>
			</div>
			<label class="paw-dash__field paw-dash__field--wide" data-paw-setting="staff_emails">
				<span><?php esc_html_e( 'Send new reservations to', 'pawfectly-reservations' ); ?></span>
				<input type="text" name="staff_emails" value="<?php echo esc_attr( $settings['staff_emails'] ); ?>">
				<small><?php esc_html_e( 'One or more email addresses, separated by commas.', 'pawfectly-reservations' ); ?></small>
			</label>
			<label class="paw-dash__field paw-dash__field--wide" data-paw-setting="intro">
				<span><?php esc_html_e( 'Welcome text', 'pawfectly-reservations' ); ?></span>
				<textarea name="intro" rows="3"><?php echo esc_textarea( $settings['intro'] ); ?></textarea>
			</label>
			<label class="paw-dash__field paw-dash__field--wide" data-paw-setting="waiver">
				<span><?php esc_html_e( 'Liability waiver', 'pawfectly-reservations' ); ?></span>
				<textarea name="waiver" rows="8"><?php echo esc_textarea( $settings['waiver'] ); ?></textarea>
				<small><?php esc_html_e( 'Shown on the last step. You can use paragraphs, bold, italic and lists.', 'pawfectly-reservations' ); ?></small>
			</label>
			<label class="paw-dash__field paw-dash__field--wide" data-paw-setting="grooming_waiver">
				<span><?php esc_html_e( 'Grooming waiver', 'pawfectly-reservations' ); ?></span>
				<textarea name="grooming_waiver" rows="8"><?php echo esc_textarea( $settings['grooming_waiver'] ); ?></textarea>
				<small><?php esc_html_e( 'Shown instead of the boarding waiver for grooming-only bookings, and after it for boarding + grooming.', 'pawfectly-reservations' ); ?></small>
			</label>
			<label class="paw-dash__check paw-dash__field--wide" data-paw-setting="grooming_needs_vaccines">
				<input type="checkbox" name="grooming_needs_vaccines"<?php echo $settings['grooming_needs_vaccines'] ? ' checked' : ''; ?>>
				<?php esc_html_e( 'Require vaccination records for grooming-only bookings', 'pawfectly-reservations' ); ?>
			</label>
			<label class="paw-dash__field" data-paw-setting="default_country">
				<span><?php esc_html_e( 'Default country', 'pawfectly-reservations' ); ?></span>
				<select name="default_country">
					<?php foreach ( Fields::countries() as $paw_code => $paw_country ) : ?>
						<option value="<?php echo esc_attr( $paw_code ); ?>"<?php selected( $settings['default_country'], $paw_code ); ?>><?php echo esc_html( $paw_country ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<fieldset class="paw-dash__field" data-paw-setting="roles">
				<legend><?php esc_html_e( 'Who can manage reservations', 'pawfectly-reservations' ); ?></legend>
				<?php foreach ( wp_roles()->role_names as $paw_slug => $paw_role ) : ?>
					<label class="paw-dash__check">
						<?php $paw_always = in_array( $paw_slug, array( 'administrator', 'developer' ), true ); ?>
						<input type="checkbox" name="roles[]" value="<?php echo esc_attr( $paw_slug ); ?>"<?php echo $paw_always || in_array( $paw_slug, (array) $settings['roles'], true ) ? ' checked' : ''; ?><?php echo $paw_always ? ' disabled' : ''; ?>>
						<?php echo esc_html( translate_user_role( $paw_role ) ); ?>
					</label>
				<?php endforeach; ?>
				<small><?php esc_html_e( 'Administrators and Developers always can.', 'pawfectly-reservations' ); ?></small>
			</fieldset>
		</div>
	</section>

	<section class="paw-dash__card">
		<h3 class="paw-dash__card-title"><?php esc_html_e( 'Pricing', 'pawfectly-reservations' ); ?></h3>
		<p class="paw-dash__hint"><?php esc_html_e( 'Leave a price blank to show "Price on request". Owners see an estimate; staff confirm the final price.', 'pawfectly-reservations' ); ?></p>
		<div class="paw-dash__fields">
			<label class="paw-dash__field paw-dash__field--narrow" data-paw-setting="currency">
				<span><?php esc_html_e( 'Currency symbol', 'pawfectly-reservations' ); ?></span>
				<input type="text" name="currency" value="<?php echo esc_attr( $settings['currency'] ); ?>" maxlength="3">
			</label>
		</div>
		<h4 class="paw-dash__subtitle"><?php esc_html_e( 'Boarding, per night', 'pawfectly-reservations' ); ?></h4>
		<div class="paw-dash__fields paw-dash__fields--3">
			<?php foreach ( $paw_rates as $paw_key => $paw_label ) : ?>
				<label class="paw-dash__field" data-paw-setting="boarding_rates.<?php echo esc_attr( $paw_key ); ?>">
					<span><?php echo esc_html( $paw_label ); ?></span>
					<input type="text" inputmode="decimal" name="boarding_rates[<?php echo esc_attr( $paw_key ); ?>]" value="<?php echo esc_attr( (string) $settings['boarding_rates'][ $paw_key ] ); ?>" placeholder="<?php esc_attr_e( 'On request', 'pawfectly-reservations' ); ?>">
				</label>
			<?php endforeach; ?>
		</div>

		<h4 class="paw-dash__subtitle"><?php esc_html_e( 'Groomers', 'pawfectly-reservations' ); ?></h4>
		<div class="paw-dash__fields">
			<label class="paw-dash__field paw-dash__field--wide" data-paw-setting="groomers">
				<span><?php esc_html_e( 'One name per line', 'pawfectly-reservations' ); ?></span>
				<textarea name="groomers" rows="4"><?php echo esc_textarea( implode( "\n", (array) $settings['groomers'] ) ); ?></textarea>
				<small><?php esc_html_e( 'Owners can ask for one of these groomers, or choose "Any / Flexible".', 'pawfectly-reservations' ); ?></small>
			</label>
		</div>

		<h4 class="paw-dash__subtitle"><?php esc_html_e( 'Grooming services', 'pawfectly-reservations' ); ?></h4>
		<div class="paw-dash__services">
			<?php foreach ( Services::defaults() as $paw_id => $paw_default ) : ?>
				<?php
				$paw_s = array_merge( $paw_default, is_array( $paw_saved[ $paw_id ] ?? null ) ? $paw_saved[ $paw_id ] : array() );
				$paw_n = 'services[' . $paw_id . ']';
				?>
				<fieldset class="paw-dash__service" data-paw-service="<?php echo esc_attr( $paw_id ); ?>">
					<legend class="screen-reader-text"><?php echo esc_html( $paw_s['name'] ); ?></legend>
					<label class="paw-dash__switch">
						<input type="checkbox" name="<?php echo esc_attr( $paw_n ); ?>[enabled]"<?php echo $paw_s['enabled'] ? ' checked' : ''; ?>>
						<span><?php esc_html_e( 'Offered', 'pawfectly-reservations' ); ?></span>
					</label>
					<div class="paw-dash__fields paw-dash__fields--service">
						<label class="paw-dash__field" data-paw-setting="services.<?php echo esc_attr( $paw_id ); ?>.name">
							<span><?php esc_html_e( 'Name', 'pawfectly-reservations' ); ?></span>
							<input type="text" name="<?php echo esc_attr( $paw_n ); ?>[name]" value="<?php echo esc_attr( $paw_s['name'] ); ?>" maxlength="60">
						</label>
						<label class="paw-dash__field" data-paw-setting="services.<?php echo esc_attr( $paw_id ); ?>.time">
							<span><?php esc_html_e( 'Time', 'pawfectly-reservations' ); ?></span>
							<input type="text" name="<?php echo esc_attr( $paw_n ); ?>[time]" value="<?php echo esc_attr( $paw_s['time'] ); ?>" maxlength="60">
						</label>
						<label class="paw-dash__field" data-paw-setting="services.<?php echo esc_attr( $paw_id ); ?>.small">
							<span><?php echo esc_html( in_array( $paw_id, Services::MONTHLY, true ) ? __( 'Small dog, per month', 'pawfectly-reservations' ) : __( 'Small dog', 'pawfectly-reservations' ) ); ?></span>
							<input type="text" inputmode="decimal" name="<?php echo esc_attr( $paw_n ); ?>[small]" value="<?php echo esc_attr( (string) $paw_s['small'] ); ?>" placeholder="<?php esc_attr_e( 'On request', 'pawfectly-reservations' ); ?>">
						</label>
						<label class="paw-dash__field" data-paw-setting="services.<?php echo esc_attr( $paw_id ); ?>.large">
							<span><?php echo esc_html( in_array( $paw_id, Services::MONTHLY, true ) ? __( 'Large dog, per month', 'pawfectly-reservations' ) : __( 'Large dog', 'pawfectly-reservations' ) ); ?></span>
							<input type="text" inputmode="decimal" name="<?php echo esc_attr( $paw_n ); ?>[large]" value="<?php echo esc_attr( (string) $paw_s['large'] ); ?>" placeholder="<?php esc_attr_e( 'On request', 'pawfectly-reservations' ); ?>">
						</label>
						<label class="paw-dash__field" data-paw-setting="services.<?php echo esc_attr( $paw_id ); ?>.cat">
							<span><?php echo esc_html( in_array( $paw_id, Services::MONTHLY, true ) ? __( 'Cat, per month', 'pawfectly-reservations' ) : __( 'Cat', 'pawfectly-reservations' ) ); ?></span>
							<input type="text" inputmode="decimal" name="<?php echo esc_attr( $paw_n ); ?>[cat]" value="<?php echo esc_attr( (string) $paw_s['cat'] ); ?>" placeholder="<?php esc_attr_e( 'On request', 'pawfectly-reservations' ); ?>">
						</label>
						<label class="paw-dash__field paw-dash__field--wide" data-paw-setting="services.<?php echo esc_attr( $paw_id ); ?>.included">
							<span><?php esc_html_e( "What's included (one per line)", 'pawfectly-reservations' ); ?></span>
							<textarea name="<?php echo esc_attr( $paw_n ); ?>[included]" rows="4"><?php echo esc_textarea( $paw_s['included'] ); ?></textarea>
						</label>
					</div>
				</fieldset>
			<?php endforeach; ?>
		</div>
	</section>

	<div class="paw-dash__savebar">
		<p class="paw-dash__savemsg" data-paw-settings-msg role="status" hidden></p>
		<button type="submit" class="paw-dash__btn"><?php esc_html_e( 'Save settings', 'pawfectly-reservations' ); ?></button>
	</div>
</form>

<section class="paw-dash__card paw-dash__ev-cats" data-paw-ev-cats>
	<h3 class="paw-dash__card-title"><?php esc_html_e( 'Event categories', 'pawfectly-reservations' ); ?></h3>
	<p class="paw-dash__hint"><?php esc_html_e( 'Each category has a colour on the event calendar. Removing one keeps its events, without a category.', 'pawfectly-reservations' ); ?></p>
	<ul class="paw-dash__ev-catlist" data-paw-ev-catlist></ul>
	<template data-paw-ev-cat-template>
		<li class="paw-dash__ev-cat" data-paw-ev-cat>
			<label class="paw-dash__field" data-paw-ev-key="name">
				<span><?php esc_html_e( 'Name', 'pawfectly-reservations' ); ?></span>
				<input type="text" data-paw-ev-cat-name maxlength="40" placeholder="<?php esc_attr_e( 'Holiday party', 'pawfectly-reservations' ); ?>">
			</label>
			<fieldset class="paw-dash__ev-swatches">
				<legend class="screen-reader-text"><?php esc_html_e( 'Colour', 'pawfectly-reservations' ); ?></legend>
				<?php foreach ( \Pawfectly\Events::PALETTE as $paw_color ) : ?>
					<label class="paw-dash__ev-swatch" style="--sw:<?php echo esc_attr( $paw_color ); ?>"><input type="radio" value="<?php echo esc_attr( $paw_color ); ?>" data-paw-ev-cat-color><span class="screen-reader-text"><?php echo esc_html( $paw_color ); ?></span></label>
				<?php endforeach; ?>
			</fieldset>
			<button type="button" class="paw-dash__ev-tool paw-dash__ev-tool--remove" data-paw-ev-cat-remove aria-label="<?php esc_attr_e( 'Remove category', 'pawfectly-reservations' ); ?>">✕</button>
		</li>
	</template>
	<div class="paw-dash__ev-cat-actions">
		<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-ev-cat-add><?php esc_html_e( '+ Add category', 'pawfectly-reservations' ); ?></button>
		<button type="button" class="paw-dash__btn" data-paw-ev-cat-save><?php esc_html_e( 'Save categories', 'pawfectly-reservations' ); ?></button>
		<span class="paw-dash__savemsg" data-paw-ev-cat-msg role="status" hidden></span>
	</div>
</section>
