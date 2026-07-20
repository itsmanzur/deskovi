<?php
/**
 * Order event transport contract.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Orders\Transport;

defined( 'ABSPATH' ) || exit;

/**
 * Push order status events to SaaS.
 */
interface OrderTransportInterface {

	/**
	 * mock|live
	 */
	public function mode(): string;

	/**
	 * Push status-changed event.
	 *
	 * @param array<string, mixed> $event Event payload.
	 * @return array<string, mixed>|\WP_Error|true
	 */
	public function push_status_changed( array $event );
}
