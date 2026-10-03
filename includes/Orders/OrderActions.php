<?php
/**
 * Order-mutating actions triggered from a ticket (refund, resend invoice).
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Orders;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over WooCommerce's own refund/email APIs — mirrors the exact
 * sequence WooCommerce core's admin order-actions meta box uses, so behavior
 * (gateway refund attempt, rollback on failure, order notes) matches core.
 */
final class OrderActions {

	/**
	 * Issue a refund. Null/0 amount refunds everything remaining.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function refund( int $order_id, ?float $amount, string $reason ) {
		$order = $this->load_order( $order_id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$remaining = (float) $order->get_remaining_refund_amount();
		if ( $remaining <= 0 ) {
			return new \WP_Error(
				'itsdesk_refund_none_remaining',
				__( 'Nothing left to refund on this order.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$requested = ( null !== $amount && $amount > 0 ) ? $amount : $remaining;
		if ( $requested > $remaining ) {
			return new \WP_Error(
				'itsdesk_refund_too_large',
				__( 'Refund amount exceeds what remains on this order.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		// Only attempt the gateway's own API refund when the order's payment
		// method actually supports it — wc_refund_payment() hard-errors (and
		// wc_create_refund() rolls the whole refund back) for orders with no
		// gateway or a gateway that doesn't support refunds (COD, bank
		// transfer, many manual methods), which would otherwise make refunds
		// impossible on a large share of real orders. Falls back to a manual
		// refund record, same as leaving WooCommerce's own "refund via
		// gateway" checkbox unticked.
		$gateway_can_refund = false;
		$payment_method     = $order->get_payment_method();
		if ( '' !== $payment_method && function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$gateways = WC()->payment_gateways->payment_gateways();
			if ( isset( $gateways[ $payment_method ] ) && $gateways[ $payment_method ]->supports( 'refunds' ) ) {
				$gateway_can_refund = true;
			}
		}

		$refund = wc_create_refund(
			array(
				'amount'         => $requested,
				'reason'         => $reason,
				'order_id'       => $order_id,
				'refund_payment' => $gateway_can_refund,
			)
		);

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		return array(
			'order_id'         => $order_id,
			'amount'           => $requested,
			'currency'         => $order->get_currency(),
			'refunded_total'   => (float) $order->get_total_refunded(),
			'gateway_refunded' => $gateway_can_refund,
		);
	}

	/**
	 * Resend the customer invoice email — same call WooCommerce's own
	 * "Resend order details" admin action uses.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function resend_invoice( int $order_id ) {
		$order = $this->load_order( $order_id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		do_action( 'woocommerce_before_resend_order_emails', $order, 'customer_invoice' );

		WC()->payment_gateways();
		WC()->shipping();
		WC()->mailer()->customer_invoice( $order );
		$order->add_order_note( __( 'Order details manually sent to customer (via Deskovi).', 'deskovi' ), false, true );

		do_action( 'woocommerce_after_resend_order_email', $order, 'customer_invoice' );

		return array(
			'order_id' => $order_id,
			'sent_to'  => $order->get_billing_email(),
		);
	}

	/**
	 * @return \WC_Order|\WP_Error
	 */
	private function load_order( int $order_id ) {
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error( 'itsdesk_order_missing', __( 'Order not found.', 'deskovi' ), array( 'status' => 404 ) );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'itsdesk_order_missing', __( 'Order not found.', 'deskovi' ), array( 'status' => 404 ) );
		}
		return $order;
	}
}
