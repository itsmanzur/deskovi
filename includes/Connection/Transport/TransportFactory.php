<?php
/**
 * Resolve mock vs live transport.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection\Transport;

/**
 * Factory for connection transports.
 */
final class TransportFactory {

	/**
	 * Current mode: mock (default) or live.
	 */
	public static function mode(): string {
		$mode = 'mock';

		if ( defined( 'ITSDESK_CONNECTION_MODE' ) ) {
			$mode = (string) ITSDESK_CONNECTION_MODE;
		}

		/**
		 * Filter connection transport mode.
		 *
		 * @param string $mode mock|live
		 */
		$mode = (string) apply_filters( 'itsdesk_connection_mode', $mode );

		return in_array( $mode, array( 'mock', 'live' ), true ) ? $mode : 'mock';
	}

	/**
	 * Build transport.
	 */
	public static function make(): TransportInterface {
		return 'live' === self::mode() ? new HttpTransport() : new MockTransport();
	}
}
