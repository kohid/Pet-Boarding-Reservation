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
define( 'PAW_URL', 'https://example.test/wp-content/plugins/pawfectly-reservations/' );
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
