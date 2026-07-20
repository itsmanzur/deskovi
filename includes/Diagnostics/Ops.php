<?php
/**
 * Sync cursor + diagnostic error helpers.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Diagnostics;

defined( 'ABSPATH' ) || exit;

/**
 * Shared operational markers for beta.
 */
final class Ops {

	public const CURSOR_OPTION = 'itsdesk_sync_cursor';
	public const ERRORS_OPTION = 'itsdesk_diagnostic_errors';

	/**
	 * Bump outbound sync cursor.
	 */
	public static function touch_cursor(): void {
		update_option( self::CURSOR_OPTION, gmdate( 'c' ), false );
	}

	/**
	 * Append sanitized diagnostic error.
	 */
	public static function push_error( string $code, string $message ): void {
		$stored = get_option( self::ERRORS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		array_unshift(
			$stored,
			array(
				'time'    => gmdate( 'c' ),
				'code'    => sanitize_key( $code ),
				'message' => sanitize_text_field( $message ),
			)
		);
		update_option( self::ERRORS_OPTION, array_slice( $stored, 0, 50 ), false );
	}
}
