<?php
/**
 * Queue outbound ticket sync jobs.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Tickets;

use Itsdesk\Connection\ActivityLogger;
use Itsdesk\Connection\ConnectionStatus;
use Itsdesk\Queue\Status as QueueStatus;
use Itsdesk\Tickets\Transport\TicketTransportFactory;

/**
 * Syncs tickets to SaaS via Action Scheduler when possible.
 */
final class OutboundSync {

	public const HOOK_CREATE = 'itsdesk_sync_ticket_create';
	public const HOOK_MESSAGE = 'itsdesk_sync_ticket_message';
	public const HOOK_STATUS = 'itsdesk_sync_ticket_status';

	/**
	 * Register AS hooks.
	 */
	public function register(): void {
		add_action( self::HOOK_CREATE, array( $this, 'run_create' ), 10, 1 );
		add_action( self::HOOK_MESSAGE, array( $this, 'run_message' ), 10, 2 );
		add_action( self::HOOK_STATUS, array( $this, 'run_status' ), 10, 1 );
	}

	/**
	 * Schedule or run create sync.
	 */
	public function enqueue_create( string $ticket_id ): void {
		$this->enqueue( self::HOOK_CREATE, array( $ticket_id ) );
	}

	/**
	 * Schedule or run message sync.
	 */
	public function enqueue_message( string $ticket_id, string $message_id ): void {
		$this->enqueue( self::HOOK_MESSAGE, array( $ticket_id, $message_id ) );
	}

	/**
	 * Schedule or run status sync.
	 */
	public function enqueue_status( string $ticket_id ): void {
		$this->enqueue( self::HOOK_STATUS, array( $ticket_id ) );
	}

	/**
	 * @param array<int, mixed> $args Hook args.
	 */
	private function enqueue( string $hook, array $args ): void {
		$connection = ( new ConnectionStatus() )->get();
		$connected  = ( $connection['status'] ?? '' ) === 'connected';
		$mode       = TicketTransportFactory::make()->mode();

		// Live mode requires an active connection. Mock mode syncs locally for demos.
		if ( ! $connected && 'live' === $mode ) {
			( new ActivityLogger() )->log(
				'Ticket',
				__( 'Outbound sync skipped — store not connected', 'deskovi' ),
				'Skipped'
			);
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( $hook, $args, QueueStatus::GROUP );
			return;
		}

		// Immediate fallback when Action Scheduler is unavailable.
		if ( self::HOOK_CREATE === $hook ) {
			$this->run_create( (string) $args[0] );
		} elseif ( self::HOOK_MESSAGE === $hook ) {
			$this->run_message( (string) $args[0], (string) $args[1] );
		} else {
			$this->run_status( (string) $args[0] );
		}
	}

	/**
	 * AS callback: create.
	 */
	public function run_create( string $ticket_id ): void {
		$repo   = new TicketRepository();
		$ticket = $repo->find( $ticket_id );
		if ( null === $ticket ) {
			return;
		}

		$result = TicketTransportFactory::make()->create_ticket( $ticket );
		$logger = new ActivityLogger();

		if ( is_wp_error( $result ) ) {
			$ticket['sync_status'] = 'failed';
			$repo->save( $ticket );
			$logger->log( 'Ticket', $result->get_error_message(), 'Failed' );
			\Itsdesk\Diagnostics\Ops::push_error( 'ticket_sync', $result->get_error_message() );
			return;
		}

		$ticket['remote_id']   = (string) ( $result['remote_id'] ?? '' );
		$ticket['saas_url']    = (string) ( $result['saas_url'] ?? '' );
		$ticket['sync_status'] = 'synced';
		$ticket['updated_at']  = gmdate( 'c' );
		$repo->save( $ticket );
		\Itsdesk\Diagnostics\Ops::touch_cursor();

		$logger->log(
			'Ticket',
			sprintf(
				/* translators: %s: ticket id */
				__( 'Ticket synced to SaaS (%s)', 'deskovi' ),
				$ticket['id']
			),
			'OK'
		);
	}

	/**
	 * AS callback: message.
	 */
	public function run_message( string $ticket_id, string $message_id ): void {
		$repo   = new TicketRepository();
		$ticket = $repo->find( $ticket_id );
		if ( null === $ticket ) {
			return;
		}

		$message = null;
		foreach ( $ticket['messages'] ?? array() as $row ) {
			if ( is_array( $row ) && ( $row['id'] ?? '' ) === $message_id ) {
				$message = $row;
				break;
			}
		}
		if ( null === $message ) {
			return;
		}

		$result = TicketTransportFactory::make()->push_message( $ticket, $message );
		if ( is_wp_error( $result ) ) {
			( new ActivityLogger() )->log( 'Ticket', $result->get_error_message(), 'Failed' );
			\Itsdesk\Diagnostics\Ops::push_error( 'ticket_message_sync', $result->get_error_message() );
			return;
		}

		\Itsdesk\Diagnostics\Ops::touch_cursor();
		( new ActivityLogger() )->log(
			'Ticket',
			__( 'Ticket message synced', 'deskovi' ),
			'OK'
		);
	}

	/**
	 * AS callback: status.
	 */
	public function run_status( string $ticket_id ): void {
		$repo   = new TicketRepository();
		$ticket = $repo->find( $ticket_id );
		if ( null === $ticket ) {
			return;
		}

		$result = TicketTransportFactory::make()->sync_status( $ticket );
		if ( is_wp_error( $result ) ) {
			( new ActivityLogger() )->log( 'Ticket', $result->get_error_message(), 'Failed' );
			\Itsdesk\Diagnostics\Ops::push_error( 'ticket_status_sync', $result->get_error_message() );
			return;
		}

		\Itsdesk\Diagnostics\Ops::touch_cursor();
		( new ActivityLogger() )->log(
			'Ticket',
			sprintf(
				/* translators: %s: status */
				__( 'Ticket status synced (%s)', 'deskovi' ),
				(string) ( $ticket['status'] ?? '' )
			),
			'OK'
		);
	}
}
