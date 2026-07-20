<?php
/**
 * Privacy and data-sharing settings.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Privacy;

/**
 * Stores merchant privacy preferences.
 */
final class Settings {

	public const OPTION_KEY = 'itsdesk_privacy_settings';

	/**
	 * Default privacy policy (data minimization).
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'sync_billing_address' => false,
			'sync_phone'           => false,
			'diagnostics_consent'  => false,
			'historical_import'    => 'off',
			'retention_days'       => 90,
		);
	}

	/**
	 * Get settings merged with defaults.
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
	 * Update settings from validated input.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update( array $input ) {
		$current = $this->get();

		if ( array_key_exists( 'sync_billing_address', $input ) ) {
			$current['sync_billing_address'] = (bool) $input['sync_billing_address'];
		}
		if ( array_key_exists( 'sync_phone', $input ) ) {
			$current['sync_phone'] = (bool) $input['sync_phone'];
		}
		if ( array_key_exists( 'diagnostics_consent', $input ) ) {
			$current['diagnostics_consent'] = (bool) $input['diagnostics_consent'];
		}

		if ( isset( $input['historical_import'] ) ) {
			$allowed = array( 'off', '30', '60', '90' );
			$value   = (string) $input['historical_import'];
			if ( ! in_array( $value, $allowed, true ) ) {
				return new \WP_Error(
					'itsdesk_invalid_import',
					__( 'Invalid historical import window.', 'deskovi' ),
					array( 'status' => 400 )
				);
			}
			$current['historical_import'] = $value;
		}

		if ( isset( $input['retention_days'] ) ) {
			$allowed = array( 30, 60, 90, 180 );
			$days    = (int) $input['retention_days'];
			if ( ! in_array( $days, $allowed, true ) ) {
				return new \WP_Error(
					'itsdesk_invalid_retention',
					__( 'Invalid retention period.', 'deskovi' ),
					array( 'status' => 400 )
				);
			}
			$current['retention_days'] = $days;
		}

		update_option( self::OPTION_KEY, $current, false );

		return $current;
	}
}
