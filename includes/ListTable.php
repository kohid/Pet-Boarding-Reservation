<?php
/**
 * The reservations list in wp-admin.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class ListTable extends \WP_List_Table {

	private const BULK = array( 'confirmed', 'declined', 'cancelled' );

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'reservation',
				'plural'   => 'reservations',
				'ajax'     => false,
			)
		);
	}

	/** The list filters from the query string, already checked, in Reservations::query() form. */
	public static function read_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification -- read-only filters.
		$get = static fn( string $k ): string => isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) ) : '';
		$filters = array(
			'status'  => sanitize_key( $get( 'status' ) ),
			'search'  => $get( 's' ),
			'from'    => $get( 'from' ),
			'to'      => $get( 'to' ),
			'orderby' => sanitize_key( $get( 'orderby' ) ),
			'order'   => sanitize_key( $get( 'order' ) ),
		);
		// phpcs:enable
		return $filters;
	}

	/** The current filters as query-string arguments, for the export link. */
	public function filters(): array {
		$f = self::read_filters();
		return array_filter(
			array(
				'status' => $f['status'],
				's'      => $f['search'],
				'from'   => $f['from'],
				'to'     => $f['to'],
			)
		);
	}

	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox">',
			'reference'  => __( 'Reference', 'pawfectly-reservations' ),
			'pet'        => __( 'Pet', 'pawfectly-reservations' ),
			'owner'      => __( 'Owner', 'pawfectly-reservations' ),
			'check_in'   => __( 'Stay', 'pawfectly-reservations' ),
			'status'     => __( 'Status', 'pawfectly-reservations' ),
			'created_at' => __( 'Submitted', 'pawfectly-reservations' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'reference'  => array( 'reference', false ),
			'check_in'   => array( 'check_in', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function get_views(): array {
		$counts  = Reservations::counts();
		$current = self::read_filters()['status'];
		$base    = admin_url( 'admin.php?page=' . Admin::MENU );
		$views   = array(
			'all' => sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( $base ), '' === $current ? ' class="current" aria-current="page"' : '', esc_html__( 'All', 'pawfectly-reservations' ), array_sum( $counts ) ),
		);
		foreach ( $counts as $status => $count ) {
			$views[ $status ] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status', $status, $base ) ), $current === $status ? ' class="current" aria-current="page"' : '', esc_html( Reservations::label( $status ) ), $count );
		}
		return $views;
	}

	protected function get_bulk_actions(): array {
		return array(
			'confirmed' => __( 'Confirm', 'pawfectly-reservations' ),
			'declined'  => __( 'Decline', 'pawfectly-reservations' ),
			'cancelled' => __( 'Cancel', 'pawfectly-reservations' ),
		);
	}

	/** Runs on the page's load hook, before any output, so it can redirect. */
	public static function process_bulk(): void {
		$action = isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ( isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '' );
		if ( ! in_array( $action, self::BULK, true ) || empty( $_REQUEST['reservation'] ) ) {
			return;
		}
		check_admin_referer( 'bulk-reservations' );
		if ( ! current_user_can( Activator::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'pawfectly-reservations' ), '', array( 'response' => 403 ) );
		}
		foreach ( array_map( 'absint', (array) $_REQUEST['reservation'] ) as $id ) {
			Reservations::set_status( $id, $action, get_current_user_id() );
		}
		wp_safe_redirect( add_query_arg( 'paw_notice', 'bulk', admin_url( 'admin.php?page=' . Admin::MENU ) ) );
		exit;
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$f = self::read_filters();
		?>
		<div class="alignleft actions paw-range">
			<label for="paw-from"><?php esc_html_e( 'Check-in from', 'pawfectly-reservations' ); ?></label>
			<input type="date" id="paw-from" name="from" value="<?php echo esc_attr( $f['from'] ); ?>">
			<label for="paw-to"><?php esc_html_e( 'to', 'pawfectly-reservations' ); ?></label>
			<input type="date" id="paw-to" name="to" value="<?php echo esc_attr( $f['to'] ); ?>">
			<?php submit_button( __( 'Filter', 'pawfectly-reservations' ), '', 'paw-filter', false ); ?>
		</div>
		<?php
	}

	public function prepare_items(): void {
		$per_page = 20;
		$result   = Reservations::query( array_merge( self::read_filters(), array( 'page' => $this->get_pagenum(), 'per_page' => $per_page ) ) );
		$this->items           = $result['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'reference' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items(): void {
		esc_html_e( 'No reservations yet. They appear here as soon as someone submits the form.', 'pawfectly-reservations' );
	}

	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="reservation[]" value="%d">', (int) $item['id'] );
	}

	protected function column_reference( $item ): string {
		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>', esc_url( Admin::detail_url( (int) $item['id'] ) ), esc_html( $item['reference'] ) );
	}

	protected function column_pet( $item ): string {
		$type = Fields::all()['pet_type']['options'][ $item['pet_type'] ] ?? $item['pet_type'];
		return esc_html( $item['pet_name'] ) . ' <span class="paw-chip">' . esc_html( $type ) . '</span>';
	}

	protected function column_owner( $item ): string {
		return esc_html( trim( $item['owner_first_name'] . ' ' . $item['owner_last_name'] ) ) . '<br><span class="paw-muted">' . esc_html( $item['owner_email'] ) . '</span>';
	}

	protected function column_check_in( $item ): string {
		$nights = ( new \DateTimeImmutable( $item['check_in'] ) )->diff( new \DateTimeImmutable( $item['check_out'] ) )->days;
		/* translators: %d: number of nights */
		return esc_html( Mailer::date( $item['check_in'] ) . ' → ' . Mailer::date( $item['check_out'] ) ) . '<br><span class="paw-muted">' . esc_html( sprintf( _n( '%d night', '%d nights', $nights, 'pawfectly-reservations' ), $nights ) ) . '</span>';
	}

	protected function column_status( $item ): string {
		return sprintf( '<span class="paw-badge paw-badge--%s">%s</span>', esc_attr( $item['status'] ), esc_html( Reservations::label( $item['status'] ) ) );
	}

	protected function column_created_at( $item ): string {
		return esc_html( get_date_from_gmt( $item['created_at'], 'm/d/Y g:i a' ) );
	}
}
