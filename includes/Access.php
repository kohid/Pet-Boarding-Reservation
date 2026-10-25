<?php
/**
 * Who may use WordPress's own dashboard (wp-admin): the account whose email is the site's
 * Administration Email Address, and users with the Developer role. Everyone else, staff and
 * other administrators included, works in the front-end dashboard.
 *
 * Escape hatch: define( 'PAW_WP_ADMIN_OPEN', true ); in wp-config.php turns the gate off.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class Access {

	public const DEVELOPER = 'developer';

	/** wp-admin screens the front end relies on: file downloads and the logo picker's upload. */
	private const PASS = array( 'admin-post.php', 'async-upload.php' );

	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'gate' ) );
		add_filter( 'show_admin_bar', array( self::class, 'toolbar' ) );
	}

	public static function open(): bool {
		return defined( 'PAW_WP_ADMIN_OPEN' ) && PAW_WP_ADMIN_OPEN;
	}

	public static function is_admin_user( \WP_User $u ): bool {
		if ( ! $u->exists() ) {
			return false;
		}
		$admin_email = strtolower( trim( (string) get_option( 'admin_email' ) ) );
		return ( '' !== $admin_email && strtolower( trim( (string) $u->user_email ) ) === $admin_email )
			|| in_array( self::DEVELOPER, (array) $u->roles, true );
	}

	/** The dashboard's Settings view. */
	public static function can_manage_settings( \WP_User $u ): bool {
		return user_can( $u, 'manage_options' ) || self::is_admin_user( $u );
	}

	/** Where to send someone who opened wp-admin, or null to let the request through. */
	public static function gate_target( \WP_User $u, string $pagenow, bool $ajax, bool $cron ): ?string {
		if ( self::open() || $ajax || $cron || defined( 'WP_CLI' ) || in_array( $pagenow, self::PASS, true ) || self::is_admin_user( $u ) ) {
			return null;
		}
		return Login::home_for( $u );
	}

	public static function gate(): void {
		global $pagenow;
		$target = self::gate_target( wp_get_current_user(), (string) $pagenow, wp_doing_ajax(), wp_doing_cron() );
		if ( null !== $target ) {
			wp_safe_redirect( $target );
			exit;
		}
	}

	public static function toolbar( $show ): bool {
		return (bool) $show && ( self::open() || self::is_admin_user( wp_get_current_user() ) );
	}

	/** A full-administrator Developer role, unless a site already has one (then it is left as it is). */
	public static function add_developer_role(): void {
		if ( get_role( self::DEVELOPER ) ) {
			return;
		}
		$admin = get_role( 'administrator' );
		$caps  = $admin ? array_filter( (array) $admin->capabilities ) : array( 'read' => true );
		add_role( self::DEVELOPER, __( 'Developer', 'pawfectly-reservations' ), $caps + array( Activator::CAPABILITY => true ) );
	}
}
