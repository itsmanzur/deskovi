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

	public const AGENT_ROLE = 'itsdesk_agent';

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
		self::register_agent_role();
	}

	/**
	 * Idempotent grant for existing installs.
	 */
	public function ensure_caps(): void {
		self::grant();
		self::register_agent_role();
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

	/**
	 * Register the support-agent role: ticket management only, no store/site admin.
	 */
	private static function register_agent_role(): void {
		$role = get_role( self::AGENT_ROLE );
		if ( ! $role ) {
			add_role(
				self::AGENT_ROLE,
				__( 'Support Agent', 'deskovi' ),
				array(
					'read'    => true,
					self::CAP => true,
				)
			);
			return;
		}

		if ( ! $role->has_cap( self::CAP ) ) {
			$role->add_cap( self::CAP );
		}
	}
}
