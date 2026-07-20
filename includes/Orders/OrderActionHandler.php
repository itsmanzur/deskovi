<?php
/**
 * Apply controlled order actions from SaaS inbound events.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Orders;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Connection\ActivityLogger;

/**
 * Safe, allowlisted WooCommerce order mutations (no refunds/status flips).
 */
final class OrderActionHandler {

	/**
	 * @param array<string, mixed> $payload Inbound event.
	 * @return true|\WP_Error
	 */
	public function handle( array $payload ) {
		$type = (string) ( $payload['type'] ?? '' );
		$order_id = isset( $payload['order']['id'] ) ? absint( $payload['order']['id'] ) : 0;
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error(
				'itsdesk_order_action_missing',
				__( 'Order id required.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error(
				'itsdesk_order_missing',
				__( 'Order not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		if ( 'order.note.add' === $type ) {
			return $this->add_note( $order, $payload );
		}

		if ( 'order.invoice.resend' === $type ) {
			return $this->resend_invoice( $order );
		}

		return new \WP_Error(
			'itsdesk_order_action_unknown',
			__( 'Unknown order action.', 'deskovi' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @return true|\WP_Error
	 */
	private function add_note( \WC_Order $order, array $payload ) {
		$note = isset( $payload['order']['note'] ) ? sanitize_textarea_field( (string) $payload['order']['note'] ) : '';
		if ( '' === $note ) {
			return new \WP_Error(
				'itsdesk_order_note_empty',
				__( 'Note is required.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$customer = ! empty( $payload['order']['customer_note'] );
		$order->add_order_note( $note, $customer, true );
		( new ActivityLogger() )->log(
			'Order',
			sprintf(
				/* translators: %d: order id */
				__( 'SaaS added note on order #%d', 'deskovi' ),
				$order->get_id()
			),
			'OK'
		);

		return true;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function resend_invoice( \WC_Order $order ) {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return new \WP_Error(
				'itsdesk_mailer_missing',
				__( 'WooCommerce mailer unavailable.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		$mails = WC()->mailer()->get_emails();
		$email = $mails['WC_Email_Customer_Invoice'] ?? null;
		if ( ! $email || ! is_object( $email ) || ! method_exists( $email, 'trigger' ) ) {
			return new \WP_Error(
				'itsdesk_invoice_email_missing',
				__( 'Customer invoice email is not available.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		$email->trigger( $order->get_id() );
		( new ActivityLogger() )->log(
			'Order',
			sprintf(
				/* translators: %d: order id */
				__( 'SaaS resent invoice email for order #%d', 'deskovi' ),
				$order->get_id()
			),
			'OK'
		);

		return true;
	}
}
