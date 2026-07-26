<?php
/**
 * General plugin activity / audit lines (tickets, auth, orders).
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Diagnostics;

defined( 'ABSPATH' ) || exit;

/**
 * Append-only activity log stored in options.
 */
final class ActivityLogger {

	public const OPTION_KEY = 'itsdesk_activity_log';

	/**
	 * Append an event.
	 *
	 * @param string               $type   Event type.
	 * @param string               $event  Human message.
	 * @param string               $result Result label.
	 * @param array<string, mixed> $meta   Optional meta (not shown in UI yet).
	 */
	public function log( string $type, string $event, string $result = 'OK', array $meta = array() ): void {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		array_unshift(
			$stored,
			array(
				'when'   => gmdate( 'Y-m-d H:i' ),
				'type'   => $type,
				'event'  => $event,
				'result' => $result,
				'meta'   => $meta,
			)
		);

		$stored = array_slice( $stored, 0, 100 );
		update_option( self::OPTION_KEY, $stored, false );
	}

	/**
	 * Recent activity lines for admin display.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function recent(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) || array() === $stored ) {
			return array(
				array(
					'when'   => gmdate( 'Y-m-d H:i' ),
					'type'   => 'System',
					'event'  => 'Deskovi plugin ready',
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
