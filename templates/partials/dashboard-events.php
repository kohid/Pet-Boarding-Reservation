<?php
/**
 * Dashboard → Events: the list, and the Add / Edit editor with its repeatable sections.
 * events-admin.js fills both from the staff routes.
 *
 * @package Pawfectly
 *
 * @var array $config Dashboard::config().
 */

use Pawfectly\Events;

defined( 'ABSPATH' ) || exit;

$paw_ev_i18n = $config['events']['i18n'];
$paw_tabs    = array(
	'upcoming' => __( 'Upcoming', 'pawfectly-reservations' ),
	'drafts'   => __( 'Drafts', 'pawfectly-reservations' ),
	'past'     => __( 'Past', 'pawfectly-reservations' ),
	'all'      => __( 'All', 'pawfectly-reservations' ),
);
$paw_defaults = Events::defaults()['sections'];

/** One labelled input: $key is the event field or row field, $attrs extra attributes. */
$paw_input = static function ( string $label, string $html, string $key, string $class = '' ): void {
	?>
	<label class="paw-dash__field<?php echo '' !== $class ? ' ' . esc_attr( $class ) : ''; ?>" data-paw-ev-key="<?php echo esc_attr( $key ); ?>">
		<span><?php echo esc_html( $label ); ?></span>
		<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts by the caller. ?>
	</label>
	<?php
};
/** An emoji input with its picker button. */
$paw_emoji = static function ( string $key, string $id, string $label ): string {
	return '<span class="paw-dash__ev-emoji"><input type="text" class="paw-dash__ev-emoji-input" id="' . esc_attr( $id ) . '" data-paw-ev-' . ( str_contains( $key, '.' ) ? 'row-field="emoji"' : 'field="' . esc_attr( $key ) . '"' ) . ' maxlength="16" placeholder="🐾" aria-label="' . esc_attr( $label ) . '">'
		. '<button type="button" class="paw-dash__btn paw-dash__btn--light paw-dash__ev-emoji-btn" data-emoji-trigger data-emoji-target="#' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Choose an emoji', 'pawfectly-reservations' ) . '">😀</button></span>';
};
/** Row controls: drag handle, move up/down, remove. */
$paw_row_tools = static function (): string {
	return '<div class="paw-dash__ev-row-tools">'
		. '<span class="paw-dash__ev-drag" data-paw-ev-drag title="' . esc_attr__( 'Drag to reorder', 'pawfectly-reservations' ) . '" aria-hidden="true">⋮⋮</span>'
		. '<button type="button" class="paw-dash__ev-tool" data-paw-ev-up aria-label="' . esc_attr__( 'Move up', 'pawfectly-reservations' ) . '">↑</button>'
		. '<button type="button" class="paw-dash__ev-tool" data-paw-ev-down aria-label="' . esc_attr__( 'Move down', 'pawfectly-reservations' ) . '">↓</button>'
		. '<button type="button" class="paw-dash__ev-tool paw-dash__ev-tool--remove" data-paw-ev-remove aria-label="' . esc_attr__( 'Remove', 'pawfectly-reservations' ) . '">✕</button>'
		. '</div>';
};
$paw_sections = array(
	'sessions'     => array( __( 'Sessions', 'pawfectly-reservations' ), __( '+ Add session', 'pawfectly-reservations' ), true ),
	'includes'     => array( __( 'Ticket includes', 'pawfectly-reservations' ), __( '+ Add item', 'pawfectly-reservations' ), true ),
	'goodies'      => array( __( 'Extra goodies', 'pawfectly-reservations' ), __( '+ Add goodie', 'pawfectly-reservations' ), false ),
	'good_to_know' => array( __( 'Good to know', 'pawfectly-reservations' ), __( '+ Add item', 'pawfectly-reservations' ), false ),
);
?>
<div class="paw-dash__card" data-paw-ev-list>
	<div class="paw-dash__toolbar">
		<label class="paw-dash__search">
			<span class="screen-reader-text"><?php esc_html_e( 'Search events', 'pawfectly-reservations' ); ?></span>
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 4a6 6 0 1 0 3.5 10.9l4.3 4.3 1.4-1.4-4.3-4.3A6 6 0 0 0 10 4zm0 2a4 4 0 1 1 0 8 4 4 0 0 1 0-8z"/></svg>
			<input type="search" data-paw-ev-search placeholder="<?php esc_attr_e( 'Search events', 'pawfectly-reservations' ); ?>">
		</label>
		<button type="button" class="paw-dash__btn" data-paw-ev-new><?php echo esc_html( $paw_ev_i18n['addEvent'] ); ?></button>
	</div>
	<div class="paw-dash__tabs" role="tablist">
		<?php foreach ( $paw_tabs as $paw_key => $paw_label ) : ?>
			<button type="button" class="paw-dash__tab" role="tab" data-paw-ev-tab="<?php echo esc_attr( $paw_key ); ?>" aria-selected="<?php echo 'upcoming' === $paw_key ? 'true' : 'false'; ?>"><?php echo esc_html( $paw_label ); ?></button>
		<?php endforeach; ?>
	</div>
	<ul class="paw-dash__ev-rows" data-paw-ev-rows></ul>
	<p class="paw-dash__empty" data-paw-ev-empty hidden><?php echo esc_html( $paw_ev_i18n['empty'] ); ?></p>
	<div class="paw-dash__pager">
		<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-ev-prev-page aria-label="<?php esc_attr_e( 'Previous page', 'pawfectly-reservations' ); ?>">‹</button>
		<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-ev-next-page aria-label="<?php esc_attr_e( 'Next page', 'pawfectly-reservations' ); ?>">›</button>
	</div>
</div>

<form class="paw-dash__ev-form" data-paw-ev-form novalidate hidden>
	<div class="paw-dash__ev-head">
		<button type="button" class="paw-dash__link paw-dash__ev-back" data-paw-ev-back>← <?php esc_html_e( 'All events', 'pawfectly-reservations' ); ?></button>
		<h3 class="paw-dash__ev-title" data-paw-ev-heading><?php echo esc_html( $paw_ev_i18n['addEvent'] ); ?></h3>
		<span class="paw-dash__badge" data-paw-ev-status hidden></span>
	</div>
	<p class="paw-dash__savemsg is-error" data-paw-ev-msg hidden role="alert"></p>

	<section class="paw-dash__card">
		<h4 class="paw-dash__card-title"><?php esc_html_e( 'Basics', 'pawfectly-reservations' ); ?></h4>
		<div class="paw-dash__fields">
			<?php
			$paw_input( __( 'Emoji', 'pawfectly-reservations' ), $paw_emoji( 'emoji', 'paw-ev-emoji', __( 'Emoji', 'pawfectly-reservations' ) ), 'emoji' );
			$paw_input( __( 'Title', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-field="title" maxlength="120" required placeholder="' . esc_attr__( 'X-Mas Party', 'pawfectly-reservations' ) . '">', 'title' );
			$paw_input( __( 'Tagline', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-field="tagline" maxlength="160" placeholder="' . esc_attr__( 'A jingle-bark holiday party for dogs of every breed', 'pawfectly-reservations' ) . '">', 'tagline', 'paw-dash__field--wide' );
			$paw_input( __( 'Category', 'pawfectly-reservations' ), '<select data-paw-ev-field="category" data-paw-ev-categories></select>', 'category' );
			?>
			<div class="paw-dash__field" data-paw-ev-key="photo_id">
				<span><?php esc_html_e( 'Cover photo (optional)', 'pawfectly-reservations' ); ?></span>
				<div class="paw-dash__ev-photo" data-paw-ev-photo>
					<input type="hidden" data-paw-ev-field="photo_id" value="0">
					<img alt="" data-paw-ev-photo-img hidden>
					<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-ev-photo-choose><?php echo esc_html( $paw_ev_i18n['choosePhoto'] ); ?></button>
					<button type="button" class="paw-dash__link" data-paw-ev-photo-remove hidden><?php esc_html_e( 'Remove', 'pawfectly-reservations' ); ?></button>
				</div>
			</div>
		</div>
	</section>

	<section class="paw-dash__card">
		<h4 class="paw-dash__card-title"><?php esc_html_e( 'When and where', 'pawfectly-reservations' ); ?></h4>
		<div class="paw-dash__fields paw-dash__fields--4">
			<?php
			$paw_input( __( 'Starts', 'pawfectly-reservations' ), '<input type="date" data-paw-ev-field="start_date" required>', 'start' );
			$paw_input( __( 'Start time', 'pawfectly-reservations' ), '<input type="time" data-paw-ev-field="start_time" required>', 'start_time' );
			$paw_input( __( 'Ends', 'pawfectly-reservations' ), '<input type="date" data-paw-ev-field="end_date" required>', 'end' );
			$paw_input( __( 'End time', 'pawfectly-reservations' ), '<input type="time" data-paw-ev-field="end_time" required>', 'end_time' );
			?>
		</div>
		<div class="paw-dash__fields">
			<?php
			$paw_input( __( 'Venue', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-field="venue" maxlength="120" required placeholder="' . esc_attr__( 'Paw-radise Indoor Dog Park', 'pawfectly-reservations' ) . '">', 'venue' );
			$paw_input( __( 'Address (optional)', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-field="address" maxlength="200" placeholder="' . esc_attr__( 'Estero, FL', 'pawfectly-reservations' ) . '">', 'address' );
			?>
		</div>
	</section>

	<section class="paw-dash__card">
		<h4 class="paw-dash__card-title"><?php esc_html_e( 'Price and tickets', 'pawfectly-reservations' ); ?></h4>
		<div class="paw-dash__fields">
			<?php
			$paw_input( __( 'Price per dog', 'pawfectly-reservations' ), '<input type="text" inputmode="decimal" data-paw-ev-field="price" placeholder="15">', 'price', 'paw-dash__field--narrow' );
			?>
			<label class="paw-dash__check paw-dash__ev-free" data-paw-ev-key="free"><input type="checkbox" data-paw-ev-field="free"> <?php esc_html_e( 'Free event', 'pawfectly-reservations' ); ?></label>
			<?php
			$paw_input( __( 'Ticket link (optional)', 'pawfectly-reservations' ), '<input type="url" data-paw-ev-field="ticket_url" placeholder="https://"><small>' . esc_html__( 'Get Tickets and Reserve Your Spot go here. Without one, the page says sign-ups open soon.', 'pawfectly-reservations' ) . '</small>', 'ticket_url', 'paw-dash__field--wide' );
			$paw_input( __( 'Highlight note (optional)', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-field="note" maxlength="200" placeholder="' . esc_attr__( '🐾 FREE for Dog Park Members — call or text us for your coupon code', 'pawfectly-reservations' ) . '"><small>' . esc_html__( 'Shown under the ticket button. Phone numbers become tap-to-call links.', 'pawfectly-reservations' ) . '</small>', 'note', 'paw-dash__field--wide' );
			?>
		</div>
	</section>

	<section class="paw-dash__card">
		<h4 class="paw-dash__card-title"><label for="paw-ev-description"><?php esc_html_e( 'Description', 'pawfectly-reservations' ); ?></label></h4>
		<div class="paw-dash__field" data-paw-ev-key="description">
			<textarea id="paw-ev-description" rows="6" data-paw-ev-field="description" placeholder="<?php esc_attr_e( 'Deck the paws! Join us for a holiday celebration…', 'pawfectly-reservations' ); ?>"></textarea>
			<small><?php esc_html_e( 'You can use paragraphs (<p>), bold, italic, lists and links.', 'pawfectly-reservations' ); ?></small>
		</div>
	</section>

	<?php foreach ( $paw_sections as $paw_name => $paw_meta ) : ?>
		<section class="paw-dash__card paw-dash__ev-section" data-paw-ev-section="<?php echo esc_attr( $paw_name ); ?>">
			<h4 class="paw-dash__card-title"><?php echo esc_html( $paw_meta[0] ); ?></h4>
			<div class="paw-dash__fields">
				<?php
				$paw_input( __( 'Heading', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-part="heading" placeholder="' . esc_attr( $paw_defaults[ $paw_name ]['heading'] ) . '">', "sections.$paw_name.heading", $paw_meta[2] ? '' : 'paw-dash__field--wide' );
				if ( $paw_meta[2] ) {
					$paw_input( __( 'Intro', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-part="intro" placeholder="' . esc_attr( $paw_defaults[ $paw_name ]['intro'] ) . '">', "sections.$paw_name.intro" );
				}
				?>
			</div>
			<?php if ( 'includes' === $paw_name ) : ?>
				<p class="paw-dash__hint"><?php esc_html_e( '{price} in the heading or intro shows the ticket price, like "$15".', 'pawfectly-reservations' ); ?></p>
			<?php endif; ?>
			<p class="paw-dash__error" data-paw-ev-section-error hidden></p>
			<ol class="paw-dash__ev-rowlist" data-paw-ev-rows-for="<?php echo esc_attr( $paw_name ); ?>"></ol>
			<p class="paw-dash__hint" data-paw-ev-none><?php esc_html_e( 'Leave empty to hide this section.', 'pawfectly-reservations' ); ?></p>
			<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-ev-add="<?php echo esc_attr( $paw_name ); ?>"><?php echo esc_html( $paw_meta[1] ); ?></button>
			<template data-paw-ev-row-template="<?php echo esc_attr( $paw_name ); ?>">
				<li class="paw-dash__ev-row" data-paw-ev-row draggable="true">
					<?php echo $paw_row_tools(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
					<div class="paw-dash__ev-row-fields paw-dash__ev-row-fields--<?php echo esc_attr( $paw_name ); ?>">
						<?php if ( 'sessions' === $paw_name ) : ?>
							<?php
							$paw_input( __( 'From', 'pawfectly-reservations' ), '<input type="time" data-paw-ev-row-field="from">', 'from' );
							$paw_input( __( 'To', 'pawfectly-reservations' ), '<input type="time" data-paw-ev-row-field="to">', 'to' );
							$paw_input( __( 'Name', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-row-field="name" maxlength="300" placeholder="' . esc_attr__( 'Session 1', 'pawfectly-reservations' ) . '">', 'name' );
							$paw_input( __( "Who it's for", 'pawfectly-reservations' ), '<input type="text" data-paw-ev-row-field="who" maxlength="300" placeholder="' . esc_attr__( 'Small dogs (under 30 lbs)', 'pawfectly-reservations' ) . '">', 'who' );
							?>
						<?php else : ?>
							<?php $paw_input( __( 'Emoji', 'pawfectly-reservations' ), $paw_emoji( 'row.emoji', 'paw-ev-__ID__', __( 'Emoji', 'pawfectly-reservations' ) ), 'emoji' ); ?>
							<?php if ( 'goodies' === $paw_name ) : ?>
								<?php $paw_input( __( 'Title', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-row-field="title" maxlength="300" placeholder="' . esc_attr__( 'Holiday Photo Booth', 'pawfectly-reservations' ) . '">', 'title' ); ?>
							<?php endif; ?>
							<?php $paw_input( 'goodies' === $paw_name ? __( 'Text (optional)', 'pawfectly-reservations' ) : __( 'Text', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-row-field="text" maxlength="300">', 'text', 'paw-dash__ev-row-text' ); ?>
						<?php endif; ?>
					</div>
				</li>
			</template>
		</section>
	<?php endforeach; ?>

	<section class="paw-dash__card paw-dash__ev-section" data-paw-ev-section="closing">
		<h4 class="paw-dash__card-title"><?php esc_html_e( 'Closing call to action', 'pawfectly-reservations' ); ?></h4>
		<div class="paw-dash__fields">
			<?php
			$paw_input( __( 'Heading', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-part="heading" placeholder="' . esc_attr( $paw_defaults['closing']['heading'] ) . '">', 'sections.closing.heading' );
			$paw_input( __( 'Text', 'pawfectly-reservations' ), '<input type="text" data-paw-ev-part="intro" placeholder="' . esc_attr( $paw_defaults['closing']['intro'] ) . '">', 'sections.closing.intro' );
			?>
		</div>
	</section>

	<div class="paw-dash__ev-actions">
		<button type="button" class="paw-dash__btn paw-dash__btn--light" data-paw-ev-save="draft"><?php esc_html_e( 'Save draft', 'pawfectly-reservations' ); ?></button>
		<button type="button" class="paw-dash__btn" data-paw-ev-save="publish"><?php echo esc_html( $paw_ev_i18n['publish'] ); ?></button>
		<a class="paw-dash__link" data-paw-ev-preview href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Preview', 'pawfectly-reservations' ); ?></a>
		<span class="paw-dash__ev-saved" data-paw-ev-saved role="status"></span>
		<button type="button" class="paw-dash__btn paw-dash__btn--danger paw-dash__ev-delete" data-paw-ev-delete hidden><?php esc_html_e( 'Delete', 'pawfectly-reservations' ); ?></button>
	</div>
</form>
