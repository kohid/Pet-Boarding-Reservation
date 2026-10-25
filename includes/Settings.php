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

	private const WAIVER_TAGS = array(
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
		);
	}

	/** Ticked roles get the staff capability, every other role loses it; administrators always keep it. */
	private static function apply_roles( array $roles ): void {
		foreach ( array_keys( wp_roles()->role_names ) as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			if ( 'administrator' === $slug || in_array( $slug, $roles, true ) ) {
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

			<div class="paw-card paw-card--note">
				<h2><?php esc_html_e( 'Add the form to a page', 'pawfectly-reservations' ); ?></h2>
				<p><?php esc_html_e( 'Paste this shortcode into any page, or into an Elementor Shortcode widget.', 'pawfectly-reservations' ); ?></p>
				<p class="paw-copy">
					<code id="paw-shortcode">[<?php echo esc_html( Shortcode::TAG ); ?>]</code>
					<button type="button" class="button" data-paw-copy="#paw-shortcode"><?php esc_html_e( 'Copy', 'pawfectly-reservations' ); ?></button>
				</p>
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
