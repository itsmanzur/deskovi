<?php
/**
 * Live SaaS order transport stub.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Orders\Transport;

use Itsdesk\Diagnostics\CheckoutGuard;

/**
 * Not available until SaaS HTTP is wired.
 */
final class HttpOrderTransport implements OrderTransportInterface {

	/**
	 * {@inheritdoc}
	 */
	public function mode(): string {
		return 'live';
	}

	/**
	 * {@inheritdoc}
	 */
	public function push_status_changed( array $event ) {
		$guard = CheckoutGuard::assert_safe_for_remote();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		return new \WP_Error(
			'itsdesk_orders_live_unavailable',
			__( 'Live order sync is not available yet. Use mock mode.', 'deskovi' ),
			array( 'status' => 501 )
		);
	}
}
