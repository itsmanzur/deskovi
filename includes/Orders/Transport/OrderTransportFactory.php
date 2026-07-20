<?php
/**
 * Resolve order transport.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Orders\Transport;

use Itsdesk\Connection\Transport\TransportFactory as ConnectionTransportFactory;

/**
 * Same mock|live switch as connection.
 */
final class OrderTransportFactory {

	/**
	 * Build transport.
	 */
	public static function make(): OrderTransportInterface {
		return 'live' === ConnectionTransportFactory::mode()
			? new HttpOrderTransport()
			: new MockOrderTransport();
	}
}
