<?php
/**
 * Sanitized environment diagnostics.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Diagnostics;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Privacy\Settings as PrivacySettings;
use Itsdesk\Widget\Frontend as WidgetFrontend;

/**
 * Builds a support-safe environment report.
 */
final class EnvironmentReport {

	/**
	 * Collect environment checks.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		global $wp_version;

		$theme   = wp_get_theme();
		$privacy = ( new PrivacySettings() )->get();

		return array(
			'wordpress'   => (string) $wp_version,
			'woocommerce' => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
			'php'         => PHP_VERSION,
			'plugin'      => ITSDESK_VERSION,
			'theme'       => $theme->get( 'Name' ) . ' (' . $theme->get( 'Version' ) . ')',
			'checks'      => array(
				array(
					'name'   => 'HPOS',
					'value'  => $this->hpos_label(),
					'status' => $this->is_hpos_enabled() ? 'pass' : 'info',
				),
				array(
					'name'   => 'REST API',
					'value'  => rest_url() ? 'Reachable' : 'Unavailable',
					'status' => rest_url() ? 'pass' : 'fail',
				),
				array(
					'name'   => 'WP_DEBUG',
					'value'  => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'On' : 'Off',
					'status' => 'ok',
				),
				array(
					'name'   => 'Widget on checkout',
					'value'  => 'Skipped by Frontend::should_load',
					'status' => class_exists( WidgetFrontend::class ) ? 'pass' : 'fail',
				),
				array(
					'name'   => 'Diagnostics consent',
					'value'  => ! empty( $privacy['diagnostics_consent'] ) ? 'Granted' : 'Not granted',
					'status' => ! empty( $privacy['diagnostics_consent'] ) ? 'pass' : 'info',
				),
			),
			'errors'      => $this->recent_errors(),
		);
	}

	/**
	 * HPOS enabled label.
	 */
	private function hpos_label(): string {
		return $this->is_hpos_enabled() ? 'Enabled' : 'Disabled';
	}

	/**
	 * Whether HPOS is enabled.
	 */
	private function is_hpos_enabled(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * No sanitized diagnostic errors are tracked locally (no outbound sync layer).
	 *
	 * @return array<int, array<string, string>>
	 */
	private function recent_errors(): array {
		return array();
	}
}
