<?php
/**
 * SaaS base URL resolution.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Connection;

defined( 'ABSPATH' ) || exit;

/**
 * Live SaaS endpoint config (defaults keep mock-safe).
 */
final class SaasConfig {

	/**
	 * Base URL without trailing slash.
	 */
	public static function base_url(): string {
		$url = 'https://app.deskovi.com';

		if ( defined( 'ITSDESK_SAAS_URL' ) && is_string( ITSDESK_SAAS_URL ) && '' !== ITSDESK_SAAS_URL ) {
			$url = ITSDESK_SAAS_URL;
		}

		/**
		 * Filter Deskovi SaaS base URL.
		 *
		 * @param string $url Base URL.
		 */
		$url = (string) apply_filters( 'itsdesk_saas_url', $url );

		return untrailingslashit( $url );
	}

	/**
	 * API root: {base}/api/v1
	 */
	public static function api_v1(): string {
		return self::base_url() . '/api/v1';
	}

	/**
	 * Whether non-HTTPS / private-host SaaS URLs are allowed (local DX only).
	 */
	public static function allows_insecure_url(): bool {
		/**
		 * Allow non-HTTPS or private SaaS hosts (e.g. local development).
		 * Do not enable on production sites submitted to WordPress.org review.
		 *
		 * @param bool $allow Default false.
		 */
		return (bool) apply_filters( 'itsdesk_allow_insecure_saas_url', false );
	}

	/**
	 * Validate SaaS base URL before live HTTP calls.
	 *
	 * @return true|\WP_Error
	 */
	public static function validate_for_request() {
		$url    = self::base_url();
		$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );

		if ( 'https' !== strtolower( $scheme ) && ! self::allows_insecure_url() ) {
			return new \WP_Error(
				'itsdesk_saas_url_insecure',
				__( 'Deskovi SaaS URL must use HTTPS in live mode.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $url || false === wp_parse_url( $url, PHP_URL_HOST ) ) {
			return new \WP_Error(
				'itsdesk_saas_url_invalid',
				__( 'Deskovi SaaS URL is invalid.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}
}
