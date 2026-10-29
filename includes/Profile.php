<?php
/**
 * [pawfectly_my_profile]: signed in, details and password forms for everyone, My Pets and
 * details for bookings (SavedPets, so the booking form fills them in), and recent activity;
 * signed out, the login panel in place (the same one [pawfectly_login] shows).
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Profile {

	public const TAG = 'pawfectly_my_profile';

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/** Styles, script and config shared by My Profile and My Bookings. */
	public static function enqueue(): void {
		wp_enqueue_style( 'paw-account', PAW_URL . 'assets/css/account.css', array(), PAW_VERSION );
		wp_enqueue_script( 'paw-account', PAW_URL . 'assets/js/account.js', array(), PAW_VERSION, true );
		wp_add_inline_script( 'paw-account', 'window.PAW_ACCOUNT = ' . wp_json_encode( self::config() ) . ';', 'before' );
		Login::password_toggle();
	}

	public static function config(): array {
		return array(
			'rest'  => rest_url( Rest::NS . '/' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'  => array(
				'failed'        => __( 'Something went wrong. Please try again.', 'pawfectly-reservations' ),
				'network'       => __( "We couldn't reach the server. Please try again.", 'pawfectly-reservations' ),
				'saving'        => __( 'Saving…', 'pawfectly-reservations' ),
				'mismatch'      => __( "The two passwords don't match.", 'pawfectly-reservations' ),
				'confirmCancel' => __( 'Cancel this reservation? This cannot be undone online.', 'pawfectly-reservations' ),
				'confirmCancelSignup' => __( 'Cancel this event sign-up? Your spots go to someone else.', 'pawfectly-reservations' ),
			),
		);
	}

	/** The verification notices shown on both account pages. */
	public static function notices( int $user_id ): string {
		$html = '';
		$flag = isset( $_GET['paw_verified'] ) ? sanitize_key( wp_unslash( $_GET['paw_verified'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		if ( '1' === $flag ) {
			$html .= '<p class="paw-acct__notice">' . esc_html__( 'Thanks — your email is confirmed.', 'pawfectly-reservations' ) . '</p>';
		} elseif ( 'signin' === $flag ) {
			$html .= '<p class="paw-acct__notice">' . esc_html__( 'Sign in to confirm your email address.', 'pawfectly-reservations' ) . '</p>';
		} elseif ( 'wrong' === $flag ) {
			$html .= '<p class="paw-acct__notice paw-acct__notice--error">' . esc_html__( 'This confirmation link belongs to a different account. Log out, then open the link again.', 'pawfectly-reservations' ) . '</p>';
		} elseif ( '0' === $flag ) {
			$html .= '<p class="paw-acct__notice paw-acct__notice--error">' . esc_html__( 'That confirmation link has expired or was already used.', 'pawfectly-reservations' ) . '</p>';
		}
		if ( $user_id && ! Accounts::is_verified( $user_id ) ) {
			$html .= '<p class="paw-acct__notice paw-acct__notice--error">' . esc_html__( 'Please confirm your email address with the link we sent you.', 'pawfectly-reservations' )
				. ' <button type="button" class="paw-acct__link" data-paw-resend>' . esc_html__( 'Send a new link', 'pawfectly-reservations' ) . '</button></p>';
		}
		return $html;
	}

	/** What account.js needs for My Pets: the saved pets and the booking form's pet questions. */
	public static function pets_config( int $user_id ): array {
		$groups = array();
		foreach ( SavedPets::pet_groups() as $paw_step => $group ) {
			$fields = array();
			foreach ( $group[1] as $f ) {
				$fields[] = array(
					'id'          => $f['id'],
					'label'       => $f['label'],
					'type'        => $f['type'],
					'options'     => (object) $f['options'],
					'showIf'      => (object) array_intersect_key( $f['show_if'], SavedPets::pet_fields() ),
					'placeholder' => (string) $f['placeholder'],
					'max'         => (int) $f['max'],
				);
			}
			$groups[] = array( 'title' => $group[0], 'fields' => $fields );
		}
		return array(
			'pets'   => SavedPets::get( $user_id ),
			'groups' => $groups,
			'max'    => SavedPets::MAX,
			'i18n'   => array(
				'add'      => __( '+ Add a pet', 'pawfectly-reservations' ),
				'edit'     => __( 'Edit', 'pawfectly-reservations' ),
				'remove'   => __( 'Remove', 'pawfectly-reservations' ),
				'save'     => __( 'Save pet', 'pawfectly-reservations' ),
				'cancel'   => __( 'Cancel', 'pawfectly-reservations' ),
				'newPet'   => __( 'New pet', 'pawfectly-reservations' ),
				'none'     => __( 'No pets saved yet. Add your pets once and the booking form fills them in for you.', 'pawfectly-reservations' ),
				/* translators: %s: pet name */
				'confirmRemove' => __( 'Remove %s from your saved pets? Your past bookings are not changed.', 'pawfectly-reservations' ),
				'unsaved'  => __( 'From your last booking — press Save pet to keep it.', 'pawfectly-reservations' ),
			),
			'saved'  => is_array( get_user_meta( $user_id, SavedPets::META_PETS, true ) ),
		);
	}

	public static function render(): string {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return Login::panel( (string) ( get_permalink() ?: home_url( '/' ) ) );
		}
		self::enqueue();
		$links = array( 'bookings' => Pages::url( MyBookings::TAG ), 'book' => Pages::url( Shortcode::TAG ) );
		$phone = $user->exists() ? (string) get_user_meta( (int) $user->ID, Accounts::META_PHONE, true ) : '';
		$pets  = self::pets_config( (int) $user->ID );
		$info  = SavedPets::info( (int) $user->ID );
		$recent = array(
			'reservations' => Reservations::for_owner( (int) $user->ID, (string) $user->user_email, Accounts::is_verified( (int) $user->ID ), 5 ),
			'signups'      => array_slice( MyBookings::signup_rows( (int) $user->ID ), 0, 5 ),
		);

		ob_start();
		include PAW_DIR . 'templates/profile.php';
		return (string) ob_get_clean();
	}
}
