<?php
/**
 * Plugin Name:       Deskovi
 * Plugin URI:        https://deskovi.com
 * Description:       Connect your WooCommerce store to Deskovi for helpdesk tickets, order context, and a customer chat widget.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Deskovi
 * Author URI:        https://deskovi.com
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

require_once ITSDESK_PATH . 'includes/Plugin.php';

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
