<?php
/**
 * One reservation in wp-admin: status, every answer by step, files, signature, notes and history.
 * The same page prints cleanly (admin.css hides the wp-admin chrome in print).
 *
 * @package Pawfectly
 *
 * @var array $r      Reservations::find() row.
 * @var array $steps  Fields::steps().
 * @var array $values Admin::values( $r ).
 */

use Pawfectly\Admin;
use Pawfectly\Mailer;
use Pawfectly\Reservations;
use Pawfectly\Uploads;
use Pawfectly\Validator;

defined( 'ABSPATH' ) || exit;

$paw_id     = (int) $r['id'];
$paw_nights = ( new DateTimeImmutable( $r['check_in'] ) )->diff( new DateTimeImmutable( $r['check_out'] ) )->days;
?>
<div class="wrap paw-admin paw-detail">
	<p class="paw-back"><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::MENU ) ); ?>">&larr; <?php esc_html_e( 'All reservations', 'pawfectly-reservations' ); ?></a></p>
	<?php Admin::notice(); ?>

	<div class="paw-detail__head">
		<div>
			<h1 class="paw-detail__title">
				<?php echo esc_html( $r['pet_name'] ); ?>
				<span class="paw-badge paw-badge--<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( Reservations::label( $r['status'] ) ); ?></span>
			</h1>
			<p class="paw-detail__meta">
				<strong><?php echo esc_html( $r['reference'] ); ?></strong>
				<?php
				/* translators: 1: check-in date, 2: check-out date, 3: number of nights */
				echo esc_html( sprintf( _n( '%1$s → %2$s (%3$d night)', '%1$s → %2$s (%3$d nights)', $paw_nights, 'pawfectly-reservations' ), Mailer::date( $r['check_in'] ), Mailer::date( $r['check_out'] ), $paw_nights ) );
				?>
			</p>
		</div>
		<div class="paw-detail__actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="paw-status-form">
				<input type="hidden" name="action" value="paw_status">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $paw_id ); ?>">
				<?php wp_nonce_field( 'paw_status_' . $paw_id ); ?>
				<label class="screen-reader-text" for="paw-status"><?php esc_html_e( 'Status', 'pawfectly-reservations' ); ?></label>
				<select id="paw-status" name="status">
					<?php foreach ( array_keys( Reservations::STATUSES ) as $paw_status ) : ?>
						<option value="<?php echo esc_attr( $paw_status ); ?>"<?php selected( $r['status'], $paw_status ); ?>><?php echo esc_html( Reservations::label( $paw_status ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="paw-notify"><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e( 'Email the owner about this change', 'pawfectly-reservations' ); ?></label>
				<?php submit_button( __( 'Update status', 'pawfectly-reservations' ), 'primary', 'submit', false ); ?>
			</form>
			<button type="button" class="button" data-paw-print><?php esc_html_e( 'Print', 'pawfectly-reservations' ); ?></button>
		</div>
	</div>

	<div class="paw-detail__grid">
		<div class="paw-detail__main">
			<?php foreach ( $steps as $paw_step ) : ?>
				<section class="paw-card">
					<h2><?php echo esc_html( $paw_step['title'] ); ?></h2>
					<dl class="paw-answers">
						<?php foreach ( $paw_step['fields'] as $paw_f ) : ?>
							<?php
							if ( ! Validator::is_shown( $paw_f, $values ) ) {
								continue;
							}
							$paw_id_f = $paw_f['id'];
							?>
							<?php if ( 'file' === $paw_f['type'] ) : ?>
								<dt><?php echo esc_html( $paw_f['label'] ); ?></dt>
								<dd>
									<ul class="paw-files">
										<?php foreach ( (array) $r['files'] as $paw_n => $paw_file ) : ?>
											<li><a href="<?php echo esc_url( Uploads::url( $paw_id, (string) $paw_n ) ); ?>"><?php echo esc_html( $paw_file['name'] ); ?></a> <span class="paw-muted"><?php echo esc_html( size_format( (int) $paw_file['size'] ) ); ?></span></li>
										<?php endforeach; ?>
									</ul>
								</dd>
							<?php elseif ( 'signature' === $paw_f['type'] ) : ?>
								<dt><?php echo esc_html( $paw_f['label'] ); ?></dt>
								<dd>
									<img class="paw-signature" src="<?php echo esc_url( Uploads::url( $paw_id, 'sig' ) ); ?>" alt="<?php esc_attr_e( "Owner's signature", 'pawfectly-reservations' ); ?>">
									<p class="paw-muted">
										<?php
										/* translators: 1: date and time, 2: IP address */
										echo esc_html( sprintf( __( 'Signed %1$s from %2$s', 'pawfectly-reservations' ), get_date_from_gmt( $r['signed_at'], 'm/d/Y g:i a' ), $r['signed_ip'] ) );
										?>
									</p>
								</dd>
							<?php elseif ( 'agree' === $paw_f['type'] ) : ?>
								<dt><?php echo esc_html( $paw_f['label'] ); ?></dt>
								<dd><?php esc_html_e( 'Agreed to the waiver', 'pawfectly-reservations' ); ?></dd>
							<?php else : ?>
								<?php
								$paw_value = (string) ( $values[ $paw_id_f ] ?? '' );
								$paw_text  = Admin::answer_text( $paw_f, $paw_value );
								$paw_flag  = 'health' === $paw_step['id'] && 'yesno' === $paw_f['type'] && 'yes' === $paw_value && 'vaccinations_current' !== $paw_id_f;
								?>
								<dt><?php echo esc_html( $paw_f['label'] ); ?></dt>
								<dd>
									<?php if ( '' === $paw_text ) : ?>
										<span class="paw-muted">&mdash;</span>
									<?php elseif ( $paw_flag ) : ?>
										<span class="paw-flag"><?php echo esc_html( $paw_text ); ?></span>
									<?php elseif ( 'owner_email' === $paw_id_f ) : ?>
										<a href="<?php echo esc_url( 'mailto:' . $paw_text ); ?>"><?php echo esc_html( $paw_text ); ?></a>
									<?php elseif ( 'tel' === $paw_f['type'] ) : ?>
										<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $paw_text ) ); ?>"><?php echo esc_html( $paw_text ); ?></a>
									<?php else : ?>
										<?php echo nl2br( esc_html( $paw_text ) ); ?>
									<?php endif; ?>
								</dd>
							<?php endif; ?>
						<?php endforeach; ?>
					</dl>
				</section>
			<?php endforeach; ?>
		</div>

		<aside class="paw-detail__side">
			<section class="paw-card">
				<h2><?php esc_html_e( 'Staff notes', 'pawfectly-reservations' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="paw_notes">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $paw_id ); ?>">
					<?php wp_nonce_field( 'paw_notes_' . $paw_id ); ?>
					<label class="screen-reader-text" for="paw-notes"><?php esc_html_e( 'Staff notes', 'pawfectly-reservations' ); ?></label>
					<textarea id="paw-notes" name="staff_notes" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'Only staff see these notes.', 'pawfectly-reservations' ); ?>"><?php echo esc_textarea( (string) $r['staff_notes'] ); ?></textarea>
					<?php submit_button( __( 'Save notes', 'pawfectly-reservations' ), 'secondary', 'submit', false ); ?>
				</form>
			</section>

			<section class="paw-card">
				<h2><?php esc_html_e( 'History', 'pawfectly-reservations' ); ?></h2>
				<ol class="paw-history">
					<?php foreach ( array_reverse( (array) $r['status_log'] ) as $paw_entry ) : ?>
						<?php $paw_user = $paw_entry['user_id'] ? get_userdata( (int) $paw_entry['user_id'] ) : null; ?>
						<li>
							<span class="paw-badge paw-badge--<?php echo esc_attr( $paw_entry['status'] ); ?>"><?php echo esc_html( Reservations::label( $paw_entry['status'] ) ); ?></span>
							<span class="paw-muted">
								<?php echo esc_html( get_date_from_gmt( $paw_entry['at'], 'm/d/Y g:i a' ) ); ?>
								&middot;
								<?php echo esc_html( $paw_user ? $paw_user->display_name : __( 'Submitted by the owner', 'pawfectly-reservations' ) ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
			</section>
		</aside>
	</div>
</div>
