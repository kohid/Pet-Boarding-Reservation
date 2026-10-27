<?php
/**
 * Tiny stand-ins for the WordPress functions the plugin uses, so tests run with
 * plain `php tests/foo-test.php`. Only what the code under test needs lives here.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'PAW_VERSION', 'test' );
define( 'PAW_DB_VERSION', '1' );
define( 'PAW_FILE', dirname( __DIR__ ) . '/pawfectly-reservations.php' );
define( 'PAW_DIR', dirname( __DIR__ ) . '/' );
defined( 'PAW_URL' ) || define( 'PAW_URL', 'https://example.test/wp-content/plugins/pawfectly-reservations/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

$GLOBALS['paw_options']    = array();
$GLOBALS['paw_transients'] = array();

function is_wp_error( $x ) { return $x instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function _n( $one, $many, $n, $d = null ) { return 1 === $n ? $one : $many; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr_e( $s, $d = null ) { echo esc_attr( $s ); }
function sanitize_text_field( $s ) { return trim( strip_tags( preg_replace( '/[\r\n\t ]+/', ' ', (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return trim( (string) $s ); }
function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function get_option( $k, $d = false ) { return $GLOBALS['paw_options'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['paw_options'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['paw_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['paw_transients'][ $k ] = $v; return true; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0755, true ); }
function wp_upload_dir() {
	if ( empty( $GLOBALS['paw_upload_base'] ) ) {
		$GLOBALS['paw_upload_base'] = sys_get_temp_dir() . '/paw-test-uploads-' . getmypid();
	}
	return array( 'basedir' => $GLOBALS['paw_upload_base'], 'baseurl' => 'https://example.test/wp-content/uploads', 'error' => false );
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Pawfectly\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$path = PAW_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

$GLOBALS['paw_fail'] = 0;

function t( string $name, bool $ok ): void {
	echo ( $ok ? 'ok   - ' : 'FAIL - ' ) . $name . "\n";
	if ( ! $ok ) {
		++$GLOBALS['paw_fail'];
	}
}

function done(): never {
	echo $GLOBALS['paw_fail'] ? "\n{$GLOBALS['paw_fail']} failed\n" : "\nall passed\n";
	exit( $GLOBALS['paw_fail'] ? 1 : 0 );
}

// Rendering and settings stand-ins.
$GLOBALS['paw_roles'] = array();
class Fake_Role {
	public $name;
	public $caps = array();
	public $capabilities = array(); // WP_Role's real property name.
	public function __construct( $name ) { $this->name = $name; }
	public function add_cap( $cap ) { $this->caps[ $cap ] = true; $this->capabilities[ $cap ] = true; }
	public function remove_cap( $cap ) { unset( $this->caps[ $cap ], $this->capabilities[ $cap ] ); }
	public function has_cap( $cap ) { return ! empty( $this->caps[ $cap ] ); }
}
foreach ( array( 'administrator' => 'Administrator', 'editor' => 'Editor', 'shop_manager' => 'Shop manager', 'subscriber' => 'Subscriber' ) as $slug => $label ) {
	$GLOBALS['paw_roles'][ $slug ] = new Fake_Role( $slug );
}
function get_role( $r ) { return $GLOBALS['paw_roles'][ $r ] ?? null; }
function wp_roles() { return (object) array( 'role_names' => array( 'administrator' => 'Administrator', 'editor' => 'Editor', 'shop_manager' => 'Shop manager', 'subscriber' => 'Subscriber' ) ); }
function wp_kses( $html, $allowed ) { return strip_tags( (string) $html, array_keys( $allowed ) ); }
function wp_kses_post( $html ) { return strip_tags( (string) $html, array( 'p', 'strong', 'em', 'ul', 'ol', 'li', 'br', 'a' ) ); }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return $id ? "https://example.test/logo-$id.png" : false; }
function selected( $a, $b, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . ltrim( $p, '/' ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . ltrim( $p, '/' ); }
function wp_nonce_url( $url, $action = -1 ) { return esc_html( $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . '_wpnonce=nonce-' . $action ); } // Escaped for HTML, like WordPress's.
$GLOBALS['paw_valid_nonce'] = 'good-nonce';
function wp_verify_nonce( $nonce, $action = -1 ) { return 'wp_rest' === $action && $nonce === $GLOBALS['paw_valid_nonce'] ? 1 : false; }
function wp_create_nonce( $action = -1 ) { return $GLOBALS['paw_valid_nonce']; }
function current_time( $type, $gmt = 0 ) { return gmdate( 'Y-m-d H:i:s' ); }
function absint( $v ) { return abs( (int) $v ); }
$GLOBALS['paw_mail']        = array();
$GLOBALS['paw_mail_result'] = true;
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	$GLOBALS['paw_mail'][] = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
	return $GLOBALS['paw_mail_result'];
}
function size_format( $b ) { return round( $b / 1024 ) . ' KB'; }
function wp_max_upload_size() { return $GLOBALS['paw_max_upload'] ?? 64 * 1024 * 1024; }
function wp_date( $f, $t = null ) { return gmdate( $f, $t ?? time() ); }
