<?php
/**
 * Plugin settings: one option, sanitised on save, and the Settings screen.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'paw_settings';
	public const PAGE   = 'paw-settings';

	public const WAIVER_TAGS = array(
		'p'      => array(),
		'strong' => array(),
		'em'     => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'br'     => array(),
	);

	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
	}

	public static function defaults(): array {
		return array(
			'business_name'   => 'Pawfectly',
			'logo_id'         => 0,
			'staff_emails'    => (string) get_option( 'admin_email' ),
			'intro'           => __( "We're excited to care for your furry family member! Please provide the information below so we can ensure a safe, comfortable and happy stay.", 'pawfectly-reservations' ),
			'waiver'          => '<p>' . __( 'I understand that boarding my pet involves inherent risks. I certify that my pet is in good health and has not been exposed to any contagious diseases within the last 30 days. I agree to hold harmless the boarding facility, its owners, employees, and agents from any and all liability for injury, illness, or death of my pet, or for damage to personal property, except in cases of proven negligence or intentional misconduct. I authorize the facility to seek emergency veterinary care if needed and agree to be responsible for all associated costs. I understand that every effort will be made to contact me before treatment, but in an emergency, care will be provided immediately. I certify that all information provided in this form is accurate and complete.', 'pawfectly-reservations' ) . '</p>',
			'default_country' => 'US',
			'roles'           => array(),
			'currency'        => '$',
			'boarding_rates'  => array( 'small' => '', 'large' => '', 'other' => '' ),
			'services'        => Services::defaults(),
			'groomers'        => array(),
			'grooming_waiver' => '<p>' . __( 'I understand that grooming involves handling, bathing, drying and the use of sharp tools, and that pets can move suddenly. Small nicks, clipper irritation or razor burn can happen despite every care. I certify that my pet is in good health and up to date on vaccinations.', 'pawfectly-reservations' ) . '</p>'
				. '<p>' . __( 'If my pet is heavily matted, I agree that the safest option may be to shave the coat short, and that removing mats can reveal skin problems that were already there. Senior pets and pets with health conditions can find grooming stressful; I will tell staff about any condition before the appointment.', 'pawfectly-reservations' ) . '</p>'
				. '<p>' . __( 'Pawfectly may stop a groom if my pet becomes aggressive or too stressed, and I will be charged for the work done. I authorize Pawfectly to seek veterinary care if my pet is injured or unwell during the visit and agree to be responsible for the costs; every effort will be made to contact me first.', 'pawfectly-reservations' ) . '</p>',
			'grooming_needs_vaccines' => true,
		);
	}

	public static function get(): array {
		return array_merge( self::defaults(), (array) get_option( self::OPTION, array() ) );
	}

	/** @return string[] */
	public static function staff_emails( ?array $settings = null ): array {
		$settings = $settings ?? self::get();
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $settings['staff_emails'] ) ), 'is_email' ) );
	}

	/** Sanitises the posted settings and applies the staff-access roles. */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		$name   = sanitize_text_field( (string) ( $input['business_name'] ?? '' ) );
		$emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) ( $input['staff_emails'] ?? '' ) ) ) ), 'is_email' );
		$intro  = sanitize_textarea_field( (string) ( $input['intro'] ?? '' ) );
		$waiver = trim( wp_kses( (string) ( $input['waiver'] ?? '' ), self::WAIVER_TAGS ) );
		$roles  = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['roles'] ?? array() ) ), array_keys( wp_roles()->role_names ) ) );
		$code   = strtoupper( sanitize_text_field( (string) ( $input['default_country'] ?? '' ) ) );

		self::apply_roles( $roles );

		return array(
			'business_name'   => '' !== $name ? $name : $defaults['business_name'],
			'logo_id'         => absint( $input['logo_id'] ?? 0 ),
			'staff_emails'    => $emails ? implode( ', ', $emails ) : $defaults['staff_emails'],
			'intro'           => '' !== $intro ? $intro : $defaults['intro'],
			'waiver'          => '' !== $waiver ? $waiver : $defaults['waiver'],
			'default_country' => isset( Fields::countries()[ $code ] ) ? $code : 'US',
			'roles'           => $roles,
		) + self::sanitize_pricing( $input, self::get() );
	}

	/**
	 * Currency, boarding rates and services. Anything not sent keeps its saved value, so the
	 * wp-admin form (which has no pricing fields) never wipes prices; an invalid price keeps the
	 * saved one too.
	 */
	private static function sanitize_pricing( array $input, array $current ): array {
		$currency = mb_substr( sanitize_text_field( (string) ( $input['currency'] ?? $current['currency'] ) ), 0, 3 );

		$rates = array();
		foreach ( array( 'small', 'large', 'other' ) as $key ) {
			$was           = (string) ( $current['boarding_rates'][ $key ] ?? '' );
			$sent          = $input['boarding_rates'][ $key ] ?? $was;
			$rates[ $key ] = Services::clean_price( $sent ) ?? $was;
		}

		$services = array();
		$sent_all = is_array( $input['services'] ?? null ) ? $input['services'] : array();
		foreach ( Services::defaults() as $id => $default ) {
			$was  = array_merge( $default, is_array( $current['services'][ $id ] ?? null ) ? $current['services'][ $id ] : array() );
			$sent = is_array( $sent_all[ $id ] ?? null ) ? $sent_all[ $id ] : array();
			$name = mb_substr( sanitize_text_field( (string) ( $sent['name'] ?? $was['name'] ) ), 0, 60 );

			$services[ $id ] = array(
				'enabled'  => array_key_exists( 'enabled', $sent ) ? ! empty( $sent['enabled'] ) : (bool) $was['enabled'],
				'name'     => '' !== $name ? $name : $default['name'],
				'time'     => mb_substr( sanitize_text_field( (string) ( $sent['time'] ?? $was['time'] ) ), 0, 60 ),
				'small'    => Services::clean_price( $sent['small'] ?? $was['small'] ) ?? (string) $was['small'],
				'large'    => Services::clean_price( $sent['large'] ?? $was['large'] ) ?? (string) $was['large'],
				'cat'      => Services::clean_price( $sent['cat'] ?? $was['cat'] ) ?? (string) $was['cat'],
				'included' => sanitize_textarea_field( (string) ( $sent['included'] ?? $was['included'] ) ),
			);
		}

		// Groomers: one name per line (or a list), trimmed, no blanks or repeats, order kept.
		$raw      = $input['groomers'] ?? $current['groomers'] ?? array();
		$names    = is_array( $raw ) ? $raw : explode( "\n", (string) $raw );
		$groomers = array();
		foreach ( $names as $name ) {
			$name = mb_substr( sanitize_text_field( (string) $name ), 0, 60 );
			if ( '' !== $name && ! in_array( mb_strtolower( $name ), array_map( 'mb_strtolower', $groomers ), true ) ) {
				$groomers[] = $name;
			}
		}

		$gwaiver = trim( wp_kses( (string) ( $input['grooming_waiver'] ?? $current['grooming_waiver'] ?? '' ), self::WAIVER_TAGS ) );

		return array(
			'grooming_waiver'         => '' !== $gwaiver ? $gwaiver : self::defaults()['grooming_waiver'],
			'grooming_needs_vaccines' => array_key_exists( 'grooming_needs_vaccines', $input ) ? ! empty( $input['grooming_needs_vaccines'] ) : (bool) ( $current['grooming_needs_vaccines'] ?? true ),
			'groomers'       => $groomers,
			'currency'       => '' !== $currency ? $currency : '$',
			'boarding_rates' => $rates,
			'services'       => $services,
		);
	}

	/** Ticked roles get the staff capability, every other role loses it; administrators and Developers always keep it. */
	private static function apply_roles( array $roles ): void {
		$developer = get_role( Access::DEVELOPER );
		if ( $developer ) {
			$developer->add_cap( Activator::CAPABILITY );
		}
		foreach ( array_keys( wp_roles()->role_names ) as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			if ( in_array( $slug, array( 'administrator', Access::DEVELOPER ), true ) || in_array( $slug, $roles, true ) ) {
				$role->add_cap( Activator::CAPABILITY );
			} else {
				$role->remove_cap( Activator::CAPABILITY );
			}
		}
	}

	public static function register(): void {
		register_setting( 'paw_settings_group', self::OPTION, array( 'sanitize_callback' => array( self::class, 'sanitize' ) ) );
	}

	public static function menu(): void {
		add_submenu_page( Admin::MENU, __( 'Pawfectly settings', 'pawfectly-reservations' ), __( 'Settings', 'pawfectly-reservations' ), 'manage_options', self::PAGE, array( self::class, 'page' ) );
	}

	/**
	 * Whether a test file in the private folder can be fetched from the web (null = could not tell).
	 * Cached for an hour so the Settings screen does not make a request on every visit.
	 */
	public static function files_exposed(): ?bool {
		$cached = get_transient( 'paw_files_exposed' );
		if ( false !== $cached ) {
			return 'unknown' === $cached ? null : 'yes' === $cached;
		}
		$dir = Uploads::ensure_private_dir();
		file_put_contents( $dir . '/probe.txt', 'paw' );
		$url      = trailingslashit( wp_upload_dir()['baseurl'] ) . Uploads::DIR_NAME . '/probe.txt';
		$response = wp_remote_get( $url, array( 'timeout' => 5, 'sslverify' => false ) );
		unlink( $dir . '/probe.txt' );

		$result = is_wp_error( $response ) ? null : ( 200 === wp_remote_retrieve_response_code( $response ) && 'paw' === wp_remote_retrieve_body( $response ) );
		set_transient( 'paw_files_exposed', null === $result ? 'unknown' : ( $result ? 'yes' : 'no' ), HOUR_IN_SECONDS );
		return $result;
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_media();
		$s       = self::get();
		$name    = self::OPTION;
		$logo    = $s['logo_id'] ? wp_get_attachment_image_url( $s['logo_id'], 'thumbnail' ) : '';
		$exposed = self::files_exposed();
		?>
		<div class="wrap paw-admin">
			<h1><?php esc_html_e( 'Pawfectly settings', 'pawfectly-reservations' ); ?></h1>

			<div class="paw-card">
				<h2><?php esc_html_e( 'Shortcodes', 'pawfectly-reservations' ); ?></h2>
				<p class="paw-muted"><?php esc_html_e( 'Paste a shortcode into any page, or into an Elementor Shortcode widget. "Create page" makes a draft for you to publish.', 'pawfectly-reservations' ); ?></p>
				<table class="widefat striped paw-shortcodes">
					<thead><tr><th><?php esc_html_e( 'Shortcode', 'pawfectly-reservations' ); ?></th><th><?php esc_html_e( 'Shows', 'pawfectly-reservations' ); ?></th><th><?php esc_html_e( 'Used on', 'pawfectly-reservations' ); ?></th><th></th></tr></thead>
					<tbody>
						<?php foreach ( Pages::TAGS as $paw_tag => $paw_info ) : ?>
							<?php $paw_url = Pages::url( $paw_tag ); ?>
							<tr>
								<td><code id="paw-sc-<?php echo esc_attr( $paw_tag ); ?>">[<?php echo esc_html( $paw_tag ); ?>]</code> <button type="button" class="button button-small" data-paw-copy="#paw-sc-<?php echo esc_attr( $paw_tag ); ?>"><?php esc_html_e( 'Copy', 'pawfectly-reservations' ); ?></button></td>
								<td><?php echo esc_html( $paw_info[1] ); ?></td>
								<td>
									<?php if ( '' !== $paw_url ) : ?>
										<a href="<?php echo esc_url( $paw_url ); ?>"><?php echo esc_html( $paw_url ); ?></a>
									<?php elseif ( 'pawfectly_user_menu' === $paw_tag ) : ?>
										<span class="paw-muted"><?php esc_html_e( 'Your header template', 'pawfectly-reservations' ); ?></span>
									<?php else : ?>
										<span class="paw-muted"><?php esc_html_e( 'Not on a published page yet', 'pawfectly-reservations' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'pawfectly_user_menu' !== $paw_tag ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="paw_create_page">
											<input type="hidden" name="tag" value="<?php echo esc_attr( $paw_tag ); ?>">
											<?php wp_nonce_field( 'paw_create_page' ); ?>
											<button type="submit" class="button button-small"><?php esc_html_e( 'Create page', 'pawfectly-reservations' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="paw-card paw-card--note">
				<h2><?php esc_html_e( 'Private files', 'pawfectly-reservations' ); ?></h2>
				<?php if ( false === $exposed ) : ?>
					<p><span class="paw-badge paw-badge--confirmed"><?php esc_html_e( 'Protected', 'pawfectly-reservations' ); ?></span> <?php esc_html_e( 'Vaccination records and signatures cannot be opened from the web. Staff open them from each reservation.', 'pawfectly-reservations' ); ?></p>
				<?php elseif ( true === $exposed ) : ?>
					<p><span class="paw-badge paw-badge--declined"><?php esc_html_e( 'Exposed', 'pawfectly-reservations' ); ?></span> <?php esc_html_e( 'Your web server is serving files from the private folder. On Nginx, add this rule to the site configuration and reload Nginx:', 'pawfectly-reservations' ); ?></p>
					<pre><code>location ~* /wp-content/uploads/<?php echo esc_html( Uploads::DIR_NAME ); ?>/ { deny all; }</code></pre>
				<?php else : ?>
					<p><span class="paw-badge paw-badge--pending"><?php esc_html_e( 'Not checked', 'pawfectly-reservations' ); ?></span> <?php esc_html_e( 'The site could not reach itself to test the private folder. Check that this address returns "Forbidden":', 'pawfectly-reservations' ); ?> <code><?php echo esc_html( trailingslashit( wp_upload_dir()['baseurl'] ) . Uploads::DIR_NAME . '/index.php' ); ?></code></p>
				<?php endif; ?>
			</div>

			<form method="post" action="options.php" class="paw-card">
				<?php settings_fields( 'paw_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="paw-business"><?php esc_html_e( 'Business name', 'pawfectly-reservations' ); ?></label></th>
						<td><input type="text" class="regular-text" id="paw-business" name="<?php echo esc_attr( $name ); ?>[business_name]" value="<?php echo esc_attr( $s['business_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logo', 'pawfectly-reservations' ); ?></th>
						<td>
							<div class="paw-logo-pick">
								<img src="<?php echo esc_url( $logo ? $logo : PAW_URL . 'assets/img/paw.svg' ); ?>" alt="" width="72" height="72" data-paw-logo-preview>
								<input type="hidden" name="<?php echo esc_attr( $name ); ?>[logo_id]" value="<?php echo esc_attr( (string) $s['logo_id'] ); ?>" data-paw-logo-id>
								<button type="button" class="button" data-paw-logo-choose><?php esc_html_e( 'Choose logo', 'pawfectly-reservations' ); ?></button>
								<button type="button" class="button-link" data-paw-logo-remove data-placeholder="<?php echo esc_url( PAW_URL . 'assets/img/paw.svg' ); ?>"><?php esc_html_e( 'Use the paw placeholder', 'pawfectly-reservations' ); ?></button>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="paw-emails"><?php esc_html_e( 'Send new reservations to', 'pawfectly-reservations' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="paw-emails" name="<?php echo esc_attr( $name ); ?>[staff_emails]" value="<?php echo esc_attr( $s['staff_emails'] ); ?>">
							<p class="description"><?php esc_html_e( 'One or more email addresses, separated by commas.', 'pawfectly-reservations' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="paw-intro"><?php esc_html_e( 'Welcome text', 'pawfectly-reservations' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="paw-intro" name="<?php echo esc_attr( $name ); ?>[intro]"><?php echo esc_textarea( $s['intro'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="paw-waiver"><?php esc_html_e( 'Liability waiver', 'pawfectly-reservations' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="10" id="paw-waiver" name="<?php echo esc_attr( $name ); ?>[waiver]"><?php echo esc_textarea( $s['waiver'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown on the last step. You can use paragraphs, bold, italic and lists.', 'pawfectly-reservations' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="paw-groomers"><?php esc_html_e( 'Groomers', 'pawfectly-reservations' ); ?></label></th>
						<td>
							<textarea class="regular-text" rows="4" id="paw-groomers" name="<?php echo esc_attr( $name ); ?>[groomers]"><?php echo esc_textarea( implode( "\n", (array) $s['groomers'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One name per line. Owners can ask for one of them, or choose "Any / Flexible".', 'pawfectly-reservations' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="paw-gwaiver"><?php esc_html_e( 'Grooming waiver', 'pawfectly-reservations' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="8" id="paw-gwaiver" name="<?php echo esc_attr( $name ); ?>[grooming_waiver]"><?php echo esc_textarea( $s['grooming_waiver'] ); ?></textarea>
							<label><input type="hidden" name="<?php echo esc_attr( $name ); ?>[grooming_needs_vaccines]" value="0"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[grooming_needs_vaccines]" value="1"<?php checked( $s['grooming_needs_vaccines'] ); ?>> <?php esc_html_e( 'Require vaccination records for grooming-only bookings', 'pawfectly-reservations' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="paw-country"><?php esc_html_e( 'Default country', 'pawfectly-reservations' ); ?></label></th>
						<td>
							<select id="paw-country" name="<?php echo esc_attr( $name ); ?>[default_country]">
								<?php foreach ( Fields::countries() as $code => $country ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>"<?php selected( $s['default_country'], $code ); ?>><?php echo esc_html( $country ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Who can manage reservations', 'pawfectly-reservations' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( wp_roles()->role_names as $slug => $label ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[roles][]" value="<?php echo esc_attr( $slug ); ?>"
											<?php checked( 'administrator' === $slug || in_array( $slug, $s['roles'], true ) ); ?>
											<?php disabled( 'administrator' === $slug ); ?>>
										<?php echo esc_html( translate_user_role( $label ) ); ?>
									</label><br>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Administrators always can.', 'pawfectly-reservations' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'pawfectly-reservations' ) ); ?>
			</form>
		</div>
		<?php
	}
}
