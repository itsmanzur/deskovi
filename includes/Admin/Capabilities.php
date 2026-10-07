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
	 * Ability ids understood by can().
	 */
	public const ABILITY_MANAGE = 'manage';

	public const ABILITY_REFUND = 'refund';

	/**
	 * Central permission check for Deskovi abilities.
	 *
	 * Defaults match the long-standing behaviour exactly; add-ons may widen or
	 * narrow them (or define their own ability ids) through the
	 * itsdesk_user_can filter.
	 *
	 * @param string   $ability Ability id (ABILITY_MANAGE, ABILITY_REFUND, or an add-on id).
	 * @param int|null $user_id User id; defaults to the current user.
	 */
	public static function can( string $ability, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		switch ( $ability ) {
			case self::ABILITY_MANAGE:
				$allowed = user_can( $user_id, self::CAP );
				break;
			case self::ABILITY_REFUND:
				$allowed = user_can( $user_id, self::CAP ) && user_can( $user_id, 'edit_shop_orders' );
				break;
			default:
				$allowed = false;
		}

		/**
		 * Filters whether a user has a Deskovi ability.
		 *
		 * @param bool   $allowed Default decision.
		 * @param string $ability Ability id.
		 * @param int    $user_id User id.
		 */
		return (bool) apply_filters( 'itsdesk_user_can', $allowed, $ability, $user_id );
	}

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
