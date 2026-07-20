<?php
/**
 * Local ticket bridge storage.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Tickets;

/**
 * Option-backed ticket store for M3 mock/bridge cache.
 */
final class TicketRepository {

	public const OPTION_KEY = 'itsdesk_tickets';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_values( $stored );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( string $id ): ?array {
		foreach ( $this->all() as $ticket ) {
			if ( is_array( $ticket ) && ( $ticket['id'] ?? '' ) === $id ) {
				return $ticket;
			}
		}
		return null;
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
		foreach ( $this->all() as $ticket ) {
			if ( is_array( $ticket ) && ( $ticket['idempotency_key'] ?? '' ) === $key ) {
				return $ticket;
			}
		}
		return null;
	}

	/**
	 * Tickets for a WP user.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function for_user( int $user_id ): array {
		return array_values(
			array_filter(
				$this->all(),
				static function ( $ticket ) use ( $user_id ) {
					return is_array( $ticket ) && (int) ( $ticket['customer_user_id'] ?? 0 ) === $user_id;
				}
			)
		);
	}

	/**
	 * Tickets for guest email (and optional order scope).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function for_guest_email( string $email, ?int $order_id = null ): array {
		$email = strtolower( sanitize_email( $email ) );
		return array_values(
			array_filter(
				$this->all(),
				static function ( $ticket ) use ( $email, $order_id ) {
					if ( ! is_array( $ticket ) ) {
						return false;
					}
					$ticket_email = strtolower( (string) ( $ticket['customer_email'] ?? '' ) );
					if ( $ticket_email !== $email ) {
						return false;
					}
					if ( null !== $order_id && $order_id > 0 ) {
						$linked = (int) ( $ticket['order_id'] ?? $ticket['guest_order_id'] ?? 0 );
						if ( $linked !== $order_id ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Delete by ids.
	 *
	 * @param array<int, string> $ids IDs.
	 */
	public function delete_ids( array $ids ): int {
		$ids = array_filter( array_map( 'strval', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$kept = array();
		$removed = 0;
		foreach ( $this->all() as $ticket ) {
			if ( ! is_array( $ticket ) ) {
				continue;
			}
			$id = (string) ( $ticket['id'] ?? '' );
			if ( in_array( $id, $ids, true ) ) {
				++$removed;
				continue;
			}
			$kept[] = $ticket;
		}
		update_option( self::OPTION_KEY, array_values( $kept ), false );
		return $removed;
	}

	/**
	 * Persist ticket (insert or replace).
	 *
	 * @param array<string, mixed> $ticket Ticket.
	 */
	public function save( array $ticket ): void {
		$all = $this->all();
		$found = false;
		foreach ( $all as $i => $row ) {
			if ( is_array( $row ) && ( $row['id'] ?? '' ) === ( $ticket['id'] ?? '' ) ) {
				$all[ $i ] = $ticket;
				$found     = true;
				break;
			}
		}
		if ( ! $found ) {
			array_unshift( $all, $ticket );
		}
		update_option( self::OPTION_KEY, array_values( $all ), false );
	}
}
