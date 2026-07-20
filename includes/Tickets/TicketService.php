<?php
/**
 * Ticket bridge business logic.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Tickets;

use Itsdesk\Auth\GuestSession;
use Itsdesk\Connection\ActivityLogger;
use Itsdesk\Orders\OrderContext;

/**
 * Create / reply / status with ownership + idempotency.
 */
final class TicketService {

	private TicketRepository $repo;
	private OutboundSync $sync;
	private ActivityLogger $logger;

	/**
	 * Constructor.
	 */
	public function __construct( ?TicketRepository $repo = null, ?OutboundSync $sync = null ) {
		$this->repo   = $repo ?? new TicketRepository();
		$this->sync   = $sync ?? new OutboundSync();
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
		return $tickets;
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
			'remote_id'              => null,
			'status'                 => 'open',
			'category'               => $category,
			'subject'                => $subject,
			'order_id'               => $order_id > 0 ? $order_id : null,
			'guest_order_id'         => $guest_order_id,
			'order_snapshot'         => $order_snapshot,
			'customer_user_id'       => $user_id,
			'customer_email'         => $email,
			'customer_name'          => $name,
			'saas_url'               => null,
			'sync_status'            => 'pending',
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

		$this->sync->enqueue_create( $ticket_id );

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
		$this->sync->enqueue_message( $ticket_id, $msg_id );

		return $this->decorate_ticket( $ticket );
	}

	/**
	 * Update status (admin).
	 *
	 * @param string $source local|saas — saas skips outbound re-sync.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update_status( string $ticket_id, string $status, string $source = 'local' ) {
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

		if ( 'saas' !== $source ) {
			$this->sync->enqueue_status( $ticket_id );
		}

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
	 * Apply SaaS agent message to local bridge ticket.
	 *
	 * @param array<string, mixed> $payload Inbound event payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function apply_saas_message( array $payload ) {
		$ticket_meta = isset( $payload['ticket'] ) && is_array( $payload['ticket'] ) ? $payload['ticket'] : array();
		$message     = isset( $payload['message'] ) && is_array( $payload['message'] ) ? $payload['message'] : array();

		$ticket = $this->find_for_saas( $ticket_meta );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		$remote_msg = (string) ( $message['remote_id'] ?? '' );
		if ( '' !== $remote_msg ) {
			foreach ( $ticket['messages'] ?? array() as $existing ) {
				if ( ! is_array( $existing ) ) {
					continue;
				}
				if ( ( $existing['remote_id'] ?? '' ) === $remote_msg || ( $existing['id'] ?? '' ) === $remote_msg ) {
					return $this->decorate_ticket( $ticket );
				}
			}
		}

		$internal = ! empty( $message['internal'] );
		$now      = (string) ( $message['created_at'] ?? gmdate( 'c' ) );
		$msg_id   = '' !== $remote_msg ? $remote_msg : ( 'msg_' . wp_generate_uuid4() );

		$entry = array(
			'id'         => $msg_id,
			'remote_id'  => $remote_msg !== '' ? $remote_msg : null,
			'author'     => (string) ( $message['author'] ?? 'agent' ),
			'body'       => sanitize_textarea_field( (string) ( $message['body'] ?? '' ) ),
			'internal'   => $internal,
			'created_at' => $now,
			'attachments'=> array(),
		);

		if ( ! empty( $message['attachments'] ) && is_array( $message['attachments'] ) ) {
			foreach ( $message['attachments'] as $att ) {
				if ( ! is_array( $att ) ) {
					continue;
				}
				$entry['attachments'][] = array(
					'id'       => (string) ( $att['id'] ?? '' ),
					'filename' => sanitize_file_name( (string) ( $att['filename'] ?? 'file' ) ),
					'mime'     => sanitize_text_field( (string) ( $att['mime'] ?? '' ) ),
					'size'     => (int) ( $att['size'] ?? 0 ),
					'url'      => esc_url_raw( (string) ( $att['url'] ?? '' ) ),
				);
			}
		}

		if ( '' === $entry['body'] && empty( $entry['attachments'] ) ) {
			return new \WP_Error( 'itsdesk_inbound_empty', __( 'Empty inbound message.', 'deskovi' ), array( 'status' => 400 ) );
		}

		$ticket['messages'][] = $entry;
		$ticket['updated_at'] = gmdate( 'c' );
		if ( ! empty( $ticket_meta['remote_id'] ) ) {
			$ticket['remote_id'] = (string) $ticket_meta['remote_id'];
		}
		if ( ! $internal && 'agent' === $entry['author'] ) {
			$ticket['agent_last_reply_at'] = $now;
			if ( 'open' === ( $ticket['status'] ?? '' ) ) {
				$ticket['status'] = 'pending';
			}
		}

		$this->repo->save( $ticket );
		return $this->decorate_ticket( $ticket );
	}

	/**
	 * Apply SaaS status without outbound loop.
	 *
	 * @param array<string, mixed> $payload Inbound event.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function apply_saas_status( array $payload ) {
		$ticket_meta = isset( $payload['ticket'] ) && is_array( $payload['ticket'] ) ? $payload['ticket'] : array();
		$ticket      = $this->find_for_saas( $ticket_meta );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		$status = (string) ( $ticket_meta['status'] ?? '' );
		return $this->update_status( (string) $ticket['id'], $status, 'saas' );
	}

	/**
	 * @param array<string, mixed> $ticket_meta Ticket refs from SaaS.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function find_for_saas( array $ticket_meta ) {
		$local  = (string) ( $ticket_meta['local_ticket_id'] ?? '' );
		$remote = (string) ( $ticket_meta['remote_id'] ?? '' );

		if ( '' !== $local ) {
			$ticket = $this->repo->find( $local );
			if ( null !== $ticket ) {
				return $ticket;
			}
		}

		if ( '' !== $remote ) {
			foreach ( $this->repo->all() as $ticket ) {
				if ( (string) ( $ticket['remote_id'] ?? '' ) === $remote ) {
					return $ticket;
				}
			}
		}

		return new \WP_Error(
			'itsdesk_ticket_not_found',
			__( 'Ticket not found for inbound event.', 'deskovi' ),
			array( 'status' => 404 )
		);
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
		return $ticket;
	}
}
