<?php
/**
 * Queue outbound order status events.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Orders;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Connection\ActivityLogger;
use Itsdesk\Connection\ConnectionStatus;
use Itsdesk\Orders\Transport\OrderTransportFactory;
use Itsdesk\Queue\Status as QueueStatus;

/**
 * Listens to WC status changes — never blocks checkout.
 */
final class OutboundOrderSync {

	public const HOOK_STATUS = 'itsdesk_sync_order_status';

	/**
	 * Register WC + AS hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );
		add_action( self::HOOK_STATUS, array( $this, 'run_status' ), 10, 1 );
	}

	/**
	 * WC callback — enqueue only.
	 *
	 * @param int         $order_id   Order ID.
	 * @param string      $from       Old status (no wc- prefix).
	 * @param string      $to         New status.
	 * @param \WC_Order   $order      Order object.
	 */
	public function on_status_changed( $order_id, $from, $to, $order ): void {
		$order_id = absint( $order_id );
		if ( $order_id <= 0 ) {
			return;
		}

		$from = sanitize_key( (string) $from );
		$to   = sanitize_key( (string) $to );
		if ( '' === $to || $from === $to ) {
			return;
		}

		$idempotency = 'ord_evt_' . $order_id . '_' . $from . '_' . $to . '_' . gmdate( 'YmdHis' );

		$payload = array(
			'type'             => 'order.status_changed',
			'idempotency_key'  => $idempotency,
			'order_id'         => $order_id,
			'from'             => $from,
			'to'               => $to,
			'occurred_at'      => gmdate( 'c' ),
		);

		$this->enqueue( $payload );
	}

	/**
	 * @param array<string, mixed> $payload Event.
	 */
	private function enqueue( array $payload ): void {
		$connection = ( new ConnectionStatus() )->get();
		$connected  = ( $connection['status'] ?? '' ) === 'connected';
		$mode       = OrderTransportFactory::make()->mode();

		if ( ! $connected && 'live' === $mode ) {
			( new ActivityLogger() )->log(
				'Order',
				__( 'Order status sync skipped — store not connected', 'deskovi' ),
				'Skipped'
			);
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_STATUS, array( $payload ), QueueStatus::GROUP );
			return;
		}

		$this->run_status( $payload );
	}

	/**
	 * AS callback.
	 *
	 * @param array<string, mixed> $payload Event.
	 */
	public function run_status( $payload ): void {
		if ( ! is_array( $payload ) ) {
			return;
		}

		$order_id = isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0;
		$logger   = new ActivityLogger();

		if ( $order_id > 0 ) {
			$snapshot = ( new OrderContext() )->get_compact_snapshot( $order_id, null, true );
			if ( ! is_wp_error( $snapshot ) ) {
				$payload['snapshot'] = $snapshot;
			}
		}

		$result = OrderTransportFactory::make()->push_status_changed( $payload );
		if ( is_wp_error( $result ) ) {
			$logger->log( 'Order', $result->get_error_message(), 'Failed' );
			\Itsdesk\Diagnostics\Ops::push_error( 'order_status_sync', $result->get_error_message() );
			return;
		}

		\Itsdesk\Diagnostics\Ops::touch_cursor();
		$logger->log(
			'Order',
			sprintf(
				/* translators: 1: order id, 2: from status, 3: to status */
				__( 'Order #%1$d status synced (%2$s → %3$s)', 'deskovi' ),
				$order_id,
				(string) ( $payload['from'] ?? '' ),
				(string) ( $payload['to'] ?? '' )
			),
			'OK'
		);
	}
}
