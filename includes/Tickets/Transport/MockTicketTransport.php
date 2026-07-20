<?php
/**
 * Mock SaaS ticket transport.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Tickets\Transport;

/**
 * Local fake remote IDs — no network.
 */
final class MockTicketTransport implements TicketTransportInterface {

	/**
	 * {@inheritdoc}
	 */
	public function mode(): string {
		return 'mock';
	}

	/**
	 * {@inheritdoc}
	 */
	public function create_ticket( array $ticket ) {
		$remote = 'tkt_mock_' . substr( hash( 'sha256', (string) ( $ticket['id'] ?? wp_generate_uuid4() ) ), 0, 12 );

		return array(
			'remote_id' => $remote,
			'saas_url'  => 'https://app.deskovi.com/tickets/' . $remote,
			'synced_at' => gmdate( 'c' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function push_message( array $ticket, array $message ) {
		return array(
			'remote_message_id' => 'msg_mock_' . substr( hash( 'sha256', (string) ( $message['id'] ?? '' ) ), 0, 10 ),
			'synced_at'         => gmdate( 'c' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function sync_status( array $ticket ) {
		return true;
	}
}
