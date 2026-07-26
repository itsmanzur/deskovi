<?php
/**
 * Ticket bridge business logic.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Admin\Capabilities;
use Itsdesk\Auth\GuestSession;
use Itsdesk\Diagnostics\ActivityLogger;
use Itsdesk\Orders\OrderContext;

/**
 * Create / reply / status with ownership + idempotency.
 */
final class TicketService {

	private TicketRepository $repo;
	private ActivityLogger $logger;

	/**
	 * Constructor.
	 */
	public function __construct( ?TicketRepository $repo = null ) {
		$this->repo   = $repo ?? new TicketRepository();
		$this->logger = new ActivityLogger();
	}

	/**
	 * List all (admin).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_all(): array {
		$tickets = $this->repo->all();
		usort(
			$tickets,
			static function ( $a, $b ) {
				return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
			}
		);
		return array_map( array( $this, 'add_agent_name' ), $tickets );
	}

	/**
	 * List for customer (WP user).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_user( int $user_id ): array {
		$tickets = $this->repo->for_user( $user_id );
		return $this->sort_and_decorate( $tickets );
	}

	/**
	 * List for guest session email/order.
	 *
	 * @param array{email: string, order_id: int} $guest Guest session.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_guest( array $guest ): array {
		$email    = (string) ( $guest['email'] ?? '' );
		$order_id = (int) ( $guest['order_id'] ?? 0 );
		$tickets  = $this->repo->for_guest_email( $email, $order_id > 0 ? $order_id : null );
		return $this->sort_and_decorate( $tickets );
	}

	/**
	 * Unread count for current customer context.
	 */
	public function unread_count_for_user( int $user_id ): int {
		$count = 0;
		foreach ( $this->list_for_user( $user_id ) as $ticket ) {
			if ( ! empty( $ticket['unread'] ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Unread count for guest.
	 *
	 * @param array{email: string, order_id: int} $guest Guest.
	 */
	public function unread_count_for_guest( array $guest ): int {
		$count = 0;
		foreach ( $this->list_for_guest( $guest ) as $ticket ) {
			if ( ! empty( $ticket['unread'] ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Get ticket if allowed.
	 *
	 * @param array{email: string, order_id: int}|null $guest Guest session.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( string $id, ?int $user_id = null, bool $is_admin = false, ?array $guest = null ) {
		$ticket = $this->repo->find( $id );
		if ( null === $ticket ) {
			return new \WP_Error(
				'itsdesk_ticket_not_found',
				__( 'Ticket not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $is_admin ) {
			$allowed = false;
			if ( null !== $user_id && $user_id > 0 && (int) ( $ticket['customer_user_id'] ?? 0 ) === $user_id ) {
				$allowed = true;
			}
			if ( ! $allowed && is_array( $guest ) ) {
				$g_email = strtolower( (string) ( $guest['email'] ?? '' ) );
				$t_email = strtolower( (string) ( $ticket['customer_email'] ?? '' ) );
				$g_order = (int) ( $guest['order_id'] ?? 0 );
				$t_order = (int) ( $ticket['order_id'] ?? $ticket['guest_order_id'] ?? 0 );
				if ( $g_email && $g_email === $t_email && ( $g_order <= 0 || $g_order === $t_order ) ) {
					$allowed = true;
				}
			}
			if ( ! $allowed ) {
				return new \WP_Error(
					'itsdesk_ticket_forbidden',
					__( 'You cannot access this ticket.', 'deskovi' ),
					array( 'status' => 403 )
				);
			}
		}

		return $this->decorate_ticket( $ticket );
	}

	/**
	 * Mark ticket read for customer.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function mark_read( string $id, ?int $user_id, ?array $guest = null ) {
		$ticket = $this->get( $id, $user_id, false, $guest );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}
		$ticket['customer_last_read_at'] = gmdate( 'c' );
		$ticket['updated_at']            = $ticket['updated_at'] ?? gmdate( 'c' );
		$this->repo->save( $ticket );
		return $this->decorate_ticket( $ticket );
	}

	/**
	 * Create ticket.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( array $input, bool $is_admin = false ) {
		$idempotency = isset( $input['idempotency_key'] ) ? sanitize_text_field( (string) $input['idempotency_key'] ) : '';
		if ( '' !== $idempotency ) {
			$existing = $this->repo->find_by_idempotency( $idempotency );
			if ( null !== $existing ) {
				return $existing;
			}
		} else {
			$idempotency = 'idem_' . wp_generate_uuid4();
		}

		$subject  = isset( $input['subject'] ) ? sanitize_text_field( (string) $input['subject'] ) : '';
		$body     = isset( $input['body'] ) ? sanitize_textarea_field( (string) $input['body'] ) : '';
		$category = isset( $input['category'] ) ? sanitize_key( (string) $input['category'] ) : 'other';

		if ( '' === $subject || '' === $body ) {
			return new \WP_Error(
				'itsdesk_ticket_invalid',
				__( 'Subject and message are required.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		if ( ! Categories::is_valid( $category ) ) {
			return new \WP_Error(
				'itsdesk_ticket_category',
				__( 'Invalid ticket category.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$user_id = isset( $input['customer_user_id'] ) ? (int) $input['customer_user_id'] : 0;
		$email   = isset( $input['customer_email'] ) ? sanitize_email( (string) $input['customer_email'] ) : '';
		$name    = isset( $input['customer_name'] ) ? sanitize_text_field( (string) $input['customer_name'] ) : '';

		$guest_order_id = null;
		if ( ! $is_admin && $user_id <= 0 ) {
			$guest = ( new GuestSession() )->current();
			if ( null === $guest ) {
				return new \WP_Error(
					'itsdesk_ticket_auth',
					__( 'Login or guest verification required to open a ticket.', 'deskovi' ),
					array( 'status' => 401 )
				);
			}
			$email          = (string) $guest['email'];
			$guest_order_id = (int) $guest['order_id'];
			$user_id        = 0;
			if ( '' === $name ) {
				$name = __( 'Guest', 'deskovi' );
			}
		}

		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$email = $email ? $email : $user->user_email;
				$name  = $name ? $name : $user->display_name;
			}
		}

		$order_id = isset( $input['order_id'] ) ? absint( $input['order_id'] ) : 0;
		if ( $order_id <= 0 && $guest_order_id ) {
			$order_id = $guest_order_id;
		}
		if ( $order_id > 0 ) {
			$order_check = $this->validate_order( $order_id, $user_id, $is_admin, $email );
			if ( is_wp_error( $order_check ) ) {
				return $order_check;
			}
		}

		$now        = gmdate( 'c' );
		$message_id = 'msg_' . wp_generate_uuid4();
		$ticket_id  = 'tkt_' . wp_generate_uuid4();

		$order_snapshot = null;
		if ( $order_id > 0 ) {
			$snap = ( new OrderContext() )->get_compact_snapshot( $order_id, $user_id > 0 ? $user_id : null, $is_admin || null !== $guest_order_id );
			if ( ! is_wp_error( $snap ) ) {
				$order_snapshot = $snap;
			}
		}

		$ticket = array(
			'id'                     => $ticket_id,
			'status'                 => 'open',
			'category'               => $category,
			'subject'                => $subject,
			'order_id'               => $order_id > 0 ? $order_id : null,
			'guest_order_id'         => $guest_order_id,
			'order_snapshot'         => $order_snapshot,
			'customer_user_id'       => $user_id,
			'customer_email'         => $email,
			'customer_name'          => $name,
			'idempotency_key'        => $idempotency,
			'customer_last_read_at'  => $now,
			'agent_last_reply_at'    => null,
			'created_at'             => $now,
			'updated_at'             => $now,
			'messages'               => array(
				array(
					'id'         => $message_id,
					'author'     => 'customer',
					'body'       => $body,
					'internal'   => false,
					'created_at' => $now,
				),
			),
		);

		$this->repo->save( $ticket );
		$this->logger->log(
			'Ticket',
			sprintf(
				/* translators: %s: subject */
				__( 'Ticket created: %s', 'deskovi' ),
				$subject
			),
			'OK'
		);

		return $ticket;
	}

	/**
	 * Add reply.
	 *
	 * @param array{email: string, order_id: int}|null $guest Guest session.
	 * @param array<string, mixed>                     $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function reply( string $ticket_id, array $input, ?int $user_id, bool $is_admin, ?array $guest = null ) {
		$ticket = $this->get( $ticket_id, $user_id, $is_admin, $guest );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		$body = isset( $input['body'] ) ? sanitize_textarea_field( (string) $input['body'] ) : '';
		if ( '' === $body ) {
			return new \WP_Error(
				'itsdesk_reply_empty',
				__( 'Reply message is required.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$internal = $is_admin && ! empty( $input['internal'] );
		$author   = $is_admin ? 'agent' : 'customer';
		$now      = gmdate( 'c' );
		$msg_id   = 'msg_' . wp_generate_uuid4();

		$message = array(
			'id'         => $msg_id,
			'author'     => $author,
			'body'       => $body,
			'internal'   => (bool) $internal,
			'created_at' => $now,
		);

		$ticket['messages'][] = $message;
		$ticket['updated_at'] = $now;
		if ( ! $internal && 'resolved' === ( $ticket['status'] ?? '' ) ) {
			$ticket['status'] = 'open';
		}
		if ( $is_admin && ! $internal && 'open' === ( $ticket['status'] ?? '' ) ) {
			$ticket['status'] = 'pending';
		}
		if ( $is_admin && ! $internal ) {
			$ticket['agent_last_reply_at'] = $now;
		}
		if ( ! $is_admin ) {
			$ticket['customer_last_read_at'] = $now;
		}

		$this->repo->save( $ticket );
		$this->logger->log( 'Ticket', __( 'Reply added to ticket', 'deskovi' ), 'OK' );

		return $this->decorate_ticket( $ticket );
	}

	/**
	 * Update status (admin).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_status( string $ticket_id, string $status ) {
		$allowed = array( 'open', 'pending', 'resolved', 'closed' );
		$status  = sanitize_key( $status );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new \WP_Error(
				'itsdesk_status_invalid',
				__( 'Invalid ticket status.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$ticket = $this->repo->find( $ticket_id );
		if ( null === $ticket ) {
			return new \WP_Error(
				'itsdesk_ticket_not_found',
				__( 'Ticket not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		$ticket['status']     = $status;
		$ticket['updated_at'] = gmdate( 'c' );
		$this->repo->save( $ticket );

		$this->logger->log(
			'Ticket',
			sprintf(
				/* translators: %s: status */
				__( 'Ticket status set to %s', 'deskovi' ),
				$status
			),
			'OK'
		);

		return $ticket;
	}

	/**
	 * Assign (or unassign) a ticket to a support agent (admin).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function assign( string $ticket_id, ?int $agent_id ) {
		$ticket = $this->repo->find( $ticket_id );
		if ( null === $ticket ) {
			return new \WP_Error(
				'itsdesk_ticket_not_found',
				__( 'Ticket not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		if ( null === $agent_id || $agent_id <= 0 ) {
			$ticket['assigned_agent_id'] = null;
			$ticket['updated_at']        = gmdate( 'c' );
			$this->repo->save( $ticket );
			$this->logger->log( 'Ticket', __( 'Ticket unassigned', 'deskovi' ), 'OK' );
			return $ticket;
		}

		$agent = get_userdata( $agent_id );
		if ( ! $agent || ! $agent->has_cap( Capabilities::CAP ) ) {
			return new \WP_Error(
				'itsdesk_assign_invalid_agent',
				__( 'That user is not a support agent.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$ticket['assigned_agent_id'] = $agent_id;
		$ticket['updated_at']        = gmdate( 'c' );
		$this->repo->save( $ticket );

		$this->logger->log(
			'Ticket',
			sprintf(
				/* translators: %s: agent display name */
				__( 'Ticket assigned to %s', 'deskovi' ),
				$agent->display_name
			),
			'OK'
		);

		return $ticket;
	}

	/**
	 * Link or unlink an order on a ticket (admin).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function link_order( string $ticket_id, ?int $order_id, bool $is_admin = true ) {
		$ticket = $this->repo->find( $ticket_id );
		if ( null === $ticket ) {
			return new \WP_Error(
				'itsdesk_ticket_not_found',
				__( 'Ticket not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		$user_id = (int) ( $ticket['customer_user_id'] ?? 0 );

		if ( null === $order_id || $order_id <= 0 ) {
			$ticket['order_id']       = null;
			$ticket['order_snapshot'] = null;
			$ticket['updated_at']     = gmdate( 'c' );
			$this->repo->save( $ticket );
			$this->logger->log( 'Ticket', __( 'Order unlinked from ticket', 'deskovi' ), 'OK' );
			return $ticket;
		}

		$check = $this->validate_order( $order_id, $user_id, $is_admin );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$snap = ( new OrderContext() )->get_compact_snapshot( $order_id, $user_id > 0 ? $user_id : null, $is_admin );
		if ( is_wp_error( $snap ) ) {
			return $snap;
		}

		$ticket['order_id']       = $order_id;
		$ticket['order_snapshot'] = $snap;
		$ticket['updated_at']     = gmdate( 'c' );
		$this->repo->save( $ticket );

		$this->logger->log(
			'Ticket',
			sprintf(
				/* translators: %d: order id */
				__( 'Order #%d linked to ticket', 'deskovi' ),
				$order_id
			),
			'OK'
		);

		return $ticket;
	}

	/**
	 * Validate WooCommerce order ownership via CRUD.
	 *
	 * @return true|\WP_Error
	 */
	private function validate_order( int $order_id, int $user_id, bool $is_admin, string $guest_email = '' ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return true;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error(
				'itsdesk_order_missing',
				__( 'Order not found.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		if ( $is_admin ) {
			return true;
		}

		$order_user = (int) $order->get_user_id();
		if ( $order_user > 0 && $user_id > 0 && $order_user === $user_id ) {
			return true;
		}

		if ( $guest_email ) {
			$billing = strtolower( (string) $order->get_billing_email() );
			if ( $billing && $billing === strtolower( $guest_email ) ) {
				return true;
			}
		}

		return new \WP_Error(
			'itsdesk_order_forbidden',
			__( 'You can only link your own orders.', 'deskovi' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $tickets Tickets.
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_and_decorate( array $tickets ): array {
		usort(
			$tickets,
			static function ( $a, $b ) {
				return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
			}
		);
		return array_map( array( $this, 'decorate_ticket' ), $tickets );
	}

	/**
	 * @param array<string, mixed> $ticket Ticket.
	 * @return array<string, mixed>
	 */
	private function decorate_ticket( array $ticket ): array {
		$read  = (string) ( $ticket['customer_last_read_at'] ?? '' );
		$agent = (string) ( $ticket['agent_last_reply_at'] ?? '' );
		$ticket['unread'] = ( '' !== $agent && ( '' === $read || strcmp( $agent, $read ) > 0 ) );
		return $this->add_agent_name( $ticket );
	}

	/**
	 * Add the assigned agent's display name for convenience in API responses.
	 *
	 * @param array<string, mixed> $ticket Ticket.
	 * @return array<string, mixed>
	 */
	private function add_agent_name( array $ticket ): array {
		$agent_id = ! empty( $ticket['assigned_agent_id'] ) ? (int) $ticket['assigned_agent_id'] : 0;
		if ( $agent_id <= 0 ) {
			$ticket['assigned_agent_name'] = null;
			return $ticket;
		}
		$agent                          = get_userdata( $agent_id );
		$ticket['assigned_agent_name']  = $agent ? $agent->display_name : null;
		return $ticket;
	}
}
