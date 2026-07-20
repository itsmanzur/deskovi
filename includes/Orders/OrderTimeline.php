<?php
/**
 * Build order timeline from WooCommerce notes.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Orders;

/**
 * Live-read timeline — no separate Deskovi table.
 */
final class OrderTimeline {

	/**
	 * Timeline entries newest-first (capped).
	 *
	 * @return array<int, array{at: string, type: string, message: string}>
	 */
	public function for_order( \WC_Order $order, int $limit = 40 ): array {
		if ( ! function_exists( 'wc_get_order_notes' ) ) {
			return array();
		}

		$notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'limit'    => $limit,
				'orderby'  => 'date_created',
				'order'    => 'DESC',
			)
		);

		$out = array();
		foreach ( $notes as $note ) {
			$content = isset( $note->content ) ? wp_strip_all_tags( (string) $note->content ) : '';
			if ( '' === $content ) {
				continue;
			}

			$at = '';
			if ( isset( $note->date_created ) && is_object( $note->date_created ) && method_exists( $note->date_created, 'date' ) ) {
				$at = $note->date_created->date( 'c' );
			}

			$customer_note = ! empty( $note->customer_note );
			$out[]         = array(
				'at'      => $at,
				'type'    => $customer_note ? 'customer_note' : 'note',
				'message' => $content,
			);
		}

		return $out;
	}
}
