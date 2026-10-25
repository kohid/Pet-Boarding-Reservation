<?php
/**
 * Every answer of one reservation, one card per form step. Shared by the wp-admin detail view
 * and the front-end staff dashboard's side panel.
 *
 * @package Pawfectly
 *
 * @var array $r      Reservations::find() row.
 * @var array $steps  Fields::steps().
 * @var array $values Reservations::values( $r ).
 */

use Pawfectly\Admin;
use Pawfectly\Uploads;
use Pawfectly\Validator;

defined( 'ABSPATH' ) || exit;

$paw_id = (int) $r['id'];
?>
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
					<dd>
						<?php esc_html_e( 'Agreed to the waiver below', 'pawfectly-reservations' ); ?>
						<?php if ( ! empty( $r['details']['waiver_text'] ) ) : ?>
							<div class="paw-waiver-signed"><?php echo wp_kses_post( $r['details']['waiver_text'] ); ?></div>
						<?php endif; ?>
					</dd>
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
