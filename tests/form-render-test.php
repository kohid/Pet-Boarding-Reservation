<?php
/**
 * The form template renders every step and field from the field list, escaped.
 * Run: php tests/form-render-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/wp-db-stubs.php';
require __DIR__ . '/user-stubs.php';
function get_permalink( $id = 0 ) { return 'https://example.test/book/'; }
function home_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }

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
t( 'three pet type radios for pet 1', 3 === substr_count( $html, 'name="pets[0][pet_type]"' ) );
t( 'two radios per yes/no', 2 === substr_count( $html, 'name="vet_authorization"' ) );
t( 'conditional fields carry show-if', (bool) preg_match( '/data-field="medications_details"[^>]*data-show-if="\{&quot;medications&quot;:&quot;yes&quot;\}"/', $html ) );
t( 'conditional fields start hidden', (bool) preg_match( '/data-field="medications_details"[^>]*hidden/', $html ) );
t( 'vaccination warning present and hidden', (bool) preg_match( '/data-paw-warn="vaccinations_current"[^>]*hidden/', $html ) );
t( 'business name escaped', ! str_contains( $html, '<script>alert(1)</script>' ) && str_contains( $html, '&lt;script&gt;' ) );
t( 'honeypot present and hidden from people', (bool) preg_match( '/class="paw-hp"[^>]*aria-hidden="true"/', $html ) && str_contains( $html, 'name="website"' ) && str_contains( $html, 'tabindex="-1"' ) );
t( 'progress bar comes before the steps', strpos( $html, 'class="paw-progress"' ) < strpos( $html, 'data-step="0"' ) );
t( 'back/next come after the steps', strpos( $html, 'class="paw-nav"' ) > strpos( $html, 'data-step="done"' ) );
t( 'back and next have visible labels', (bool) preg_match( '/data-paw-prev[^>]*>.*Back/s', $html ) && (bool) preg_match( '/data-paw-next[^>]*>.*Next/s', $html ) );
t( 'nav controls', str_contains( $html, 'data-paw-prev' ) && str_contains( $html, 'data-paw-next' ) && str_contains( $html, 'class="paw-progress"' ) );
t( 'submit button', str_contains( $html, 'data-paw-submit' ) );
t( 'noscript notice', str_contains( $html, '<noscript>' ) );
t( 'waiver text printed', str_contains( $html, 'inherent risks' ) );
t( 'US preselected', str_contains( $html, '<option value="US" selected="selected">United States</option>' ) );
t( 'file input accepts only allowed types', str_contains( $html, 'accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"' ) && str_contains( $html, 'name="vaccination_files_0[]"' ) && str_contains( $html, 'multiple' ) );
t( 'signature canvas and hidden value', str_contains( $html, '<canvas' ) && str_contains( $html, 'name="signature"' ) );
t( 'labels tied to inputs', str_contains( $html, 'for="paw-owner_email"' ) && str_contains( $html, 'id="paw-owner_email"' ) );
t( 'required fields marked', str_contains( $html, 'First Name<span class="paw-req" aria-hidden="true">*</span>' ) );
t( 'optional fields not marked', ! str_contains( $html, 'City/Suburb<span class="paw-req"' ) );
t( 'error slot per field', str_contains( $html, 'id="paw-owner_email-error"' ) );
t( 'placeholder logo without a logo set', str_contains( $html, 'assets/img/paw.svg' ) );
$settings['logo_id'] = 7;
t( 'chosen logo used', str_contains( Shortcode::markup( $settings ), 'https://example.test/logo-7.png' ) );

t( 'signed out: sign-in link and create-account box', str_contains( $html, 'data-paw-signin-toggle' ) && str_contains( $html, 'name="create_account"' ) && str_contains( $html, 'name="account_password"' ) && str_contains( $html, 'autocomplete="new-password"' ) );
t( 'account block sits in the owner step', strpos( $html, 'data-paw-account' ) > strpos( $html, 'data-step="1"' ) && strpos( $html, 'data-paw-account' ) < strpos( $html, 'data-step="2"' ) );
t( 'lost password link', str_contains( $html, 'action=lostpassword' ) );
$me = paw_test_user( 'jay@example.com', 'some pass1', array( 'paw_customer' ) );
$me->display_name = 'Jay <Miralles>';
wp_set_current_user( $me->ID );
$in = Shortcode::markup( $settings );
t( 'signed in: "signed in as" with escaped name, no account prompts', str_contains( $in, 'Jay &lt;Miralles&gt;' ) && ! str_contains( $in, 'name="create_account"' ) && ! str_contains( $in, 'data-paw-signin-toggle' ) && str_contains( $in, 'action=logout' ) );
$cfg_in = Shortcode::config();
t( 'signed in: config carries the prefill', true === $cfg_in['account']['signedIn'] && 'jay@example.com' === $cfg_in['account']['prefill']['owner_email'] );
$front = paw_test_user( 'front@example.com', 'some pass1', array( 'paw_staff' ), array( 'pawfectly_manage_reservations' => true ) );
$front->display_name = 'Dana Desk';
wp_set_current_user( $front->ID );
$staff_html = Shortcode::markup( $settings );
$staff_cfg  = Shortcode::config();
t( 'staff: no prefill from the last customer they entered', array() === $staff_cfg['account']['prefill'] && true === $staff_cfg['account']['staff'] );
t( 'staff: told they are booking for a customer', str_contains( $staff_html, 'booking for a customer' ) && ! str_contains( $staff_html, 'name="create_account"' ) );
wp_set_current_user( 0 );
$cfg = Shortcode::config();
t( 'config: endpoints and nonce', 'https://example.test/wp-json/pawfectly/v1/reservations' === $cfg['restUrl'] && str_ends_with( $cfg['nonceUrl'], 'pawfectly/v1/nonce' ) && 'good-nonce' === $cfg['nonce'] );
t( 'config: today from the site clock', gmdate( 'Y-m-d' ) === $cfg['today'] );
t( 'config: limits', 3 === $cfg['maxFiles'] && \Pawfectly\Uploads::max_file_bytes() === $cfg['maxBytes'] && \Pawfectly\Uploads::max_request_bytes() === $cfg['maxTotal'] && 7 === $cfg['steps'] );
t( 'config: size messages name the real limits', 'Each file must be 10 MB or smaller.' === $cfg['i18n']['fileSize'] && str_contains( $cfg['i18n']['tooLarge'], 'in total' ) );
t( 'config: field rules without labels', isset( $cfg['fields']['owner_email']['type'] ) && ! isset( $cfg['fields']['owner_email']['label'] ) );
t( 'signed out: no prefill sent to the browser', false === $cfg['account']['signedIn'] && array() === $cfg['account']['prefill'] && str_ends_with( $cfg['account']['loginRest'], 'pawfectly/v1/account/login' ) );
t( 'config: messages', 'This field is required.' === $cfg['i18n']['required'] );
t( 'config: pets', 5 === $cfg['maxPets'] && true === $cfg['fields']['pet_name']['perPet'] && false === $cfg['fields']['owner_email']['perPet'] && 'Pet %d' === $cfg['i18n']['petN'] );

// Services and estimate.
$cut = static function ( string $html, string $from, string $to ): string {
	$a = strpos( $html, $from );
	$b = strpos( $html, $to, (int) $a );
	return false === $a || false === $b ? '' : substr( $html, $a, $b - $a );
};
$stay0 = $cut( $html, 'data-paw-pets="stay"', '<template data-paw-pet-template="stay"' );
preg_match_all( '/data-paw-svc="([a-z_]+)"/', $stay0, $svc );
t( "pet 1's service cards in the listed order, then No grooming", array( 'nail_trim', 'nail_membership', 'bath_membership', 'basic_bath', 'deluxe_bath', 'basic_haircut', 'premium_haircut', 'none' ) === $svc[1] );
t( 'service cards are radios named per pet', 8 === substr_count( $stay0, 'name="pets[0][service]"' ) );
t( '"No grooming for this pet" only for boarding + grooming', (bool) preg_match( '/data-paw-svc="none"[^>]*data-paw-both-only/', $stay0 ) && str_contains( $stay0, 'No grooming for this pet' ) );
t( 'monthly tag on the two memberships', 2 === substr_count( $stay0, 'class="paw-svc__tag"' ) );
t( "what's included listed", str_contains( $html, 'Paw pad inspection' ) && str_contains( $html, 'What&#039;s included' ) );
t( 'price range before a size is chosen', str_contains( $stay0, '$15–$20' ) && str_contains( $stay0, 'Price on request' ) );
t( 'groomer: Any / Flexible first', (bool) preg_match( '/name="pets\[0\]\[groomer\]" value="any"/', $stay0 ) );

// Welcome: the booking choice and the menu.
$welcome = $cut( $html, 'data-step="0"', 'data-step="1"' );
t( 'booking type on the welcome screen, boarding preselected', (bool) preg_match( '/name="booking_type" value="boarding"[^>]*checked/', $welcome ) );
t( 'service menu on the welcome screen: boarding plus seven services', str_contains( $welcome, 'data-paw-menu' ) && 8 === substr_count( $welcome, 'class="paw-menu__item' ) && str_contains( $welcome, 'Monthly memberships' ) );
t( 'welcome menu notes grooming is for dogs', str_contains( $welcome, 'Grooming is for dogs. You&#039;ll choose a service for each pet later.' ) );

// Pets.
t( 'pet 1 card with an add button and a template for more', str_contains( $html, 'data-paw-pet="0"' ) && str_contains( $html, 'data-paw-add-pet' ) && str_contains( $html, '<template data-paw-pet-template="pet">' ) && str_contains( $html, 'name="pets[__N__][pet_name]"' ) );
t( 'remove link only in the template (pet 1 stays)', ! str_contains( $cut( $html, 'data-paw-pets="pet"', '<template' ), 'data-paw-remove-pet' ) && str_contains( $cut( $html, '<template data-paw-pet-template="pet">', '</template>' ), 'data-paw-remove-pet' ) );
t( 'health and care have a section per pet', str_contains( $html, 'data-paw-pets="health"' ) && str_contains( $html, 'data-paw-pets="care"' ) && str_contains( $html, 'name="pets[0][feeding_schedule]"' ) );
t( 'special requests after the grooming cards', strpos( $html, 'name="special_requests"' ) > strpos( $html, '<template data-paw-pet-template="stay"' ) );
t( 'contact consent on the waiver step', str_contains( $html, 'name="contact_consent"' ) );
t( 'estimate box in the stay step, hidden at first', (bool) preg_match( '/data-paw-estimate[^>]*hidden/', $html ) && strpos( $html, 'data-paw-estimate' ) > strpos( $html, 'data-step="3"' ) && strpos( $html, 'data-paw-estimate' ) < strpos( $html, 'data-step="4"' ) );
t( 'config: prices by size, null when on request', 20.0 === $cfg['services']['nail_trim']['large'] && null === $cfg['services']['basic_bath']['small'] && true === $cfg['services']['bath_membership']['monthly'] );
t( 'config: rates and currency', array_key_exists( 'other', $cfg['rates'] ) && '$' === $cfg['currency'] );
t( 'config: estimate copy', 'Estimate. Payment is taken at Pawfectly; staff confirm the final price.' === $cfg['i18n']['estimate'] && 'Price on request' === $cfg['i18n']['onRequest'] );
$GLOBALS['paw_options'][ Settings::OPTION ] = array( 'services' => array( 'deluxe_bath' => array( 'enabled' => false ) ) );
t( 'turned-off services are not shown', ! str_contains( Shortcode::markup( Settings::get() ), 'data-paw-svc="deluxe_bath"' ) );
unset( $GLOBALS['paw_options'][ Settings::OPTION ] );

done();
