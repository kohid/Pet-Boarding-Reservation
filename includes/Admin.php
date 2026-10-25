<?php
/**
 * wp-admin: the Pawfectly menu, the reservations list and detail view, status changes and CSV export.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public const MENU = 'paw-reservations';

	/** Field types that are not plain answers: shown specially, never exported. */
	private const NOT_ANSWERS = array( 'file', 'signature', 'agree' );

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_post_paw_status', array( self::class, 'handle_status' ) );
		add_action( 'admin_post_paw_notes', array( self::class, 'handle_notes' ) );
		add_action( 'admin_post_paw_export', array( self::class, 'handle_export' ) );
		add_action( 'admin_post_paw_delete', array( self::class, 'handle_delete' ) );
	}

	public static function menu(): void {
		$pending = Reservations::counts()['pending'];
		$bubble  = $pending ? ' <span class="awaiting-mod count-' . (int) $pending . '"><span class="pending-count">' . (int) $pending . '</span></span>' : '';
		$hook    = add_menu_page( __( 'Reservations', 'pawfectly-reservations' ), __( 'Pawfectly', 'pawfectly-reservations' ) . $bubble, Activator::CAPABILITY, self::MENU, array( self::class, 'page' ), 'dashicons-pets', 26 );
		add_action(
			'load-' . $hook,
			static function (): void {
				require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
				ListTable::process_bulk();
			}
		);
		add_submenu_page( self::MENU, __( 'Reservations', 'pawfectly-reservations' ), __( 'Reservations', 'pawfectly-reservations' ), Activator::CAPABILITY, self::MENU, array( self::class, 'page' ) );
	}

	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, self::MENU ) && ! str_contains( $hook, Settings::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'paw-admin', PAW_URL . 'assets/css/admin.css', array(), PAW_VERSION );
		wp_enqueue_script( 'paw-admin', PAW_URL . 'assets/js/admin.js', array(), PAW_VERSION, true );
		wp_localize_script( 'paw-admin', 'PAW_ADMIN', array( 'chooseLogo' => __( 'Choose a logo', 'pawfectly-reservations' ), 'copied' => __( 'Copied', 'pawfectly-reservations' ), 'confirmBulkDelete' => __( 'Delete the selected reservations and their files permanently? This cannot be undone.', 'pawfectly-reservations' ) ) );
	}

	public static function detail_url( int $id ): string {
		return admin_url( 'admin.php?page=' . self::MENU . '&view=' . $id );
	}

	public static function page(): void {
		if ( ! current_user_can( Activator::CAPABILITY ) ) {
			return;
		}
		$id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- read-only view.
		if ( $id ) {
			$reservation = Reservations::find( $id );
			if ( ! $reservation ) {
				echo '<div class="wrap"><p>' . esc_html__( 'Reservation not found.', 'pawfectly-reservations' ) . '</p></div>';
				return;
			}
			self::render_detail( $reservation );
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		$table = new ListTable();
		$table->prepare_items();
		$export = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'paw_export' ), $table->filters() ), admin_url( 'admin-post.php' ) ), 'paw_export' );
		?>
		<div class="wrap paw-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Reservations', 'pawfectly-reservations' ); ?></h1>
			<a href="<?php echo esc_url( $export ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'pawfectly-reservations' ); ?></a>
			<hr class="wp-header-end">
			<?php self::notice(); ?>
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU ); ?>">
				<?php if ( ! empty( $_GET['status'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['status'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?>">
				<?php endif; ?>
				<?php $table->search_box( __( 'Search reservations', 'pawfectly-reservations' ), 'paw-search' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function render_detail( array $reservation ): void {
		$r      = $reservation;
		$steps  = Fields::steps();
		$values = self::values( $r );
		include PAW_DIR . 'templates/admin-detail.php';
	}

	public static function values( array $r ): array {
		return Reservations::values( $r );
	}

	/** An answer as a person reads it: option labels, country names, m/d/Y dates. Not escaped. */
	public static function answer_text( array $field, string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		switch ( $field['type'] ) {
			case 'choice':
			case 'yesno':
				return (string) ( $field['options'][ $value ] ?? $value );
			case 'country':
				return Fields::countries()[ $value ] ?? $value;
			case 'date':
				return Mailer::date( $value );
		}
		return $value;
	}

	/** Field ids exported as CSV columns, after reference/status/submitted, then two summary columns. */
	public static function csv_keys(): array {
		$keys = array( 'reference', 'status', 'created_at' );
		foreach ( Fields::all() as $id => $field ) {
			if ( ! in_array( $field['type'], self::NOT_ANSWERS, true ) ) {
				$keys[] = $id;
			}
		}
		return array_merge( $keys, array( 'files_count', 'signed_at' ) );
	}

	public static function csv_header(): array {
		$all    = Fields::all();
		$labels = array(
			'reference'   => __( 'Reference', 'pawfectly-reservations' ),
			'status'      => __( 'Status', 'pawfectly-reservations' ),
			'created_at'  => __( 'Submitted (UTC)', 'pawfectly-reservations' ),
			'files_count' => __( 'Vaccination files', 'pawfectly-reservations' ),
			'signed_at'   => __( 'Signed (UTC)', 'pawfectly-reservations' ),
		);
		return array_map( fn( $key ) => $labels[ $key ] ?? $all[ $key ]['label'], self::csv_keys() );
	}

	public static function csv_row( array $r ): array {
		$all    = Fields::all();
		$values = self::values( $r );
		$row    = array();
		foreach ( self::csv_keys() as $key ) {
			switch ( $key ) {
				case 'reference':
				case 'created_at':
				case 'signed_at':
					$cell = (string) $r[ $key ];
					break;
				case 'status':
					$cell = Reservations::label( (string) $r['status'] );
					break;
				case 'files_count':
					$cell = (string) count( (array) $r['files'] );
					break;
				default:
					$field = $all[ $key ];
					$cell  = Validator::is_shown( $field, $values ) ? self::answer_text( $field, (string) ( $values[ $key ] ?? '' ) ) : '';
			}
			$row[] = self::csv_cell( $cell );
		}
		return $row;
	}

	/** Stops spreadsheet apps from running a cell as a formula. */
	public static function csv_cell( string $value ): string {
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	public static function handle_status(): void {
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		self::guard( 'paw_status_' . $id );
		$ok = Reservations::set_status( $id, $status, get_current_user_id() );
		if ( $ok && ! empty( $_POST['notify'] ) ) {
			$reservation = Reservations::find( $id );
			if ( $reservation ) {
				Mailer::status_changed( $reservation );
			}
		}
		wp_safe_redirect( add_query_arg( 'paw_notice', $ok ? 'status' : 'error', self::detail_url( $id ) ) );
		exit;
	}

	public static function handle_notes(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		self::guard( 'paw_notes_' . $id );
		Reservations::set_notes( $id, isset( $_POST['staff_notes'] ) ? wp_unslash( (string) $_POST['staff_notes'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised in set_notes.
		wp_safe_redirect( add_query_arg( 'paw_notice', 'notes', self::detail_url( $id ) ) );
		exit;
	}

	public static function handle_delete(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		self::guard( 'paw_delete_' . $id );
		$ok = Reservations::delete( $id );
		wp_safe_redirect( add_query_arg( 'paw_notice', $ok ? 'deleted' : 'error', admin_url( 'admin.php?page=' . self::MENU ) ) );
		exit;
	}

	/** Streams the reservations matching the list's current filters as CSV. */
	public static function handle_export(): void {
		self::guard( 'paw_export', 'get' );
		$result = Reservations::query( array_merge( ListTable::read_filters(), array( 'per_page' => 0, 'orderby' => 'check_in', 'order' => 'asc' ) ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="pawfectly-reservations-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads UTF-8 names correctly.
		fputcsv( $out, self::csv_header() );
		foreach ( $result['rows'] as $r ) {
			fputcsv( $out, self::csv_row( $r ) );
		}
		fclose( $out );
		exit;
	}

	private static function guard( string $action, string $method = 'post' ): void {
		$nonce = 'get' === $method ? ( $_GET['_wpnonce'] ?? '' ) : ( $_POST['_wpnonce'] ?? '' ); // phpcs:ignore WordPress.Security
		if ( ! current_user_can( Activator::CAPABILITY ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $action ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'pawfectly-reservations' ), '', array( 'response' => 403 ) );
		}
	}

	public static function notice(): void {
		$notice   = isset( $_GET['paw_notice'] ) ? sanitize_key( wp_unslash( $_GET['paw_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$messages = array(
			'status' => __( 'Status updated.', 'pawfectly-reservations' ),
			'notes'  => __( 'Notes saved.', 'pawfectly-reservations' ),
			'bulk'   => __( 'Reservations updated.', 'pawfectly-reservations' ),
			'deleted' => __( 'Reservation and its files deleted.', 'pawfectly-reservations' ),
			'error'  => __( 'That change could not be saved.', 'pawfectly-reservations' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 'error' === $notice ? 'error' : 'success', esc_html( $messages[ $notice ] ) );
		}
	}
}
