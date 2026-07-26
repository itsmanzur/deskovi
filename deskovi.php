<?php
/**
 * Plugin Name:       Deskovi
 * Description:       A local WooCommerce helpdesk: support tickets, order context for agents, and an optional customer chat widget.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Deskovi
 * Author URI:        https://profiles.wordpress.org/getwpkit/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       deskovi
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   9.8
 *
 * @package Deskovi
 */

defined( 'ABSPATH' ) || exit;

define( 'ITSDESK_VERSION', '1.0.0' );
define( 'ITSDESK_FILE', __FILE__ );
define( 'ITSDESK_PATH', plugin_dir_path( __FILE__ ) );
define( 'ITSDESK_URL', plugin_dir_url( __FILE__ ) );
define( 'ITSDESK_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 autoload for Itsdesk\ → includes/ (no Composer vendor required).
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Itsdesk\\';
		if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = ITSDESK_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once ITSDESK_PATH . 'includes/Plugin.php';

register_activation_hook(
	__FILE__,
	static function () {
		if ( ! class_exists( \Itsdesk\Admin\Capabilities::class ) ) {
			require_once ITSDESK_PATH . 'includes/Admin/Capabilities.php';
		}
		\Itsdesk\Admin\Capabilities::activate();

		if ( ! class_exists( \Itsdesk\Tickets\Schema::class ) ) {
			require_once ITSDESK_PATH . 'includes/Tickets/Schema.php';
		}
		\Itsdesk\Tickets\Schema::install();
	}
);

/**
 * Bootstrap Deskovi.
 *
 * @return Itsdesk\Plugin
 */
function itsdesk() {
	return Itsdesk\Plugin::instance();
}

add_action(
	'plugins_loaded',
	static function () {
		itsdesk()->init();
	}
);
