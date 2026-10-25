<?php
/**
 * My Bookings: the signed-in owner's reservations.
 *
 * @package Pawfectly
 *
 * @var \WP_User $user
 * @var bool     $verified
 * @var array    $rows     Reservations::for_owner() rows.
 * @var string   $today    Y-m-d, site timezone.
 * @var string   $book_url The reservation form page, or ''.
 */

use Pawfectly\Fields;
use Pawfectly\MyBookings;
use Pawfectly\Profile;
use Pawfectly\Reservations;

defined( 'ABSPATH' ) || exit;

$paw_types = Fields::all()['pet_type']['options'];
?>
<div class="paw-acct paw-acct--bookings" data-paw-acct>
	<?php echo Profile::notices( (int) $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
	<div class="paw-acct__card">
		<h2><?php esc_html_e( 'My Bookings', 'pawfectly-reservations' ); ?></h2>
		<?php if ( ! $rows ) : ?>
			<p><?php esc_html_e( 'No reservations yet.', 'pawfectly-reservations' ); ?></p>
			<?php if ( '' !== $book_url ) : ?>
				<p><a class="paw-acct__btn" href="<?php echo esc_url( $book_url ); ?>"><?php esc_html_e( 'Book a stay', 'pawfectly-reservations' ); ?></a></p>
			<?php endif; ?>
		<?php else : ?>
			<table class="paw-acct__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Reference', 'pawfectly-reservations' ); ?></th>
						<th><?php esc_html_e( 'Pet', 'pawfectly-reservations' ); ?></th>
						<th><?php esc_html_e( 'Stay', 'pawfectly-reservations' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pawfectly-reservations' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'pawfectly-reservations' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $paw_r ) : ?>
						<tr data-paw-row="<?php echo esc_attr( (string) $paw_r['id'] ); ?>">
							<td><strong><?php echo esc_html( $paw_r['reference'] ); ?></strong></td>
							<td><?php echo esc_html( $paw_r['pet_name'] ); ?> <span class="paw-acct__muted"><?php echo esc_html( $paw_types[ $paw_r['pet_type'] ] ?? $paw_r['pet_type'] ); ?></span></td>
							<td>
								<?php echo esc_html( Reservations::when( $paw_r ) ); ?>
								<?php if ( '' !== Reservations::service_names( $paw_r ) ) : ?>
									<br><span class="paw-acct__muted"><?php echo esc_html( Reservations::service_names( $paw_r ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><span class="paw-badge paw-badge--<?php echo esc_attr( $paw_r['status'] ); ?>" data-paw-status><?php echo esc_html( Reservations::label( $paw_r['status'] ) ); ?></span></td>
							<td>
								<?php if ( MyBookings::can_cancel( $paw_r, $today ) ) : ?>
									<button type="button" class="paw-acct__btn paw-acct__btn--ghost" data-paw-cancel="<?php echo esc_attr( (string) $paw_r['id'] ); ?>"><?php esc_html_e( 'Cancel', 'pawfectly-reservations' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="paw-acct__msg" data-paw-msg role="status" hidden></p>
			<?php if ( ! $verified ) : ?>
				<p class="paw-acct__muted"><?php esc_html_e( 'Reservations you made before creating your account appear here once you confirm your email.', 'pawfectly-reservations' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
