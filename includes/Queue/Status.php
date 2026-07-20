<?php
/**
 * Action Scheduler / outbound queue visibility.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Summarizes pending/failed itsdesk jobs.
 */
final class Status {

	public const GROUP = 'itsdesk';

	/**
	 * Queue summary for admin.
	 *
	 * @return array{pending: int, failed: int, cursor: string}
	 */
	public function summary(): array {
		$pending = 0;
		$failed  = 0;

		if (
			class_exists( '\ActionScheduler' ) &&
			class_exists( '\ActionScheduler_Store' ) &&
			method_exists( \ActionScheduler::store(), 'query_actions' )
		) {
			$pending = (int) \ActionScheduler::store()->query_actions(
				array(
					'group'  => self::GROUP,
					'status' => \ActionScheduler_Store::STATUS_PENDING,
				),
				'count'
			);
			$failed = (int) \ActionScheduler::store()->query_actions(
				array(
					'group'  => self::GROUP,
					'status' => \ActionScheduler_Store::STATUS_FAILED,
				),
				'count'
			);
		}

		$cursor = (string) get_option( 'itsdesk_sync_cursor', '' );

		return array(
			'pending' => $pending,
			'failed'  => $failed,
			'cursor'  => $cursor,
		);
	}

	/**
	 * Activity / audit lines for admin table.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function activity(): array {
		$stored = get_option( 'itsdesk_activity_log', array() );
		if ( ! is_array( $stored ) || array() === $stored ) {
			return array(
				array(
					'when'   => gmdate( 'Y-m-d H:i' ),
					'type'   => 'System',
					'event'  => 'Deskovi plugin ready (no connection events yet)',
					'result' => 'OK',
				),
			);
		}

		$lines = array();
		foreach ( array_slice( $stored, 0, 50 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$lines[] = array(
				'when'   => (string) ( $row['when'] ?? '' ),
				'type'   => (string) ( $row['type'] ?? '' ),
				'event'  => (string) ( $row['event'] ?? '' ),
				'result' => (string) ( $row['result'] ?? '' ),
			);
		}

		return $lines;
	}
}
