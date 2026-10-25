<?php
/**
 * The form template renders every step and field from the field list, escaped.
 * Run: php tests/form-render-test.php
 */
require __DIR__ . '/bootstrap.php';

use Pawfectly\Fields;
use Pawfectly\Settings;
use Pawfectly\Shortcode;

$GLOBALS['paw_options']['admin_email'] = 'owner@example.com';
$settings                  = Settings::get();
$settings['business_name'] = '<script>alert(1)</script>';
$html                      = Shortcode::markup( $settings );

t( 'wrapped in .paw-app', str_contains( $html, 'class="paw-app"' ) && str_contains( $html, 'data-paw-form' ) );
t( 'welcome screen', 1 === substr_count( $html, 'data-step="0"' ) );
for ( $i = 1; $i <= 7; $i++ ) {
	t( "step $i section", 1 === substr_count( $html, "data-step=\"$i\"" ) );
}
t( 'thank-you screen', str_contains( $html, 'data-step="done"' ) );
foreach ( array_keys( Fields::all() ) as $id ) {
	t( "field $id rendered", str_contains( $html, "data-field=\"$id\"" ) );
}
t( 'three pet type radios', 3 === substr_count( $html, 'name="pet_type"' ) );
t( 'two radios per yes/no', 2 === substr_count( $html, 'name="vet_authorization"' ) );
t( 'conditional fields carry show-if', (bool) preg_match( '/data-field="medications_details"[^>]*data-show-if="\{&quot;medications&quot;:&quot;yes&quot;\}"/', $html ) );
t( 'conditional fields start hidden', (bool) preg_match( '/data-field="medications_details"[^>]*hidden/', $html ) );
t( 'vaccination warning present and hidden', (bool) preg_match( '/data-paw-warn="vaccinations_current"[^>]*hidden/', $html ) );
t( 'business name escaped', ! str_contains( $html, '<script>alert(1)</script>' ) && str_contains( $html, '&lt;script&gt;' ) );
t( 'honeypot present and hidden from people', (bool) preg_match( '/class="paw-hp"[^>]*aria-hidden="true"/', $html ) && str_contains( $html, 'name="website"' ) && str_contains( $html, 'tabindex="-1"' ) );
t( 'nav controls', str_contains( $html, 'data-paw-prev' ) && str_contains( $html, 'data-paw-next' ) && str_contains( $html, 'class="paw-progress"' ) );
t( 'submit button', str_contains( $html, 'data-paw-submit' ) );
t( 'noscript notice', str_contains( $html, '<noscript>' ) );
t( 'waiver text printed', str_contains( $html, 'inherent risks' ) );
t( 'US preselected', str_contains( $html, '<option value="US" selected="selected">United States</option>' ) );
t( 'file input accepts only allowed types', str_contains( $html, 'accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"' ) && str_contains( $html, 'name="vaccination_files[]"' ) && str_contains( $html, 'multiple' ) );
t( 'signature canvas and hidden value', str_contains( $html, '<canvas' ) && str_contains( $html, 'name="signature"' ) );
t( 'labels tied to inputs', str_contains( $html, 'for="paw-owner_email"' ) && str_contains( $html, 'id="paw-owner_email"' ) );
t( 'required fields marked', str_contains( $html, 'First Name<span class="paw-req" aria-hidden="true">*</span>' ) );
t( 'optional fields not marked', ! str_contains( $html, 'City/Suburb<span class="paw-req"' ) );
t( 'error slot per field', str_contains( $html, 'id="paw-owner_email-error"' ) );
t( 'placeholder logo without a logo set', str_contains( $html, 'assets/img/paw.svg' ) );
$settings['logo_id'] = 7;
t( 'chosen logo used', str_contains( Shortcode::markup( $settings ), 'https://example.test/logo-7.png' ) );

$cfg = Shortcode::config();
t( 'config: endpoints and nonce', 'https://example.test/wp-json/pawfectly/v1/reservations' === $cfg['restUrl'] && str_ends_with( $cfg['nonceUrl'], 'pawfectly/v1/nonce' ) && 'good-nonce' === $cfg['nonce'] );
t( 'config: today from the site clock', gmdate( 'Y-m-d' ) === $cfg['today'] );
t( 'config: limits', 3 === $cfg['maxFiles'] && \Pawfectly\Uploads::max_file_bytes() === $cfg['maxBytes'] && \Pawfectly\Uploads::max_request_bytes() === $cfg['maxTotal'] && 7 === $cfg['steps'] );
t( 'config: size messages name the real limits', 'Each file must be 10 MB or smaller.' === $cfg['i18n']['fileSize'] && str_contains( $cfg['i18n']['tooLarge'], 'in total' ) );
t( 'config: field rules without labels', isset( $cfg['fields']['owner_email']['type'] ) && ! isset( $cfg['fields']['owner_email']['label'] ) );
t( 'config: messages', 'This field is required.' === $cfg['i18n']['required'] );

done();
