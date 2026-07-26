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
			wp_enqueue_script(
				'itsdesk-admin',
				ITSDESK_URL . 'assets/admin/index.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_localize_script(
				'itsdesk-admin',
				'itsdeskAdmin',
				array(
					'restRoot'      => esc_url_raw( rest_url() ),
					'nonce'         => wp_create_nonce( 'wp_rest' ),
					'version'       => ITSDESK_VERSION,
					'pluginUrl'     => ITSDESK_URL,
					'currentUserId' => get_current_user_id(),
				)
			);

			wp_set_script_translations( 'itsdesk-admin', 'deskovi', ITSDESK_PATH . 'languages' );
		}

		if ( is_readable( $style_path ) ) {
			wp_enqueue_style(
				'itsdesk-admin',
				ITSDESK_URL . 'assets/admin/index.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}
	}
}
