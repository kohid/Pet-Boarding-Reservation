<?php
/**
 * The staff dashboard shell: side menu, Overview and Reservations views, and the side panel.
 * dashboard.js fills it from the staff REST routes.
 *
 * @package Pawfectly
 *
 * @var \WP_User $user
 * @var string   $view       overview|reservations
 * @var array    $config     Dashboard::config().
 * @var bool     $full       Fill the browser window.
 * @var array    $settings   Settings::get().
 * @var string   $logout_url
 */

use Pawfectly\UserMenu;

defined( 'ABSPATH' ) || exit;

$paw_icon = array(
	'overview'     => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
	'reservations' => '<path d="M4 6h2v2H4zm4 0h12v2H8zM4 11h2v2H4zm4 0h12v2H8zM4 16h2v2H4zm4 0h12v2H8z"/>',
	'settings'     => '<path d="M4 7h10v2H4zm12 0h4v2h-4zm-2-2h2v6h-2zM4 15h4v2H4zm6 0h10v2H10zm-2-2h2v6H8z"/>',
);
$paw_tiles = array(
	'check_ins'  => __( 'Check-ins today', 'pawfectly-reservations' ),
	'check_outs' => __( 'Check-outs today', 'pawfectly-reservations' ),
	'boarding'   => __( 'Pets boarding tonight', 'pawfectly-reservations' ),
	'grooming'   => __( 'Grooming today', 'pawfectly-reservations' ),
	'pending'    => __( 'Requests to review', 'pawfectly-reservations' ),
);
?>
<div class="paw-dash<?php echo $full ? ' paw-dash--full' : ''; ?>" data-paw-dash>
	<aside class="paw-dash__side">
		<div class="paw-dash__brand">
			<img src="<?php echo esc_url( $config['brand']['logo'] ); ?>" alt="" width="36" height="36">
			<span><?php echo esc_html( $settings['business_name'] ); ?></span>
		</div>
		<nav class="paw-dash__nav" aria-label="<?php esc_attr_e( 'Dashboard', 'pawfectly-reservations' ); ?>">
			<?php foreach ( array( 'overview', 'reservations' ) as $paw_v ) : ?>
				<a href="<?php echo esc_url( $config['urls'][ $paw_v ] ); ?>" class="paw-dash__nav-item" data-paw-nav="<?php echo esc_attr( $paw_v ); ?>"<?php echo $view === $paw_v ? ' aria-current="page"' : ''; ?>>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo $paw_icon[ $paw_v ]; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed SVG. ?></svg>
					<span><?php echo esc_html( $config['i18n'][ $paw_v ] ); ?></span>
				</a>
			<?php endforeach; ?>
			<?php if ( '' !== $config['urls']['settings'] ) : ?>
				<a href="<?php echo esc_url( $config['urls']['settings'] ); ?>" class="paw-dash__nav-item" data-paw-nav="settings"<?php echo 'settings' === $view ? ' aria-current="page"' : ''; ?>>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo $paw_icon['settings']; // phpcs:ignore WordPress.Security.EscapeOutput ?></svg>
					<span><?php esc_html_e( 'Settings', 'pawfectly-reservations' ); ?></span>
				</a>
			<?php endif; ?>
		</nav>
		<div class="paw-dash__me">
			<span class="paw-dash__avatar" aria-hidden="true"><?php echo esc_html( UserMenu::initials( (string) $user->display_name ) ); ?></span>
			<span class="paw-dash__me-name"><?php echo esc_html( $user->display_name ); ?></span>
			<a class="paw-dash__me-out" href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Log out', 'pawfectly-reservations' ); ?></a>
		</div>
	</aside>

	<main class="paw-dash__main">
		<header class="paw-dash__head">
			<h1 class="paw-dash__title" data-paw-title><?php echo esc_html( $config['i18n'][ $view ] ); ?></h1>
			<p class="paw-dash__date"><?php echo esc_html( wp_date( 'l j F' ) ); ?></p>
		</header>

		<section data-paw-view="overview" aria-labelledby="paw-dash-overview"<?php echo 'overview' === $view ? '' : ' hidden'; ?>>
			<h2 class="screen-reader-text" id="paw-dash-overview"><?php esc_html_e( 'Overview', 'pawfectly-reservations' ); ?></h2>
			<div class="paw-dash__tiles">
				<?php foreach ( $paw_tiles as $paw_key => $paw_label ) : ?>
					<div class="paw-dash__tile paw-dash__tile--<?php echo esc_attr( $paw_key ); ?>" data-paw-tile="<?php echo esc_attr( $paw_key ); ?>">
						<span class="paw-dash__tile-num">–</span>
						<span class="paw-dash__tile-label"><?php echo esc_html( $paw_label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="paw-dash__card">
				<h3 class="paw-dash__card-title"><?php esc_html_e( 'Pets per night, next 14 nights', 'pawfectly-reservations' ); ?></h3>
				<div class="paw-dash__chart" data-paw-chart role="img" aria-label="<?php esc_attr_e( 'Pets per night for the next 14 nights', 'pawfectly-reservations' ); ?>"></div>
			</div>
		</section>

		<section data-paw-view="reservations" aria-labelledby="paw-dash-reservations"<?php echo 'reservations' === $view ? '' : ' hidden'; ?>>
			<h2 class="screen-reader-text" id="paw-dash-reservations"><?php esc_html_e( 'Reservations', 'pawfectly-reservations' ); ?></h2>
			<div class="paw-dash__card">
				<div class="paw-dash__toolbar">
					<label class="paw-dash__search">
						<span class="screen-reader-text"><?php esc_html_e( 'Search reservations', 'pawfectly-reservations' ); ?></span>
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 4a6 6 0 1 0 3.5 10.9l4.3 4.3 1.4-1.4-4.3-4.3A6 6 0 0 0 10 4zm0 2a4 4 0 1 1 0 8 4 4 0 0 1 0-8z"/></svg>
						<input type="search" data-paw-search placeholder="<?php esc_attr_e( 'Search reference, name, email, phone, pet', 'pawfectly-reservations' ); ?>">
					</label>
					<div class="paw-dash__range">
						<label><?php esc_html_e( 'Check-in from', 'pawfectly-reservations' ); ?> <input type="date" data-paw-from></label>
						<label><?php esc_html_e( 'to', 'pawfectly-reservations' ); ?> <input type="date" data-paw-to></label>
					</div>
				</div>
				<div class="paw-dash__tabs" role="tablist" data-paw-status-tabs></div>
				<p class="paw-dash__export"><a class="paw-dash__btn paw-dash__btn--light" data-paw-export href="<?php echo esc_url( $config['urls']['export'] ); ?>"><?php esc_html_e( 'Export CSV', 'pawfectly-reservations' ); ?></a></p>
				<div class="paw-dash__tablewrap">
					<table class="paw-dash__table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Reference', 'pawfectly-reservations' ); ?></th>
								<th><?php esc_html_e( 'Pet', 'pawfectly-reservations' ); ?></th>
								<th><?php esc_html_e( 'Owner', 'pawfectly-reservations' ); ?></th>
								<th><?php esc_html_e( 'Stay', 'pawfectly-reservations' ); ?></th>
								<th><?php esc_html_e( 'Status', 'pawfectly-reservations' ); ?></th>
							</tr>
						</thead>
						<tbody data-paw-rows></tbody>
					</table>
				</div>
				<p class="paw-dash__empty" data-paw-empty hidden></p>
				<div class="paw-dash__pager">
					<span data-paw-showing></span>
					<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-prev-page aria-label="<?php esc_attr_e( 'Previous page', 'pawfectly-reservations' ); ?>">‹</button>
					<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-next-page aria-label="<?php esc_attr_e( 'Next page', 'pawfectly-reservations' ); ?>">›</button>
				</div>
			</div>
		</section>

		<?php if ( '' !== $config['urls']['settings'] ) : ?>
			<section data-paw-view="settings" aria-labelledby="paw-dash-settings"<?php echo 'settings' === $view ? '' : ' hidden'; ?>>
				<h2 class="screen-reader-text" id="paw-dash-settings"><?php esc_html_e( 'Settings', 'pawfectly-reservations' ); ?></h2>
				<?php include PAW_DIR . 'templates/partials/dashboard-settings.php'; ?>
			</section>
		<?php endif; ?>
	</main>

	<div class="paw-dash__overlay" data-paw-overlay hidden></div>
	<aside class="paw-dash__panel" data-paw-panel role="dialog" aria-modal="true" aria-labelledby="paw-panel-title" hidden>
		<header class="paw-dash__panel-head">
			<div>
				<h2 id="paw-panel-title" data-paw-panel-title></h2>
				<p class="paw-dash__panel-sub" data-paw-panel-sub></p>
			</div>
			<button type="button" class="paw-dash__close" data-paw-close aria-label="<?php esc_attr_e( 'Close', 'pawfectly-reservations' ); ?>">×</button>
		</header>
		<div class="paw-dash__panel-body">
			<section class="paw-dash__card paw-dash__card--flat">
				<span class="paw-dash__badge" data-paw-panel-badge></span>
				<div class="paw-dash__status">
					<label class="screen-reader-text" for="paw-dash-status"><?php esc_html_e( 'Status', 'pawfectly-reservations' ); ?></label>
					<select id="paw-dash-status" data-paw-status-select>
						<?php foreach ( $config['statuses'] as $paw_key => $paw_label ) : ?>
							<option value="<?php echo esc_attr( $paw_key ); ?>"><?php echo esc_html( $paw_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="paw-dash__btn" data-paw-status-save><?php esc_html_e( 'Update status', 'pawfectly-reservations' ); ?></button>
				</div>
				<label class="paw-dash__check"><input type="checkbox" data-paw-notify checked> <?php esc_html_e( 'Email the owner about this change', 'pawfectly-reservations' ); ?></label>
			</section>
			<section class="paw-dash__card paw-dash__card--flat">
				<h3 class="paw-dash__card-title"><?php esc_html_e( 'Vaccination records', 'pawfectly-reservations' ); ?></h3>
				<div class="paw-dash__files" data-paw-files></div>
				<h3 class="paw-dash__card-title"><?php esc_html_e( 'Signature', 'pawfectly-reservations' ); ?></h3>
				<img class="paw-dash__signature" data-paw-signature alt="<?php esc_attr_e( "Owner's signature", 'pawfectly-reservations' ); ?>">
			</section>
			<div class="paw-dash__answers" data-paw-answers></div>
			<section class="paw-dash__card paw-dash__card--flat">
				<h3 class="paw-dash__card-title"><label for="paw-dash-notes"><?php esc_html_e( 'Staff notes', 'pawfectly-reservations' ); ?></label></h3>
				<textarea id="paw-dash-notes" rows="4" data-paw-notes placeholder="<?php esc_attr_e( 'Only staff see these notes.', 'pawfectly-reservations' ); ?>"></textarea>
				<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-notes-save><?php esc_html_e( 'Save notes', 'pawfectly-reservations' ); ?></button>
			</section>
			<section class="paw-dash__card paw-dash__card--flat">
				<h3 class="paw-dash__card-title"><?php esc_html_e( 'History', 'pawfectly-reservations' ); ?></h3>
				<ol class="paw-dash__history" data-paw-history></ol>
			</section>
			<div class="paw-dash__panel-foot">
				<a class="paw-dash__link" data-paw-admin-link href="#"><?php esc_html_e( 'Open in wp-admin (print)', 'pawfectly-reservations' ); ?></a>
				<button type="button" class="paw-dash__btn paw-dash__btn--danger" data-paw-delete><?php esc_html_e( 'Delete permanently', 'pawfectly-reservations' ); ?></button>
			</div>
		</div>
	</aside>
	<div class="paw-dash__lightbox" data-paw-lightbox role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Vaccination record', 'pawfectly-reservations' ); ?>" hidden>
		<figure class="paw-dash__lightbox-figure">
			<img class="paw-dash__lightbox-img" data-paw-lightbox-img alt="">
			<figcaption class="paw-dash__lightbox-caption">
				<span data-paw-lightbox-name></span>
				<span class="paw-dash__lightbox-count" data-paw-lightbox-count></span>
				<a class="paw-dash__lightbox-download" data-paw-lightbox-download href="#" download><?php esc_html_e( 'Download', 'pawfectly-reservations' ); ?></a>
			</figcaption>
		</figure>
		<button type="button" class="paw-dash__lightbox-btn paw-dash__lightbox-btn--close" data-paw-lightbox-close aria-label="<?php esc_attr_e( 'Close', 'pawfectly-reservations' ); ?>">&times;</button>
		<button type="button" class="paw-dash__lightbox-btn paw-dash__lightbox-btn--prev" data-paw-lightbox-prev aria-label="<?php esc_attr_e( 'Previous image', 'pawfectly-reservations' ); ?>">&lsaquo;</button>
		<button type="button" class="paw-dash__lightbox-btn paw-dash__lightbox-btn--next" data-paw-lightbox-next aria-label="<?php esc_attr_e( 'Next image', 'pawfectly-reservations' ); ?>">&rsaquo;</button>
	</div>
</div>
