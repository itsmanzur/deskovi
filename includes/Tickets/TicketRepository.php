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
	 * Paginated, filtered ticket listing for the admin screen — for large
	 * ticket tables where all() would load everything into memory on every
	 * request. Only the current page's rows are hydrated (messages +
	 * attachments), not the whole table.
	 *
	 * @param array{
	 *     page: int,
	 *     per_page: int,
	 *     search: string,
	 *     assignee: string,   // 'all' | 'unassigned' | numeric string (agent user id)
	 * } $args
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function paginated( array $args ): array {
		global $wpdb;
		$table = Schema::tickets_table();

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where = $this->build_where( $args );

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where['sql']}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal constant, $where['sql'] built from prepared fragments above.
		$total     = (int) ( $where['params']
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $where['params'] ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table; $count_sql is the table name plus fragments with placeholders, bound from $where['params'].
			: $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table; no user input in $count_sql when params is empty.

		$list_params = array_merge( $where['params'], array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT * FROM {$table} {$where['sql']} ORDER BY updated_at DESC LIMIT %d OFFSET %d", $list_params ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- table name is internal constant, $where['sql'] built from prepared fragments above.
			ARRAY_A
		);

		return array(
			'items' => $this->hydrate_rows( is_array( $rows ) ? $rows : array() ),
			'total' => $total,
		);
	}

	/**
	 * All tickets matching the given filters, unpaginated, NOT hydrated
	 * (no messages/attachments — CSV export only needs ticket-level
	 * fields, hydrating would be wasted work on a potentially large set).
	 * Capped at 10,000 rows as a safety limit.
	 *
	 * @param array<string, mixed> $args Filters (see build_where()).
	 * @return array<int, array<string, mixed>>
	 */
	public function matching( array $args ): array {
		global $wpdb;
		$table = Schema::tickets_table();

		$where = $this->build_where( $args );

		$sql = "SELECT * FROM {$table} {$where['sql']} ORDER BY updated_at DESC LIMIT 10000"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal constant, $where['sql'] built from prepared fragments above.

		$rows = $where['params']
			? $wpdb->get_results( $wpdb->prepare( $sql, $where['params'] ), ARRAY_A ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table; $sql is the table name plus fragments with placeholders, bound from $where['params'].
			: $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table; no user input when params is empty.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build a WHERE clause + bound params for the given list filters.
	 * Shared by paginated() and matching() so filter logic can't drift
	 * between the two.
	 *
	 * @param array{search?: string, assignee?: string, status?: string, priority?: string, category?: string, ids?: array<int, string>} $args `ids`, when present, limits the result to those tickets (an empty list matches nothing).
	 * @return array{sql: string, params: array<int, mixed>}
	 */
	private function build_where( array $args ): array {
		global $wpdb;
		$where  = array();
		$params = array();

		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(subject LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$assignee = (string) ( $args['assignee'] ?? 'all' );
		if ( 'unassigned' === $assignee ) {
			$where[] = 'assigned_agent_id IS NULL';
		} elseif ( is_numeric( $assignee ) ) {
			$where[]  = 'assigned_agent_id = %d';
			$params[] = (int) $assignee;
		}

		$status = (string) ( $args['status'] ?? '' );
		if ( 'active' === $status ) {
			$where[] = "status IN ('open', 'pending')";
		} elseif ( in_array( $status, array( 'open', 'pending', 'resolved', 'closed' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$priority = (string) ( $args['priority'] ?? '' );
		if ( in_array( $priority, array( 'low', 'normal', 'high', 'urgent' ), true ) ) {
			$where[]  = 'priority = %s';
			$params[] = $priority;
		}

		$category = (string) ( $args['category'] ?? '' );
		if ( '' !== $category ) {
			$where[]  = 'category = %s';
			$params[] = $category;
		}

		// An agent who may only work on their own tickets: assigned to them, or not assigned yet.
		if ( ! empty( $args['scope_agent'] ) ) {
			$where[]  = '(assigned_agent_id = %d OR assigned_agent_id IS NULL)';
			$params[] = (int) $args['scope_agent'];
		}

		if ( array_key_exists( 'ids', $args ) ) {
			$ids = array_slice( array_values( array_filter( array_map( 'strval', (array) $args['ids'] ) ) ), 0, 5000 );
			if ( array() === $ids ) {
				$where[] = '1 = 0';
			} else {
				$where[] = 'id IN (' . implode( ', ', array_fill( 0, count( $ids ), '%s' ) ) . ')';
				$params  = array_merge( $params, $ids );
			}
		}

		return array(
			'sql'    => $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '',
			'params' => $params,
		);
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
	 * Light rows (no messages) of tickets created in a period, for reports. Includes when
	 * the first visible agent reply was written.
	 *
	 * @param string $from  ISO 8601 UTC, inclusive.
	 * @param string $to    ISO 8601 UTC, exclusive.
	 * @param int    $limit Row cap (default 50,000).
	 * @return array<int, array<string, mixed>>
	 */
	public function report_rows( string $from, string $to, int $limit = 50000 ): array {
		global $wpdb;
		$tickets  = Schema::tickets_table();
		$messages = Schema::messages_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the only interpolated values are Deskovi's own table names.
		$rows     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin tables, no core caching API applies.
			$wpdb->prepare(
				"SELECT t.id, t.status, t.priority, t.category, t.order_id, t.assigned_agent_id, t.created_at, t.updated_at,
					(SELECT MIN(m.created_at) FROM {$messages} m WHERE m.ticket_id = t.id AND m.author = 'agent' AND m.internal = 0) AS first_agent_reply_at
				FROM {$tickets} t WHERE t.created_at >= %s AND t.created_at < %s ORDER BY t.created_at ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are internal constants.
				$from,
				$to,
				max( 1, min( 50000, $limit ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Light rows of tickets that are still being worked on (open or pending), for reports.
	 *
	 * @param int $limit Row cap.
	 * @return array<int, array<string, mixed>>
	 */
	public function open_rows( int $limit = 5000 ): array {
		global $wpdb;
		$table = Schema::tickets_table();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT id, status, priority, category, assigned_agent_id, created_at, updated_at FROM {$table} WHERE status IN ('open', 'pending') ORDER BY created_at ASC LIMIT %d", max( 1, min( 5000, $limit ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant.
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Ids of tickets not touched since a date and in one of the given statuses (data retention).
	 *
	 * @param string             $before   ISO 8601 UTC; tickets last updated before this.
	 * @param array<int, string> $statuses Statuses to include.
	 * @param int                $limit    Max ids.
	 * @return array<int, string>
	 */
	public function ids_older_than( string $before, array $statuses, int $limit ): array {
		global $wpdb;
		$statuses = array_values( array_intersect( array_map( 'strval', $statuses ), array( 'open', 'pending', 'resolved', 'closed' ) ) );
		if ( array() === $statuses ) {
			return array();
		}

		$table        = Schema::tickets_table();
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$ids          = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT id FROM {$table} WHERE updated_at < %s AND status IN ({$placeholders}) ORDER BY updated_at ASC LIMIT %d", array_merge( array( $before ), $statuses, array( max( 1, min( 1000, $limit ) ) ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table name is an internal constant; placeholders built from a validated list.
		);

		return is_array( $ids ) ? array_map( 'strval', $ids ) : array();
	}

	/**
	 * Tickets linked to an order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function for_order( int $order_id ): array {
		global $wpdb;
		$table = Schema::tickets_table();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY updated_at DESC", $order_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * Move every message and attachment of one ticket to another (used when tickets are merged).
	 * The files stay where they are: an attachment row knows its own path.
	 *
	 * @param string $from Source ticket id.
	 * @param string $to   Target ticket id.
	 * @return int Messages moved.
	 */
	public function move_content( string $from, string $to ): int {
		global $wpdb;

		$messages    = Schema::messages_table();
		$attachments = Schema::attachments_table();

		$moved = $wpdb->query( $wpdb->prepare( "UPDATE {$messages} SET ticket_id = %s WHERE ticket_id = %s", $to, $from ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, internal table name.
		$wpdb->query( $wpdb->prepare( "UPDATE {$attachments} SET ticket_id = %s WHERE ticket_id = %s", $to, $from ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table, internal table name.

		return is_int( $moved ) ? $moved : 0;
	}

	/**
	 * Move chosen messages, and the files that belong to them, from one ticket to another (used when a ticket is split).
	 *
	 * @param string             $from        Source ticket id.
	 * @param string             $to          Target ticket id.
	 * @param array<int, string> $message_ids Message ids to move.
	 * @return int Messages moved.
	 */
	public function move_messages( string $from, string $to, array $message_ids ): int {
		$message_ids = array_values( array_filter( array_map( 'strval', $message_ids ) ) );
		if ( array() === $message_ids ) {
			return 0;
		}

		global $wpdb;
		$messages     = Schema::messages_table();
		$attachments  = Schema::attachments_table();
		$placeholders = implode( ', ', array_fill( 0, count( $message_ids ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- internal table name, IN(...) placeholders built via array_fill()+prepare().
		$moved = $wpdb->query( $wpdb->prepare( "UPDATE {$messages} SET ticket_id = %s WHERE ticket_id = %s AND id IN ({$placeholders})", array_merge( array( $to, $from ), $message_ids ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- internal table name, IN(...) placeholders built via array_fill()+prepare().
		$wpdb->query( $wpdb->prepare( "UPDATE {$attachments} SET ticket_id = %s WHERE ticket_id = %s AND message_id IN ({$placeholders})", array_merge( array( $to, $from ), $message_ids ) ) );

		return is_int( $moved ) ? $moved : 0;
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

		/**
		 * Fires after tickets (and their messages/attachments) were deleted,
		 * so add-ons can remove data they keep per ticket.
		 *
		 * @param array<int, string> $ids Deleted ticket ids.
		 */
		do_action( 'itsdesk_tickets_deleted', $ids );

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
			'priority'              => (string) ( $ticket['priority'] ?? 'normal' ),
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
				'priority'               => $row['priority'] ?? 'normal',
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
