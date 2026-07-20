<?php
/**
 * WordPress admin menu registration.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Deskovi top-level admin page.
 */
final class AdminMenu {

	public const PAGE_SLUG = 'itsdesk';

	/**
	 * Hook menu registration.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Add top-level menu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Deskovi', 'deskovi' ),
			__( 'Deskovi', 'deskovi' ),
			'manage_itsdesk',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-format-chat',
			56
		);
	}

	/**
	 * Root mount for React admin.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_itsdesk' ) ) {
			wp_die( esc_html__( 'You do not have permission to access Deskovi.', 'deskovi' ) );
		}

		echo '<div class="wrap itsdesk-admin-wrap"><div id="itsdesk-admin-root"></div></div>';
	}
}
