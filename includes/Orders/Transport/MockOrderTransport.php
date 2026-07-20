<?php
/**
 * Mock order event transport.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Orders\Transport;

defined( 'ABSPATH' ) || exit;

/**
 * Accepts events locally — no network.
 */
final class MockOrderTransport implements OrderTransportInterface {

	/**
	 * {@inheritdoc}
	 */
	public function mode(): string {
		return 'mock';
	}

	/**
	 * {@inheritdoc}
	 */
	public function push_status_changed( array $event ) {
		return array(
			'accepted'  => true,
			'event_id'  => 'ord_evt_mock_' . substr( hash( 'sha256', (string) ( $event['idempotency_key'] ?? wp_generate_uuid4() ) ), 0, 12 ),
			'synced_at' => gmdate( 'c' ),
		);
	}
}
