<?php
/**
 * Email notification toggles.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

/**
 * Which ticket-event emails are enabled — all local wp_mail(), no external service.
 */
final class NotificationSettings {

	public const OPTION_KEY = 'itsdesk_notification_settings';

	/**
	 * Defaults — every notification on by default.
	 *
	 * @return array<string, bool>
	 */
	public static function defaults(): array {
		return array(
			'agent_new_ticket' => true,
			'agent_new_reply'  => true,
			'customer_reply'   => true,
			'agent_assigned'   => true,
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array<string, bool>
	 */
	public function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Update settings (partial — only provided keys change).
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, bool>
	 */
	public function update( array $input ): array {
		$current = $this->get();

		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$current[ $key ] = (bool) $input[ $key ];
			}
		}

		update_option( self::OPTION_KEY, $current, false );

		return $current;
	}
}
