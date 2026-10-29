<?php
/**
 * My Bookings → Event sign-ups: the owner's sign-ups, with Cancel until the event starts.
 * Nothing is shown when there are none.
 *
 * @package Pawfectly
 *
 * @var array $paw_signups MyBookings::signup_rows().
 */

defined( 'ABSPATH' ) || exit;

if ( ! $paw_signups ) {
	return;
}
?>
<div class="paw-acct__card paw-acct__signups">
	<h2><?php esc_html_e( 'Event sign-ups', 'pawfectly-reservations' ); ?></h2>
	<table class="paw-acct__table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Event', 'pawfectly-reservations' ); ?></th>
				<th><?php esc_html_e( 'Dogs', 'pawfectly-reservations' ); ?></th>
				<th><?php esc_html_e( 'Total', 'pawfectly-reservations' ); ?></th>
				<th><?php esc_html_e( 'Status', 'pawfectly-reservations' ); ?></th>
				<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'pawfectly-reservations' ); ?></span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $paw_signups as $paw_su ) : ?>
				<tr data-paw-row="<?php echo esc_attr( (string) $paw_su['id'] ); ?>">
					<td>
						<strong>
							<?php echo '' !== $paw_su['emoji'] ? esc_html( $paw_su['emoji'] ) . ' ' : ''; ?>
							<?php if ( '' !== $paw_su['url'] ) : ?>
								<a href="<?php echo esc_url( $paw_su['url'] ); ?>"><?php echo esc_html( $paw_su['event'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $paw_su['event'] ); ?>
							<?php endif; ?>
						</strong>
						<br><span class="paw-acct__muted"><?php echo esc_html( $paw_su['when'] ); ?></span>
						<?php if ( '' !== $paw_su['session'] ) : ?>
							<br><span class="paw-acct__muted"><?php echo esc_html( $paw_su['session'] ); ?></span>
						<?php endif; ?>
						<br><span class="paw-acct__muted"><?php echo esc_html( $paw_su['reference'] ); ?></span>
					</td>
					<td><?php echo esc_html( $paw_su['dogs'] ); ?></td>
					<td><?php echo esc_html( $paw_su['total_text'] ); ?></td>
					<td><span class="paw-badge paw-badge--<?php echo esc_attr( $paw_su['status'] ); ?>" data-paw-status><?php echo esc_html( $paw_su['label'] ); ?></span></td>
					<td>
						<?php if ( $paw_su['can_cancel'] ) : ?>
							<button type="button" class="paw-acct__btn paw-acct__btn--ghost" data-paw-cancel-signup="<?php echo esc_attr( (string) $paw_su['id'] ); ?>"><?php esc_html_e( 'Cancel', 'pawfectly-reservations' ); ?></button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="paw-acct__msg" data-paw-msg role="status" hidden></p>
</div>
