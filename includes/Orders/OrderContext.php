<?php
/**
 * HPOS-safe order list + privacy-filtered snapshots.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Orders;

use Itsdesk\Privacy\Settings as PrivacySettings;

/**
 * Order context for tickets / customer REST.
 */
final class OrderContext {

	private PrivacySettings $privacy;
	private OrderTimeline $timeline;

	/**
	 * Constructor.
	 */
	public function __construct( ?PrivacySettings $privacy = null, ?OrderTimeline $timeline = null ) {
		$this->privacy  = $privacy ?? new PrivacySettings();
		$this->timeline = $timeline ?? new OrderTimeline();
	}

	/**
	 * Days window for customer order picker / list.
	 */
	public function list_window_days(): int {
		$settings = $this->privacy->get();
		$import   = (string) ( $settings['historical_import'] ?? 'off' );
		if ( in_array( $import, array( '30', '60', '90' ), true ) ) {
			return (int) $import;
		}
		// `off` → still allow a short picker window (not full history).
		return 30;
	}

	/**
	 * List summary rows for a customer (CRUD only).
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function list_for_customer( int $user_id, int $limit = 40 ) {
		if ( $user_id <= 0 ) {
			return new \WP_Error(
				'itsdesk_orders_auth',
				__( 'Login required.', 'deskovi' ),
				array( 'status' => 401 )
			);
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$days  = $this->list_window_days();
		$after = time() - ( $days * DAY_IN_SECONDS );

		$orders = wc_get_orders(
			array(
				'customer_id'  => $user_id,
				'limit'        => max( 1, min( 100, $limit ) ),
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'objects',
				'status'       => array_keys( wc_get_order_statuses() ),
				'date_created' => '>=' . $after,
			)
		);

		$rows = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$rows[] = $this->summarize( $order );
		}

		return $rows;
	}

	/**
	 * Full snapshot + timeline with ownership + privacy.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_snapshot( int $order_id, ?int $user_id, bool $is_admin ) {
		$order = $this->load_order( $order_id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$owned = $this->assert_can_view( $order, $user_id, $is_admin );
		if ( is_wp_error( $owned ) ) {
			return $owned;
		}

		return $this->build_snapshot( $order, true );
	}

	/**
	 * Compact snapshot without timeline (ticket create / outbound).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_compact_snapshot( int $order_id, ?int $user_id, bool $is_admin ) {
		$order = $this->load_order( $order_id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$owned = $this->assert_can_view( $order, $user_id, $is_admin );
		if ( is_wp_error( $owned ) ) {
			return $owned;
		}

		return $this->build_snapshot( $order, false );
	}

	/**
	 * @return \WC_Order|\WP_Error
	 */
	private function load_order( int $order_id ) {
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return new \WP_Error(
				'itsdesk_order_missing',
				__( 'Order not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error(
				'itsdesk_order_missing',
				__( 'Order not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		return $order;
	}

	/**
	 * @return true|\WP_Error
	 */
	private function assert_can_view( \WC_Order $order, ?int $user_id, bool $is_admin ) {
		if ( $is_admin ) {
			return true;
		}

		if ( null === $user_id || $user_id <= 0 ) {
			return new \WP_Error(
				'itsdesk_order_forbidden',
				__( 'You can only view your own orders.', 'deskovi' ),
				array( 'status' => 403 )
			);
		}

		$order_user = (int) $order->get_user_id();
		if ( $order_user > 0 && $order_user === $user_id ) {
			return true;
		}

		return new \WP_Error(
			'itsdesk_order_forbidden',
			__( 'You can only view your own orders.', 'deskovi' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function summarize( \WC_Order $order ): array {
		$created = $order->get_date_created();
		return array(
			'id'           => $order->get_id(),
			'number'       => $order->get_order_number(),
			'status'       => $order->get_status(),
			'date_created' => $created ? $created->date( 'c' ) : '',
			'currency'     => $order->get_currency(),
			'total'        => $order->get_total(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_snapshot( \WC_Order $order, bool $with_timeline ): array {
		$settings = $this->privacy->get();
		$allow_address = ! empty( $settings['sync_billing_address'] );
		$allow_phone   = ! empty( $settings['sync_phone'] );

		$created = $order->get_date_created();
		$items   = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => (int) $item->get_quantity(),
				'sku'      => $product ? (string) $product->get_sku() : '',
			);
		}

		$shipping_method = '';
		$methods         = $order->get_shipping_methods();
		if ( ! empty( $methods ) ) {
			$first = reset( $methods );
			if ( $first instanceof \WC_Order_Item_Shipping ) {
				$shipping_method = $first->get_name();
			}
		}

		$snapshot = array(
			'id'                    => $order->get_id(),
			'number'                => $order->get_order_number(),
			'status'                => $order->get_status(),
			'date_created'          => $created ? $created->date( 'c' ) : '',
			'currency'              => $order->get_currency(),
			'total'                 => $order->get_total(),
			'payment_method_title'  => $order->get_payment_method_title(),
			'shipping_method'       => $shipping_method,
			'items'                 => $items,
			'billing'               => null,
			'shipping'              => null,
			'phone'                 => null,
		);

		if ( $allow_address ) {
			$snapshot['billing'] = array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
				'email'      => $order->get_billing_email(),
			);
			$snapshot['shipping'] = array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
			);
		}

		if ( $allow_phone ) {
			$snapshot['phone'] = $order->get_billing_phone();
		}

		if ( $with_timeline ) {
			$snapshot['timeline'] = $this->timeline->for_order( $order );
		}

		return $snapshot;
	}
}
