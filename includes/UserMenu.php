<?php
/**
 * [pawfectly_user_menu]: the avatar and dropdown for a site header. Visitors see Sign In and
 * Sign Up; signed-in users see their name and email with Dashboard (staff), My Bookings,
 * My Profile, Settings (admins), Admin Panel (the admin-email account and Developers) and Log Out. Place it in an Elementor header with a Shortcode widget.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class UserMenu {

	public const TAG = 'pawfectly_user_menu';

	private static int $count = 0;

	public static function init(): void {
		add_shortcode( self::TAG, array( self::class, 'render' ) );
	}

	/**
	 * Menu entries for one visitor. Entries whose page does not exist are left out.
	 *
	 * @param array{logged_in: bool, staff: bool, admin: bool, wp_admin?: bool, urls: array<string,string>} $ctx
	 * @return array<int, array{key: string, label: string, url: string}|array{sep: true}>
	 */
	public static function items( array $ctx ): array {
		$u     = $ctx['urls'];
		$out   = array();
		$add   = static function ( string $key, string $label ) use ( &$out, $u ): void {
			if ( '' !== (string) ( $u[ $key ] ?? '' ) ) {
				$out[] = array( 'key' => $key, 'label' => $label, 'url' => $u[ $key ] );
			}
		};
		if ( ! $ctx['logged_in'] ) {
			$add( 'login', __( 'Sign In', 'pawfectly-reservations' ) );
			$add( 'signup', __( 'Sign Up', 'pawfectly-reservations' ) );
			return $out;
		}
		if ( $ctx['staff'] ) {
			$add( 'dashboard', __( 'Dashboard', 'pawfectly-reservations' ) );
		}
		$add( 'bookings', __( 'My Bookings', 'pawfectly-reservations' ) );
		$add( 'profile', __( 'My Profile', 'pawfectly-reservations' ) );
		if ( $ctx['admin'] ) {
			$add( 'settings', __( 'Settings', 'pawfectly-reservations' ) );
		}
		if ( ! empty( $ctx['wp_admin'] ) ) {
			$add( 'wp_admin', __( 'Admin Panel', 'pawfectly-reservations' ) );
		}
		$out[] = array( 'sep' => true );
		$add( 'logout', __( 'Log Out', 'pawfectly-reservations' ) );
		return $out;
	}

	/** "Jay Miralles" → "JM"; one name → one letter; nothing → "?". */
	public static function initials( string $name ): string {
		$parts = preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $parts ) {
			return '?';
		}
		$first = mb_strtoupper( mb_substr( $parts[0], 0, 1 ) );
		return count( $parts ) > 1 ? $first . mb_strtoupper( mb_substr( end( $parts ), 0, 1 ) ) : $first;
	}

	/** @param array<string,string>|string $atts */
	public static function render( $atts = array() ): string {
		$a = shortcode_atts( array( 'align' => 'right' ), (array) $atts, self::TAG );
		wp_enqueue_style( 'paw-account', PAW_URL . 'assets/css/account.css', array(), PAW_VERSION );
		wp_enqueue_script( 'paw-user-menu', PAW_URL . 'assets/js/user-menu.js', array(), PAW_VERSION, true );

		$user    = wp_get_current_user();
		$in      = $user->exists();
		$here    = (string) ( get_permalink() ?: home_url( '/' ) );
		$profile  = Pages::url( Profile::TAG );
		$dash     = Pages::url( Dashboard::TAG );
		$wp_admin = Access::is_admin_user( $user );
		$login    = Login::url_for( $here );

		$items = self::items(
			array(
				'logged_in' => $in,
				'staff'     => $in && current_user_can( Activator::CAPABILITY ),
				'admin'     => Access::can_manage_settings( $user ),
				'wp_admin'  => $wp_admin,
				'urls'      => array(
					'login'     => $login,
					'signup'    => add_query_arg( array( 'tab' => 'signup' ), $login ),
					'profile'   => '' !== $profile ? $profile : ( $wp_admin ? get_edit_profile_url() : '' ),
					'bookings'  => Pages::url( MyBookings::TAG ),
					'dashboard' => $dash,
					'settings'  => '' !== $dash ? add_query_arg( array( 'paw_view' => 'settings' ), $dash ) : ( $wp_admin ? admin_url( 'admin.php?page=' . Settings::PAGE ) : '' ),
					'wp_admin'  => admin_url(),
					'logout'    => wp_logout_url( $here ),
				),
			)
		);

		$name  = $in ? (string) $user->display_name : __( 'Guest', 'pawfectly-reservations' );
		$sub   = $in ? (string) $user->user_email : __( 'Not signed in', 'pawfectly-reservations' );
		$id    = 'paw-um-' . ( ++self::$count );
		$guest = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="9" r="4.2"/><path d="M3.8 21c.7-4.3 4-6.4 8.2-6.4s7.5 2.1 8.2 6.4z"/></svg>';
		$face  = static fn( string $cls ): string => '<span class="paw-um__avatar ' . $cls . ( $in ? '' : ' is-guest' ) . '" aria-hidden="true">' . ( $in ? esc_html( self::initials( $name ) ) : $guest ) . '</span>';
		$align = 'left' === $a['align'] ? 'left' : 'right';

		ob_start();
		echo '<div class="paw-um paw-um--' . esc_attr( $align ) . '" data-paw-um>';
		/* translators: %s: the user's name, or "Guest" */
		echo '<button type="button" class="paw-um__btn" aria-haspopup="menu" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr( sprintf( __( 'Account menu for %s', 'pawfectly-reservations' ), $name ) ) . '">' . $face( '' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup and escaped initials.
		echo '<div class="paw-um__panel" id="' . esc_attr( $id ) . '" role="menu" hidden>';
		echo '<div class="paw-um__head">' . $face( 'paw-um__avatar--lg' ) . '<div class="paw-um__who"><strong>' . esc_html( $name ) . '</strong><span>' . esc_html( $sub ) . '</span></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="paw-um__list">';
		foreach ( $items as $item ) {
			if ( ! empty( $item['sep'] ) ) {
				echo '<hr class="paw-um__sep" role="separator">';
				continue;
			}
			echo '<a class="paw-um__item paw-um__item--' . esc_attr( $item['key'] ) . '" role="menuitem" tabindex="-1" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		}
		echo '</div></div></div>';
		return (string) ob_get_clean();
	}
}
