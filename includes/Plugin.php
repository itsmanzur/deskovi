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
use Itsdesk\Orders\OutboundOrderSync;
use Itsdesk\Privacy\PersonalData;
use Itsdesk\Rest\AdminController;
use Itsdesk\Rest\GuestController;
use Itsdesk\Rest\OrderController;
use Itsdesk\Rest\SaasInboundController;
use Itsdesk\Rest\TicketController;
use Itsdesk\Tickets\OutboundSync;
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
		( new OrderController() )->register();
		( new GuestController() )->register();
		( new SaasInboundController() )->register();
		( new OutboundSync() )->register();
		( new OutboundOrderSync() )->register();
		( new WidgetFrontend() )->register();
		( new PersonalData() )->register();
	}
}
