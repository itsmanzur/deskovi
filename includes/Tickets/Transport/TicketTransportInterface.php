<?php
/**
 * Ticket outbound transport contract.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Tickets\Transport;

/**
 * Push ticket events to SaaS.
 */
interface TicketTransportInterface {

	/**
	 * @return string mock|live
	 */
	public function mode(): string;

	/**
	 * Create remote ticket.
	 *
	 * @param array<string, mixed> $ticket Local ticket.
	 * @return array<string, mixed>|\WP_Error remote_id, saas_url
	 */
	public function create_ticket( array $ticket );

	/**
	 * Push a message.
	 *
	 * @param array<string, mixed> $ticket  Ticket.
	 * @param array<string, mixed> $message Message.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function push_message( array $ticket, array $message );

	/**
	 * Sync status change.
	 *
	 * @param array<string, mixed> $ticket Ticket.
	 * @return true|\WP_Error
	 */
	public function sync_status( array $ticket );
}
