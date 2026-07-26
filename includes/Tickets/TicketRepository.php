<?php
/**
 * Local ticket storage (custom tables — see Schema).
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

/**
 * DB-backed ticket store. Public API intentionally mirrors the original
 * option-backed repository so TicketService and callers need no changes.
 */
final class TicketRepository {

	/**
	 * Legacy option key. Kept only so migration helpers can detect and
	 * import pre-1.1 data — no longer written to.
	 */
	public const OPTION_KEY = 'itsdesk_tickets';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		global $wpdb;
		$table = Schema::tickets_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is internal constant, no core caching API applies to custom tables.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC", ARRAY_A );
		return $this->hydrate_rows( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( string $id ): ?array {
		global $wpdb;
		$table = Schema::tickets_table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$hydrated = $this->hydrate_rows( array( $row ) );
		return $hydrated[0] ?? null;
	}

	/**
	 * Find by idempotency key.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_by_idempotency( string $key ): ?array {
		if ( '' === $key ) {
			return null;
		}
		global $wpdb;
		$table = Schema::tickets_table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE idempotency_key = %s LIMIT 1", $key ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$hydrated = $this->hydrate_rows( array( $row ) );
		return $hydrated[0] ?? null;
	}

	/**
	 * Tickets for a WP user.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function for_user( int $user_id ): array {
		global $wpdb;
		$table = Schema::tickets_table();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE customer_user_id = %d ORDER BY updated_at DESC", $user_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return $this->hydrate_rows( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Tickets for guest email (and optional order scope).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function for_guest_email( string $email, ?int $order_id = null ): array {
		global $wpdb;
		$email = strtolower( sanitize_email( $email ) );
		$table = Schema::tickets_table();

		if ( null !== $order_id && $order_id > 0 ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE LOWER(customer_email) = %s AND (order_id = %d OR guest_order_id = %d) ORDER BY updated_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$email,
					$order_id,
					$order_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
				$wpdb->prepare( "SELECT * FROM {$table} WHERE LOWER(customer_email) = %s ORDER BY updated_at DESC", $email ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
		}

		return $this->hydrate_rows( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Delete by ids (ticket rows + their messages).
	 *
	 * @param array<int, string> $ids IDs.
	 */
	public function delete_ids( array $ids ): int {
		$ids = array_values( array_filter( array_map( 'strval', $ids ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;
		$tickets_table  = Schema::tickets_table();
		$messages_table = Schema::messages_table();

		$attachment_service = new AttachmentService();
		foreach ( $ids as $ticket_id ) {
			$attachment_service->delete_for_ticket( $ticket_id );
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is internal constant, IN(...) placeholders built via array_fill()+prepare().
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$messages_table} WHERE ticket_id IN ({$placeholders})", $ids ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is internal constant, IN(...) placeholders built via array_fill()+prepare().
		$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$tickets_table} WHERE id IN ({$placeholders})", $ids ) );

		return is_int( $removed ) ? $removed : 0;
	}

	/**
	 * Persist ticket (insert or replace ticket row; insert any new messages).
	 *
	 * @param array<string, mixed> $ticket Ticket.
	 */
	public function save( array $ticket ): void {
		global $wpdb;
		$tickets_table = Schema::tickets_table();
		$id            = (string) ( $ticket['id'] ?? '' );
		if ( '' === $id ) {
			return;
		}

		$order_snapshot = $ticket['order_snapshot'] ?? null;

		$data = array(
			'id'                    => $id,
			'status'                => (string) ( $ticket['status'] ?? 'open' ),
			'category'              => isset( $ticket['category'] ) ? (string) $ticket['category'] : null,
			'subject'               => (string) ( $ticket['subject'] ?? '' ),
			'order_id'              => ! empty( $ticket['order_id'] ) ? (int) $ticket['order_id'] : null,
			'guest_order_id'        => ! empty( $ticket['guest_order_id'] ) ? (int) $ticket['guest_order_id'] : null,
			'order_snapshot'        => null !== $order_snapshot ? wp_json_encode( $order_snapshot ) : null,
			'customer_user_id'      => ! empty( $ticket['customer_user_id'] ) ? (int) $ticket['customer_user_id'] : null,
			'customer_email'        => (string) ( $ticket['customer_email'] ?? '' ),
			'customer_name'         => (string) ( $ticket['customer_name'] ?? '' ),
			'idempotency_key'       => isset( $ticket['idempotency_key'] ) ? (string) $ticket['idempotency_key'] : null,
			'customer_last_read_at' => isset( $ticket['customer_last_read_at'] ) ? (string) $ticket['customer_last_read_at'] : null,
			'agent_last_reply_at'   => isset( $ticket['agent_last_reply_at'] ) ? (string) $ticket['agent_last_reply_at'] : null,
			'assigned_agent_id'     => ! empty( $ticket['assigned_agent_id'] ) ? (int) $ticket['assigned_agent_id'] : null,
			'created_at'            => (string) ( $ticket['created_at'] ?? gmdate( 'c' ) ),
			'updated_at'            => (string) ( $ticket['updated_at'] ?? gmdate( 'c' ) ),
		);

		$exists = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tickets_table} WHERE id = %s", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( $exists ) {
			$wpdb->update( $tickets_table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, no core caching API applies.
		} else {
			$wpdb->insert( $tickets_table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, no core caching API applies.
		}

		$this->save_messages( $id, is_array( $ticket['messages'] ?? null ) ? $ticket['messages'] : array() );
	}

	/**
	 * Insert any messages that don't already exist for this ticket.
	 *
	 * @param array<int, array<string, mixed>> $messages Messages.
	 */
	private function save_messages( string $ticket_id, array $messages ): void {
		if ( empty( $messages ) ) {
			return;
		}

		global $wpdb;
		$table = Schema::messages_table();

		$existing_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT id FROM {$table} WHERE ticket_id = %s", $ticket_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$existing_ids = is_array( $existing_ids ) ? $existing_ids : array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}
			$message_id = (string) ( $message['id'] ?? '' );
			if ( '' === $message_id || in_array( $message_id, $existing_ids, true ) ) {
				continue;
			}

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, no core caching API applies.
				$table,
				array(
					'id'         => $message_id,
					'ticket_id'  => $ticket_id,
					'author'     => (string) ( $message['author'] ?? 'customer' ),
					'body'       => (string) ( $message['body'] ?? '' ),
					'internal'   => ! empty( $message['internal'] ) ? 1 : 0,
					'created_at' => (string) ( $message['created_at'] ?? gmdate( 'c' ) ),
				)
			);
		}
	}

	/**
	 * Turn raw ticket rows into the array shape the rest of the plugin expects,
	 * batching the messages lookup to avoid N+1 queries.
	 *
	 * @param array<int, array<string, mixed>> $rows Raw $wpdb rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function hydrate_rows( array $rows ): array {
		if ( empty( $rows ) ) {
			return array();
		}

		$ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $row ) {
							return (string) ( $row['id'] ?? '' );
						},
						$rows
					)
				)
			)
		);

		$messages_by_ticket    = $this->messages_for_tickets( $ids );
		$attachments_by_ticket = $this->attachments_for_tickets( $ids );

		$tickets = array();
		foreach ( $rows as $row ) {
			$id           = (string) ( $row['id'] ?? '' );
			$attachments  = $attachments_by_ticket[ $id ] ?? array();
			$messages     = $messages_by_ticket[ $id ] ?? array();
			$messages     = $this->attach_files_to_messages( $messages, $attachments );

			$tickets[] = array(
				'id'                     => $id,
				'status'                 => $row['status'] ?? 'open',
				'category'               => $row['category'] ?? null,
				'subject'                => $row['subject'] ?? '',
				'order_id'               => null !== $row['order_id'] ? (int) $row['order_id'] : null,
				'guest_order_id'         => null !== $row['guest_order_id'] ? (int) $row['guest_order_id'] : null,
				'order_snapshot'         => ! empty( $row['order_snapshot'] ) ? json_decode( (string) $row['order_snapshot'], true ) : null,
				'customer_user_id'       => null !== $row['customer_user_id'] ? (int) $row['customer_user_id'] : null,
				'customer_email'         => $row['customer_email'] ?? '',
				'customer_name'          => $row['customer_name'] ?? '',
				'idempotency_key'        => $row['idempotency_key'] ?? null,
				'customer_last_read_at'  => $row['customer_last_read_at'] ?? null,
				'agent_last_reply_at'    => $row['agent_last_reply_at'] ?? null,
				'assigned_agent_id'      => null !== ( $row['assigned_agent_id'] ?? null ) ? (int) $row['assigned_agent_id'] : null,
				'created_at'             => $row['created_at'] ?? '',
				'updated_at'             => $row['updated_at'] ?? '',
				'messages'               => $messages,
				'attachments'            => $attachments,
			);
		}

		return $tickets;
	}

	/**
	 * Group each message with the attachments uploaded against its message id.
	 *
	 * @param array<int, array<string, mixed>> $messages    Messages.
	 * @param array<int, array<string, mixed>> $attachments All attachments for the ticket.
	 * @return array<int, array<string, mixed>>
	 */
	private function attach_files_to_messages( array $messages, array $attachments ): array {
		foreach ( $messages as &$message ) {
			$message_id            = (string) ( $message['id'] ?? '' );
			$message['attachments'] = array_values(
				array_filter(
					$attachments,
					static function ( $attachment ) use ( $message_id ) {
						return '' !== $message_id && (string) ( $attachment['message_id'] ?? '' ) === $message_id;
					}
				)
			);
		}
		unset( $message );

		return $messages;
	}

	/**
	 * Batch-load attachment metadata (no file contents) for a set of ticket ids.
	 *
	 * @param array<int, string> $ticket_ids Ticket ids.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function attachments_for_tickets( array $ticket_ids ): array {
		if ( empty( $ticket_ids ) ) {
			return array();
		}

		global $wpdb;
		$table        = Schema::attachments_table();
		$placeholders = implode( ', ', array_fill( 0, count( $ticket_ids ), '%s' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare(
				"SELECT id, ticket_id, message_id, filename, mime_type, size_bytes, uploaded_by, created_at FROM {$table} WHERE ticket_id IN ({$placeholders}) ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table name is internal constant, IN(...) placeholders built via array_fill()+prepare().
				$ticket_ids
			),
			ARRAY_A
		);

		$grouped = array();
		foreach ( (array) $rows as $row ) {
			$ticket_id = (string) ( $row['ticket_id'] ?? '' );
			if ( '' === $ticket_id ) {
				continue;
			}
			$grouped[ $ticket_id ][] = array(
				'id'          => $row['id'],
				'message_id'  => $row['message_id'],
				'filename'    => $row['filename'],
				'mime_type'   => $row['mime_type'],
				'size_bytes'  => (int) $row['size_bytes'],
				'uploaded_by' => $row['uploaded_by'],
				'created_at'  => $row['created_at'],
			);
		}

		return $grouped;
	}

	/**
	 * Batch-load messages for a set of ticket ids, grouped by ticket id.
	 *
	 * @param array<int, string> $ticket_ids Ticket ids.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function messages_for_tickets( array $ticket_ids ): array {
		if ( empty( $ticket_ids ) ) {
			return array();
		}

		global $wpdb;
		$table        = Schema::messages_table();
		$placeholders = implode( ', ', array_fill( 0, count( $ticket_ids ), '%s' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table name is internal constant, IN(...) placeholders built via array_fill()+prepare().
			$wpdb->prepare( "SELECT * FROM {$table} WHERE ticket_id IN ({$placeholders}) ORDER BY created_at ASC, id ASC", $ticket_ids ),
			ARRAY_A
		);

		$grouped = array();
		foreach ( (array) $rows as $row ) {
			$ticket_id = (string) ( $row['ticket_id'] ?? '' );
			if ( '' === $ticket_id ) {
				continue;
			}
			$grouped[ $ticket_id ][] = array(
				'id'         => $row['id'],
				'author'     => $row['author'],
				'body'       => $row['body'],
				'internal'   => ! empty( $row['internal'] ),
				'created_at' => $row['created_at'],
			);
		}

		return $grouped;
	}
}
