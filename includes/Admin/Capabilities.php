<?php
/**
 * Capability bootstrap for Deskovi admins.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures manage_itsdesk exists on roles that manage the store.
 */
final class Capabilities {

	public const CAP = 'manage_itsdesk';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'ensure_caps' ), 5 );
	}

	/**
	 * Grant capability on plugin activation.
	 */
	public static function activate(): void {
		self::grant();
	}

	/**
	 * Idempotent grant for existing installs.
	 */
	public function ensure_caps(): void {
		self::grant();
	}

	/**
	 * Add manage_itsdesk to administrator and shop_manager.
	 */
	private static function grant(): void {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( self::CAP ) ) {
				$role->add_cap( self::CAP );
			}
		}
	}
}
