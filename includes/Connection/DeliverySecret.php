<?php
/**
 * SaaS → WP delivery secret (server-side only).
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection;

/**
 * Stores HMAC delivery secret from connect exchange. Never exposed via REST.
 */
final class DeliverySecret {

	public const OPTION_KEY = 'itsdesk_delivery_secret';

	public function get(): string {
		$v = get_option( self::OPTION_KEY, '' );
		return is_string( $v ) ? $v : '';
	}

	public function set( string $secret ): void {
		update_option( self::OPTION_KEY, $secret, false );
	}

	public function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
