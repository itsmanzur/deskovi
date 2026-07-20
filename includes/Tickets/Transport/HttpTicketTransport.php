<?php
/**
 * Live ticket transport for Deskovi SaaS.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets\Transport;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Connection\ConnectionStatus;
use Itsdesk\Connection\SignedHttpClient;
use Itsdesk\Diagnostics\CheckoutGuard;

/**
 * Signed ticket ingest (tickets-v1).
 */
final class HttpTicketTransport implements TicketTransportInterface {

	private SignedHttpClient $http;
	private ConnectionStatus $status;

	public function __construct( ?SignedHttpClient $http = null, ?ConnectionStatus $status = null ) {
		$this->http   = $http ?? new SignedHttpClient();
		$this->status = $status ?? new ConnectionStatus();
	}

	/**
	 * {@inheritdoc}
	 */
	public function mode(): string {
		return 'live';
	}

	/**
	 * {@inheritdoc}
	 */
	public function create_ticket( array $ticket ) {
		$guard = CheckoutGuard::assert_safe_for_remote();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$connection = $this->require_connection();
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		$site_uuid = (string) $connection['site_uuid'];
		$path      = '/sites/' . rawurlencode( $site_uuid ) . '/tickets';
		$key       = (string) ( $ticket['idempotency_key'] ?? ( 'tkt_create_' . ( $ticket['id'] ?? wp_generate_uuid4() ) ) );

		$body = array(
			'id'               => $ticket['id'] ?? null,
			'subject'          => (string) ( $ticket['subject'] ?? '' ),
			'status'           => (string) ( $ticket['status'] ?? 'open' ),
			'category'         => $ticket['category'] ?? null,
			'order_id'         => isset( $ticket['order_id'] ) ? (int) $ticket['order_id'] : null,
			'order_context'    => ( isset( $ticket['order_snapshot'] ) && is_array( $ticket['order_snapshot'] ) )
				? $ticket['order_snapshot']
				: null,
			'customer_user_id' => isset( $ticket['customer_user_id'] ) ? (int) $ticket['customer_user_id'] : null,
			'customer_email'   => $ticket['customer_email'] ?? null,
			'customer_name'    => $ticket['customer_name'] ?? null,
			'messages'         => array(),
		);

		if ( ! empty( $ticket['messages'] ) && is_array( $ticket['messages'] ) ) {
			foreach ( $ticket['messages'] as $message ) {
				if ( ! is_array( $message ) ) {
					continue;
				}
				$body['messages'][] = array(
					'id'       => $message['id'] ?? null,
					'author'   => $message['author'] ?? 'customer',
					'body'     => (string) ( $message['body'] ?? '' ),
					'internal' => (bool) ( $message['internal'] ?? false ),
				);
			}
		}

		$result = $this->http->post_signed( $connection, $path, $body, $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'remote_id' => (string) ( $result['remote_id'] ?? '' ),
			'saas_url'  => (string) ( $result['saas_url'] ?? '' ),
			'synced_at' => (string) ( $result['synced_at'] ?? gmdate( 'c' ) ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function push_message( array $ticket, array $message ) {
		$guard = CheckoutGuard::assert_safe_for_remote();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$connection = $this->require_connection();
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		$remote_id = (string) ( $ticket['remote_id'] ?? '' );
		if ( '' === $remote_id ) {
			return new \WP_Error(
				'itsdesk_ticket_not_synced',
				__( 'Ticket has no remote id yet.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$site_uuid = (string) $connection['site_uuid'];
		$path      = '/sites/' . rawurlencode( $site_uuid ) . '/tickets/' . rawurlencode( $remote_id ) . '/messages';
		$key       = 'msg_' . (string) ( $message['id'] ?? wp_generate_uuid4() );

		$body = array(
			'id'       => $message['id'] ?? null,
			'author'   => $message['author'] ?? 'customer',
			'body'     => (string) ( $message['body'] ?? '' ),
			'internal' => (bool) ( $message['internal'] ?? false ),
		);

		$result = $this->http->post_signed( $connection, $path, $body, $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'remote_message_id' => (string) ( $result['remote_message_id'] ?? '' ),
			'synced_at'         => (string) ( $result['synced_at'] ?? gmdate( 'c' ) ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function sync_status( array $ticket ) {
		$guard = CheckoutGuard::assert_safe_for_remote();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$connection = $this->require_connection();
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		$remote_id = (string) ( $ticket['remote_id'] ?? '' );
		if ( '' === $remote_id ) {
			return new \WP_Error(
				'itsdesk_ticket_not_synced',
				__( 'Ticket has no remote id yet.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$site_uuid = (string) $connection['site_uuid'];
		$path      = '/sites/' . rawurlencode( $site_uuid ) . '/tickets/' . rawurlencode( $remote_id ) . '/status';
		$key       = 'status_' . (string) ( $ticket['id'] ?? '' ) . '_' . (string) ( $ticket['status'] ?? '' ) . '_' . (string) ( $ticket['updated_at'] ?? time() );

		$result = $this->http->post_signed(
			$connection,
			$path,
			array( 'status' => (string) ( $ticket['status'] ?? 'open' ) ),
			$key
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function require_connection() {
		$connection = $this->status->get();
		if ( ( $connection['status'] ?? '' ) !== 'connected' && ( $connection['status'] ?? '' ) !== 'error' ) {
			return new \WP_Error(
				'itsdesk_not_connected',
				__( 'Store is not connected to Deskovi.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}
		if ( empty( $connection['site_uuid'] ) ) {
			return new \WP_Error(
				'itsdesk_missing_site_uuid',
				__( 'Missing site UUID. Reconnect the store.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		return $connection;
	}
}
