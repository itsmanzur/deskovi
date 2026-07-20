<?php
/**
 * Persisted connection state (public fields only).
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection;

use Itsdesk\Connection\Transport\TransportFactory;

/**
 * Read/write connection option.
 */
final class ConnectionStatus {

	public const OPTION_KEY = 'itsdesk_connection';

	/**
	 * Default disconnected payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'status'                 => 'disconnected',
			'mode'                   => TransportFactory::mode(),
			'workspace_id'           => '',
			'workspace_name'         => '',
			'site_uuid'              => '',
			'saas_url'               => 'https://app.deskovi.com',
			'scopes'                 => array(),
			'public_key_fingerprint' => '',
			'connected_at'           => null,
			'last_sync_at'           => null,
			'last_health_at'         => null,
			'health'                 => 'unknown',
		);
	}

	/**
	 * Current connection state.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$state         = array_merge( self::defaults(), $stored );
		$state['mode'] = TransportFactory::mode();

		return $state;
	}

	/**
	 * Replace connection state.
	 *
	 * @param array<string, mixed> $state Full state.
	 */
	public function save( array $state ): void {
		update_option( self::OPTION_KEY, array_merge( self::defaults(), $state ), false );
	}

	/**
	 * Merge patch into current state.
	 *
	 * @param array<string, mixed> $patch Partial fields.
	 */
	public function patch( array $patch ): void {
		$this->save( array_merge( $this->get(), $patch ) );
	}

	/**
	 * Overview helpers derived from connection + queue.
	 *
	 * @return array<string, mixed>
	 */
	public function overview(): array {
		$connection = $this->get();
		$queue      = ( new \Itsdesk\Queue\Status() )->summary();

		return array(
			'connection'     => $connection,
			'queue_failures' => $queue['failed'],
			'queue_pending'  => $queue['pending'],
			'hpos_enabled'   => $this->is_hpos_enabled(),
		);
	}

	/**
	 * Whether HPOS custom order tables are enabled.
	 */
	private function is_hpos_enabled(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
