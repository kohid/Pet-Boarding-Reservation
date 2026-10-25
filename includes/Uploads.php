<?php
/**
 * Vaccination record uploads: checking them, storing them where the web cannot
 * reach them, and handing them to signed-in staff only.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Uploads {

	public const MIMES     = array(
		'pdf' => 'application/pdf',
		'jpg' => 'image/jpeg',
		'png' => 'image/png',
	);
	public const MAX_FILES = 3;
	public const MAX_BYTES = 10485760;
	public const DIR_NAME  = 'pawfectly-private';

	/** @var callable(string): bool Swappable so tests can use plain files instead of real uploads. */
	public static $is_uploaded = 'is_uploaded_file';

	/** @var callable(string, string): bool */
	public static $mover = 'move_uploaded_file';

	public static function init(): void {
		add_action( 'admin_post_paw_file', array( self::class, 'download' ) );
	}

	/**
	 * Checks the PHP multi-file array for the vaccination_files field.
	 *
	 * @return array<int, array{tmp: string, name: string, mime: string, size: int, ext: string}>|\WP_Error
	 */
	public static function check( array $field ) {
		$checked = array();
		$names   = (array) ( $field['name'] ?? array() );

		foreach ( $names as $i => $name ) {
			$error = (int) ( $field['error'][ $i ] ?? UPLOAD_ERR_NO_FILE );
			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}
			if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
				return self::error( __( 'Each file must be 10 MB or smaller.', 'pawfectly-reservations' ) );
			}
			$tmp = (string) ( $field['tmp_name'][ $i ] ?? '' );
			if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! ( self::$is_uploaded )( $tmp ) ) {
				return self::type_error();
			}
			$size = (int) filesize( $tmp );
			if ( $size > self::MAX_BYTES ) {
				return self::error( __( 'Each file must be 10 MB or smaller.', 'pawfectly-reservations' ) );
			}

			$ext = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
			$ext = 'jpeg' === $ext ? 'jpg' : $ext;
			// The browser's claimed type is ignored: the extension must match what the bytes really are.
			$mime = ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $tmp );
			if ( ! isset( self::MIMES[ $ext ] ) || self::MIMES[ $ext ] !== $mime ) {
				return self::type_error();
			}

			$checked[] = array(
				'tmp'  => $tmp,
				'name' => sanitize_text_field( wp_unslash( (string) $name ) ),
				'mime' => $mime,
				'size' => $size,
				'ext'  => $ext,
			);
		}

		if ( count( $checked ) < 1 || count( $checked ) > self::MAX_FILES ) {
			/* translators: %d: maximum number of files */
			return self::error( sprintf( __( 'Please upload between 1 and %d files.', 'pawfectly-reservations' ), self::MAX_FILES ) );
		}
		return $checked;
	}

	/**
	 * Moves checked uploads into the private folder. On any failure, removes what was already moved.
	 *
	 * @return array<int, array{name: string, path: string, mime: string, size: int}>|\WP_Error
	 */
	public static function store( string $reference, array $checked ) {
		$stored = array();
		foreach ( $checked as $file ) {
			$relative = self::new_path( $reference, $file['ext'] );
			if ( null === $relative || ! ( self::$mover )( $file['tmp'], self::private_dir() . '/' . $relative ) ) {
				self::delete( $stored );
				return self::storage_error();
			}
			$stored[] = array(
				'name' => $file['name'],
				'path' => $relative,
				'mime' => $file['mime'],
				'size' => $file['size'],
			);
		}
		return $stored;
	}

	/** @param array<int, array{path: string}> $stored */
	public static function delete( array $stored ): void {
		foreach ( $stored as $file ) {
			$path = self::resolve( $file['path'] );
			if ( $path ) {
				unlink( $path );
			}
		}
	}

	/** Absolute path of a stored file, or null if it is missing or outside the private folder. */
	public static function resolve( string $relative ): ?string {
		$base = realpath( self::private_dir() );
		$path = realpath( self::private_dir() . '/' . $relative );
		if ( ! $base || ! $path || ! is_file( $path ) || ! str_starts_with( $path, $base . DIRECTORY_SEPARATOR ) ) {
			return null;
		}
		return $path;
	}

	public static function private_dir(): string {
		return rtrim( wp_upload_dir()['basedir'], '/\\' ) . '/' . self::DIR_NAME;
	}

	/** Creates the private folder with deny rules for Apache; Nginx needs the rule from the README. */
	public static function ensure_private_dir(): string {
		$dir = self::private_dir();
		wp_mkdir_p( $dir );
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "# Pawfectly: reservation files are only served to staff through wp-admin.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		return $dir;
	}

	/** A fresh relative path like 2026/10/PAW-7K3QX9-3f9a….pdf, with its month folder created. */
	public static function new_path( string $reference, string $ext, string $tag = '' ): ?string {
		$month = gmdate( 'Y/m' );
		if ( ! wp_mkdir_p( self::ensure_private_dir() . '/' . $month ) ) {
			return null;
		}
		return $month . '/' . $reference . ( '' !== $tag ? '-' . $tag : '' ) . '-' . bin2hex( random_bytes( 8 ) ) . '.' . $ext;
	}

	/** Signed link to a reservation's file (`$n` is the file index, or `sig` for the signature). */
	public static function url( int $id, string $n ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=paw_file&id=' . $id . '&n=' . rawurlencode( $n ) ), 'paw_file_' . $id );
	}

	/** admin-post.php?action=paw_file: streams one file of one reservation to staff. */
	public static function download(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$n  = isset( $_GET['n'] ) ? sanitize_key( wp_unslash( $_GET['n'] ) ) : '';
		if ( ! current_user_can( Activator::CAPABILITY ) || ! check_admin_referer( 'paw_file_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to open this file.', 'pawfectly-reservations' ), '', array( 'response' => 403 ) );
		}
		$reservation = Reservations::find( $id );
		if ( ! $reservation ) {
			wp_die( esc_html__( 'Reservation not found.', 'pawfectly-reservations' ), '', array( 'response' => 404 ) );
		}

		if ( 'sig' === $n ) {
			$path   = self::resolve( $reservation['signature_path'] );
			$mime   = 'image/png';
			$name   = $reservation['reference'] . '-signature.png';
			$inline = true;
		} else {
			$file   = $reservation['files'][ (int) $n ] ?? null;
			$path   = $file ? self::resolve( $file['path'] ) : null;
			$mime   = $file['mime'] ?? '';
			$name   = $file['name'] ?? '';
			$inline = false;
		}
		if ( ! $path || ! in_array( $mime, self::MIMES, true ) ) {
			wp_die( esc_html__( 'File not found.', 'pawfectly-reservations' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . str_replace( array( '"', "\r", "\n" ), '', $name ) . '"' );
		readfile( $path );
		exit;
	}

	private static function error( string $message ): \WP_Error {
		return new \WP_Error(
			'paw_invalid',
			__( 'Please check the highlighted fields.', 'pawfectly-reservations' ),
			array( 'status' => 400, 'fields' => array( 'vaccination_files' => $message ) )
		);
	}

	private static function type_error(): \WP_Error {
		return self::error( __( 'Only PDF, JPG or PNG files are accepted.', 'pawfectly-reservations' ) );
	}

	public static function storage_error(): \WP_Error {
		return new \WP_Error( 'paw_storage', __( 'Something went wrong, your details are still here — please try again.', 'pawfectly-reservations' ), array( 'status' => 500 ) );
	}
}
