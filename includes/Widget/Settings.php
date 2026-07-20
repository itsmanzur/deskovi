<?php
/**
 * Storefront widget settings.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Widget;

/**
 * Widget launcher configuration (assets load only when enabled — M5).
 */
final class Settings {

	public const OPTION_KEY = 'itsdesk_widget_settings';

	/**
	 * Defaults — disabled so no frontend assets in M1.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'   => false,
			'placement' => 'bottom-right',
			'theme'     => 'system',
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update( array $input ) {
		$current = $this->get();

		if ( array_key_exists( 'enabled', $input ) ) {
			$current['enabled'] = (bool) $input['enabled'];
		}

		if ( isset( $input['placement'] ) ) {
			$allowed = array( 'bottom-right', 'bottom-left' );
			$value   = (string) $input['placement'];
			if ( ! in_array( $value, $allowed, true ) ) {
				return new \WP_Error(
					'itsdesk_invalid_placement',
					__( 'Invalid widget placement.', 'deskovi' ),
					array( 'status' => 400 )
				);
			}
			$current['placement'] = $value;
		}

		if ( isset( $input['theme'] ) ) {
			$allowed = array( 'system', 'light', 'dark' );
			$value   = (string) $input['theme'];
			if ( ! in_array( $value, $allowed, true ) ) {
				return new \WP_Error(
					'itsdesk_invalid_theme',
					__( 'Invalid widget theme.', 'deskovi' ),
					array( 'status' => 400 )
				);
			}
			$current['theme'] = $value;
		}

		update_option( self::OPTION_KEY, $current, false );

		return $current;
	}
}
