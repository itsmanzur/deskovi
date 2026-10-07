<?php
/**
 * Main plugin bootstrap.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Admin\AdminAssets;
use Itsdesk\Admin\AdminMenu;
use Itsdesk\Admin\Capabilities;
use Itsdesk\Compatibility\WooCommerce;
use Itsdesk\Privacy\PersonalData;
use Itsdesk\Rest\AdminController;
use Itsdesk\Rest\GuestController;
use Itsdesk\Rest\OrderController;
use Itsdesk\Rest\TicketController;
use Itsdesk\Tickets\Notifications as TicketNotifications;
use Itsdesk\Tickets\Schema as TicketSchema;
use Itsdesk\Widget\Frontend as WidgetFrontend;

/**
 * Singleton plugin orchestrator.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent cloning.
	 */
	private function __construct() {}

	/**
	 * Wire hooks.
	 */
	public function init(): void {
		TicketSchema::maybe_install();
		$this->cleanup_removed_features();

		( new Capabilities() )->register();

		$woocommerce = new WooCommerce();
		$woocommerce->register();

		if ( ! $woocommerce->is_active() ) {
			return;
		}

		( new AdminMenu() )->register();
		( new AdminAssets() )->register();
		( new AdminController() )->register();
		( new TicketController() )->register();
		( new TicketNotifications() )->register();
		( new OrderController() )->register();
		( new GuestController() )->register();
		( new WidgetFrontend() )->register();
		( new PersonalData() )->register();

		// Single place add-ons register REST routes, after all of Free's own
		// controllers have registered theirs (same rest_api_init priority, later
		// add order). Use a dedicated sub-path such as /pro/... — Free's
		// GET /tickets/{id} pattern would shadow a later literal /tickets/... route.
		add_action(
			'rest_api_init',
			static function (): void {
				/**
				 * Fires so add-ons can register REST routes in the Deskovi namespace.
				 *
				 * @param string $namespace REST namespace (itsdesk/v1).
				 */
				do_action( 'itsdesk_register_rest_routes', 'itsdesk/v1' );
			}
		);

		/**
		 * Fires once Deskovi has finished wiring itself (WooCommerce active).
		 * Add-ons attach here and gate on ITSDESK_API_VERSION.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'itsdesk_loaded', $this );
	}

	/**
	 * Self-heal sites that ran a pre-release 1.3.0 build which included the
	 * since-removed reply-by-email (IMAP) feature: drop its recurring cron
	 * event (it would error on every tick now that the handler class is
	 * gone) and the settings it stored, including the mailbox password.
	 * Idempotent and cheap — a no-op on every site that never had it.
	 */
	private function cleanup_removed_features(): void {
		if ( wp_next_scheduled( 'itsdesk_poll_inbox' ) ) {
			wp_clear_scheduled_hook( 'itsdesk_poll_inbox' );
		}

		if ( false !== get_option( 'itsdesk_imap_settings', false ) ) {
			delete_option( 'itsdesk_imap_settings' );
		}
		if ( false !== get_option( 'itsdesk_imap_cron_schedule', false ) ) {
			delete_option( 'itsdesk_imap_cron_schedule' );
		}
	}
}
