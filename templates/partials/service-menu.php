<?php
/**
 * The welcome screen's menu: boarding and every offered service with its price range. For looking
 * only; each pet picks its service on the Reservation Details step.
 *
 * @package Pawfectly
 *
 * @var array $settings Settings::get().
 */

use Pawfectly\Services;

defined( 'ABSPATH' ) || exit;

$paw_rates = array_filter(
	array_map(
		static fn( $r ) => '' === (string) $r ? null : (float) $r,
		array_intersect_key( (array) $settings['boarding_rates'], array_flip( array( 'small', 'large', 'other' ) ) )
	),
	'is_float'
);
$paw_range = static function ( array $svc ): string {
	$text = Services::price_range( $svc );
	if ( '' === $text ) {
		return __( 'Price on request', 'pawfectly-reservations' );
	}
	return $svc['monthly'] ? $text . ' ' . __( '/ month', 'pawfectly-reservations' ) : $text;
};
$paw_groups = array(
	__( 'Single visit', 'pawfectly-reservations' )        => array_filter( Services::enabled(), static fn( array $s ): bool => ! $s['monthly'] ),
	__( 'Monthly memberships', 'pawfectly-reservations' ) => array_filter( Services::enabled(), static fn( array $s ): bool => $s['monthly'] ),
);
?>
<div class="paw-menu" data-paw-menu>
	<div class="paw-menu__item paw-menu__item--boarding">
		<span class="paw-menu__name"><?php esc_html_e( 'Boarding', 'pawfectly-reservations' ); ?></span>
		<span class="paw-menu__price">
			<?php
			echo esc_html(
				$paw_rates
					/* translators: %s: lowest nightly rate */
					? sprintf( __( 'from %s / night', 'pawfectly-reservations' ), Services::money( min( $paw_rates ) ) )
					: __( 'Price on request', 'pawfectly-reservations' )
			);
			?>
		</span>
	</div>
	<?php foreach ( $paw_groups as $paw_group_title => $paw_services ) : ?>
		<?php if ( $paw_services ) : ?>
			<p class="paw-menu__group"><?php echo esc_html( $paw_group_title ); ?></p>
			<?php foreach ( $paw_services as $paw_svc ) : ?>
				<div class="paw-menu__item">
					<span class="paw-menu__name"><?php echo esc_html( $paw_svc['name'] ); ?></span>
					<span class="paw-menu__price"><?php echo esc_html( $paw_range( $paw_svc ) ); ?></span>
					<?php if ( '' !== $paw_svc['time'] ) : ?>
						<span class="paw-menu__time"><?php echo esc_html( $paw_svc['time'] ); ?></span>
					<?php endif; ?>
					<?php if ( $paw_svc['included'] ) : ?>
						<details class="paw-svc__more paw-menu__more">
							<summary><?php esc_html_e( "What's included", 'pawfectly-reservations' ); ?></summary>
							<ul>
								<?php foreach ( $paw_svc['included'] as $paw_item ) : ?>
									<li><?php echo esc_html( $paw_item ); ?></li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	<?php endforeach; ?>
	<p class="paw-menu__note"><?php esc_html_e( "Grooming is for dogs and cats. You'll choose a service for each pet later.", 'pawfectly-reservations' ); ?></p>
</div>
