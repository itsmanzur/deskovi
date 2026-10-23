<?php
/**
 * Admin script and style loading (Deskovi screens only).
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the React admin bundle only on the Deskovi page.
 */
final class AdminAssets {

	/**
	 * Register enqueue hook.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Load assets when on the Deskovi admin screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'toplevel_page_' . AdminMenu::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = ITSDESK_PATH . 'assets/admin/index.asset.php';
		$asset      = is_readable( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'react-jsx-runtime', 'wp-element', 'wp-api-fetch', 'wp-i18n' ),
				'version'      => ITSDESK_VERSION,
			);

		$script_path = ITSDESK_PATH . 'assets/admin/index.js';
		$style_path  = ITSDESK_PATH . 'assets/admin/index.css';

		if ( is_readable( $script_path ) ) {
			/**
			 * Filters the script dependencies of the Deskovi admin bundle.
			 *
			 * @param array<int, string> $dependencies Script handles.
			 */
			$dependencies = (array) apply_filters( 'itsdesk_admin_script_deps', $asset['dependencies'] );

			wp_enqueue_script(
				'itsdesk-admin',
				ITSDESK_URL . 'assets/admin/index.js',
				$dependencies,
				$asset['version'],
				true
			);

			$config = array(
				'restRoot'        => esc_url_raw( rest_url() ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'version'         => ITSDESK_VERSION,
				'apiVersion'      => ITSDESK_API_VERSION,
				'pluginUrl'       => ITSDESK_URL,
				'currentUserId'   => get_current_user_id(),
				'currentUserName' => wp_get_current_user()->display_name,
				/**
				 * Filters the address of the page that describes Deskovi Pro. Empty (the default) hides the link.
				 *
				 * @param string $url URL.
				 */
				'proUrl'          => esc_url_raw( (string) apply_filters( 'itsdesk_pro_url', '' ) ),
				'canManageOrders' => Capabilities::can( Capabilities::ABILITY_REFUND ),
				'capabilities'    => array(
					'manage' => Capabilities::can( Capabilities::ABILITY_MANAGE ),
					'refund' => Capabilities::can( Capabilities::ABILITY_REFUND ),
				),
			);

			/**
			 * Filters the itsdeskAdmin object passed to the admin bundle.
			 * Add-ons append their own keys (e.g. extra capability flags).
			 *
			 * @param array<string, mixed> $config Localized config.
			 */
			$config = (array) apply_filters( 'itsdesk_admin_config', $config );

			wp_localize_script( 'itsdesk-admin', 'itsdeskAdmin', $config );

			wp_set_script_translations( 'itsdesk-admin', 'deskovi', ITSDESK_PATH . 'languages' );

			/**
			 * Fires right after the Deskovi admin bundle is enqueued. Add-ons
			 * enqueue their own bundle here, depending on the 'itsdesk-admin' handle.
			 *
			 * @param string $hook_suffix Current admin page hook.
			 */
			do_action( 'itsdesk_admin_enqueue_scripts', $hook_suffix );
		}

		if ( is_readable( $style_path ) ) {
			// Self-hosted Inter and Hind Siliguri (Bengali); nothing is fetched from a third party.
			wp_register_style( 'itsdesk-admin-fonts', ITSDESK_URL . 'assets/fonts/fonts.css', array(), ITSDESK_VERSION );

			wp_enqueue_style(
				'itsdesk-admin',
				ITSDESK_URL . 'assets/admin/index.css',
				array( 'wp-components', 'itsdesk-admin-fonts' ),
				$asset['version']
			);
		}
	}
}
