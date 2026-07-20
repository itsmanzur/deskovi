<?php
/**
 * Connection / sync activity audit lines.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection;

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
}
