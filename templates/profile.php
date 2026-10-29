<?php
/**
 * My Profile for a signed-in user: details, My Pets, details for bookings, recent activity and
 * password. Signed out, Profile shows the login panel. Forms post JSON to the account REST routes
 * (account.js); fields carry data-paw-field for errors. My Pets is drawn by account.js.
 *
 * @package Pawfectly
 *
 * @var \WP_User $user
 * @var array    $links  bookings, book.
 * @var string   $phone
 * @var array    $pets   Profile::pets_config().
 * @var array    $info   SavedPets::info().
 * @var array    $recent reservations, signups.
 */

use Pawfectly\Profile;
use Pawfectly\Reservations;
use Pawfectly\SavedPets;

defined( 'ABSPATH' ) || exit;

$paw_field = include PAW_DIR . 'templates/partials/acct-field.php';
$paw_input = include PAW_DIR . 'templates/partials/acct-input.php';
$paw_info  = SavedPets::info_fields();
$paw_parts = array(
	__( 'Home address', 'pawfectly-reservations' )      => array( 'address_street', 'address_city', 'address_state', 'address_postcode', 'address_country' ),
	__( 'Emergency contact', 'pawfectly-reservations' ) => array( 'emergency_name', 'emergency_phone', 'emergency_relationship' ),
	__( 'Veterinarian', 'pawfectly-reservations' )      => array( 'vet_name', 'vet_clinic', 'vet_phone', 'vet_authorization' ),
);
?>
<div class="paw-acct paw-acct--profile" data-paw-acct>
	<?php echo Profile::notices( (int) $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
	<form class="paw-acct__card" data-paw-form="account/profile" novalidate>
		<h2><?php esc_html_e( 'My Profile', 'pawfectly-reservations' ); ?></h2>
		<div class="paw-acct__grid paw-acct__grid--2">
			<?php $paw_field( 'first_name', __( 'First name', 'pawfectly-reservations' ), 'text', (string) $user->first_name, 'given-name' ); ?>
			<?php $paw_field( 'last_name', __( 'Last name', 'pawfectly-reservations' ), 'text', (string) $user->last_name, 'family-name' ); ?>
			<?php $paw_field( 'email', __( 'Email', 'pawfectly-reservations' ), 'email', (string) $user->user_email, 'email' ); ?>
			<?php $paw_field( 'phone', __( 'Phone', 'pawfectly-reservations' ), 'tel', $phone, 'tel' ); ?>
		</div>
		<p class="paw-acct__msg" data-paw-msg role="status" hidden></p>
		<div class="paw-acct__actions">
			<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Save details', 'pawfectly-reservations' ); ?></button>
			<?php if ( '' !== $links['bookings'] ) : ?>
				<a class="paw-acct__link" href="<?php echo esc_url( $links['bookings'] ); ?>"><?php esc_html_e( 'My Bookings', 'pawfectly-reservations' ); ?></a>
			<?php endif; ?>
		</div>
	</form>

	<section class="paw-acct__card paw-acct__pets" data-paw-pets data-config="<?php echo esc_attr( (string) wp_json_encode( $pets ) ); ?>">
		<div class="paw-acct__card-head">
			<h2><?php esc_html_e( 'My Pets', 'pawfectly-reservations' ); ?></h2>
			<p class="paw-acct__muted"><?php esc_html_e( 'Saved here, your pets are filled in for you on the booking form. Vaccination records are still uploaded with each booking.', 'pawfectly-reservations' ); ?></p>
		</div>
		<ul class="paw-acct__petlist" data-paw-petlist></ul>
		<div data-paw-pet-editor hidden></div>
		<p class="paw-acct__msg" data-paw-pets-msg role="status" hidden></p>
		<button type="button" class="paw-acct__btn paw-acct__btn--ghost" data-paw-pet-add></button>
	</section>

	<form class="paw-acct__card" data-paw-form="account/info" novalidate>
		<h2><?php esc_html_e( 'Details for bookings', 'pawfectly-reservations' ); ?></h2>
		<p class="paw-acct__muted"><?php esc_html_e( 'Filled in on the booking form for you. You can still change them there.', 'pawfectly-reservations' ); ?></p>
		<?php foreach ( $paw_parts as $paw_title => $paw_ids ) : ?>
			<h3 class="paw-acct__subhead"><?php echo esc_html( $paw_title ); ?></h3>
			<div class="paw-acct__grid paw-acct__grid--2">
				<?php
				foreach ( $paw_ids as $paw_id ) {
					if ( isset( $paw_info[ $paw_id ] ) ) {
						$paw_input( $paw_info[ $paw_id ], (string) ( $info[ $paw_id ] ?? '' ) );
					}
				}
				?>
			</div>
		<?php endforeach; ?>
		<p class="paw-acct__msg" data-paw-msg role="status" hidden></p>
		<div class="paw-acct__actions">
			<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Save details for bookings', 'pawfectly-reservations' ); ?></button>
		</div>
	</form>

	<section class="paw-acct__card paw-acct__activity">
		<h2><?php esc_html_e( 'Recent activity', 'pawfectly-reservations' ); ?></h2>
		<?php if ( ! $recent['reservations'] && ! $recent['signups'] ) : ?>
			<p class="paw-acct__muted"><?php esc_html_e( 'No bookings or event sign-ups yet.', 'pawfectly-reservations' ); ?></p>
		<?php endif; ?>
		<?php if ( $recent['reservations'] ) : ?>
			<h3 class="paw-acct__subhead"><?php esc_html_e( 'Reservations', 'pawfectly-reservations' ); ?></h3>
			<ul class="paw-acct__activitylist">
				<?php foreach ( $recent['reservations'] as $paw_r ) : ?>
					<li>
						<span class="paw-acct__activity-main"><strong><?php echo esc_html( implode( ', ', array_filter( array_column( Reservations::pets( $paw_r ), 'pet_name' ), 'strlen' ) ) ); ?></strong> <span class="paw-acct__muted"><?php echo esc_html( Reservations::when( $paw_r ) . ' · ' . $paw_r['reference'] ); ?></span></span>
						<span class="paw-badge paw-badge--<?php echo esc_attr( $paw_r['status'] ); ?>"><?php echo esc_html( Reservations::label( $paw_r['status'] ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $recent['signups'] ) : ?>
			<h3 class="paw-acct__subhead"><?php esc_html_e( 'Event sign-ups', 'pawfectly-reservations' ); ?></h3>
			<ul class="paw-acct__activitylist">
				<?php foreach ( $recent['signups'] as $paw_su ) : ?>
					<li>
						<span class="paw-acct__activity-main"><strong><?php echo esc_html( trim( $paw_su['emoji'] . ' ' . $paw_su['event'] ) ); ?></strong> <span class="paw-acct__muted"><?php echo esc_html( $paw_su['when'] . ' · ' . $paw_su['dogs'] ); ?></span></span>
						<span class="paw-badge paw-badge--<?php echo esc_attr( $paw_su['status'] ); ?>"><?php echo esc_html( $paw_su['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<div class="paw-acct__actions">
			<?php if ( '' !== $links['book'] ) : ?>
				<a class="paw-acct__btn" href="<?php echo esc_url( $links['book'] ); ?>"><?php esc_html_e( 'Book a stay', 'pawfectly-reservations' ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $links['bookings'] ) : ?>
				<a class="paw-acct__link" href="<?php echo esc_url( $links['bookings'] ); ?>"><?php esc_html_e( 'See all in My Bookings', 'pawfectly-reservations' ); ?></a>
			<?php endif; ?>
		</div>
	</section>

	<form class="paw-acct__card" data-paw-form="account/password" novalidate>
		<h2><?php esc_html_e( 'Change password', 'pawfectly-reservations' ); ?></h2>
		<div class="paw-acct__grid">
			<?php $paw_field( 'current', __( 'Current password', 'pawfectly-reservations' ), 'password', '', 'current-password' ); ?>
			<?php $paw_field( 'password', __( 'New password (at least 8 characters)', 'pawfectly-reservations' ), 'password', '', 'new-password' ); ?>
			<?php $paw_field( 'confirm', __( 'Repeat new password', 'pawfectly-reservations' ), 'password', '', 'new-password' ); ?>
		</div>
		<p class="paw-acct__msg" data-paw-msg role="status" hidden></p>
		<div class="paw-acct__actions">
			<button type="submit" class="paw-acct__btn"><?php esc_html_e( 'Change password', 'pawfectly-reservations' ); ?></button>
		</div>
	</form>
</div>
