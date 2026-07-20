<?php
/**
 * Resolve ticket transport.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets\Transport;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Connection\Transport\TransportFactory as ConnectionTransportFactory;

/**
 * Uses same mode switch as connection (mock|live).
 */
final class TicketTransportFactory {

	/**
	 * Build transport.
	 */
	public static function make(): TicketTransportInterface {
		return 'live' === ConnectionTransportFactory::mode()
			? new HttpTicketTransport()
			: new MockTicketTransport();
	}
}
