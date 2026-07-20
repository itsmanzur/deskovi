<?php
/**
 * Checkout safety guard for outbound transports.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Diagnostics;

/**
 * Blocks live remote calls during cart/checkout requests.
 */
final class CheckoutGuard {

	/**
	 * Whether current request is cart/checkout.
	 */
	public static function is_checkout_request(): bool {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		return false;
	}

	/**
	 * Fail live transport if on checkout.
	 *
	 * @return true|\WP_Error
	 */
	public static function assert_safe_for_remote() {
		if ( ! self::is_checkout_request() ) {
			return true;
		}
		Ops::push_error(
			'checkout_remote_blocked',
			'Blocked Deskovi remote call during cart/checkout.'
		);
		return new \WP_Error(
			'itsdesk_checkout_blocked',
			__( 'Remote Deskovi calls are not allowed during checkout.', 'deskovi' ),
			array( 'status' => 503 )
		);
	}
}
