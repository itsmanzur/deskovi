<?php
/**
 * WooCommerce dependency and HPOS compatibility.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Compatibility;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures WooCommerce is present and declares HPOS compatibility.
 */
final class WooCommerce {

	/**
	 * Register compatibility hooks.
	 */
	public function register(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'admin_notices', array( $this, 'maybe_missing_notice' ) );
	}

	/**
	 * Whether WooCommerce is active.
	 */
	public function is_active(): bool {
		return class_exists( '\WooCommerce' );
	}

	/**
	 * Declare High-Performance Order Storage compatibility.
	 */
	public function declare_hpos_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			ITSDESK_FILE,
			true
		);
	}

	/**
	 * Admin notice when WooCommerce is missing.
	 */
	public function maybe_missing_notice(): void {
		if ( $this->is_active() ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'Deskovi requires WooCommerce to be installed and active. Please install and activate WooCommerce to use this connector.',
			'deskovi'
		);
		echo '</p></div>';
	}
}
