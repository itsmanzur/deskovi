<?php
/**
 * Verifies SaaS → WP signed inbound requests.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Connection;

defined( 'ABSPATH' ) || exit;

/**
 * HMAC verification for inbound-v1 events.
 */
final class InboundVerifier {

	/**
	 * @param array<string, string> $headers Normalized header map.
	 * @return true|\WP_Error
	 */
	public function verify( string $raw_body, array $headers, string $path ) {
		$secret = ( new DeliverySecret() )->get();
		if ( '' === $secret ) {
			return new \WP_Error(
				'itsdesk_no_delivery_secret',
				__( 'Delivery secret missing. Reconnect the store.', 'deskovi' ),
				array( 'status' => 401 )
			);
		}

		$connection = ( new ConnectionStatus() )->get();
		$site_uuid  = (string) ( $connection['site_uuid'] ?? '' );

		$timestamp       = $headers['timestamp'] ?? '';
		$nonce           = $headers['nonce'] ?? '';
		$body_hash       = $headers['body_hash'] ?? '';
		$site_id         = $headers['site_id'] ?? '';
		$signature       = $headers['signature'] ?? '';
		$idempotency_key = $headers['idempotency_key'] ?? '';

		if ( '' === $timestamp || '' === $nonce || '' === $body_hash || '' === $site_id || '' === $signature || '' === $idempotency_key ) {
			return new \WP_Error( 'itsdesk_inbound_headers', __( 'Missing signature headers.', 'deskovi' ), array( 'status' => 401 ) );
		}

		if ( '' === $site_uuid || ! hash_equals( $site_uuid, $site_id ) ) {
			return new \WP_Error( 'itsdesk_inbound_site', __( 'Site id mismatch.', 'deskovi' ), array( 'status' => 401 ) );
		}

		if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > 300 ) {
			return new \WP_Error( 'itsdesk_inbound_ts', __( 'Expired timestamp.', 'deskovi' ), array( 'status' => 401 ) );
		}

		$nonce_key = 'itsdesk_inbound_nonce_' . md5( $nonce );
		if ( get_transient( $nonce_key ) ) {
			return new \WP_Error( 'itsdesk_inbound_replay', __( 'Replay detected.', 'deskovi' ), array( 'status' => 409 ) );
		}
		set_transient( $nonce_key, 1, 5 * MINUTE_IN_SECONDS );

		$computed = hash( 'sha256', $raw_body );
		if ( ! hash_equals( $computed, $body_hash ) ) {
			return new \WP_Error( 'itsdesk_inbound_hash', __( 'Body hash mismatch.', 'deskovi' ), array( 'status' => 401 ) );
		}

		$path      = '/' . ltrim( $path, '/' );
		$canonical = implode(
			"\n",
			array(
				$timestamp,
				$nonce,
				$body_hash,
				$site_id,
				$idempotency_key,
				'POST',
				$path,
			)
		);

		$expected = base64_encode( hash_hmac( 'sha256', $canonical, $secret, true ) );
		if ( ! hash_equals( $expected, $signature ) ) {
			return new \WP_Error( 'itsdesk_inbound_sig', __( 'Invalid signature.', 'deskovi' ), array( 'status' => 401 ) );
		}

		$idem_key = 'itsdesk_inbound_idem_' . md5( $idempotency_key );
		if ( get_transient( $idem_key ) ) {
			return new \WP_Error( 'itsdesk_inbound_idempotent', __( 'Already processed.', 'deskovi' ), array( 'status' => 200, 'idempotent' => true ) );
		}

		return true;
	}

	public function mark_idempotent( string $idempotency_key ): void {
		set_transient( 'itsdesk_inbound_idem_' . md5( $idempotency_key ), 1, DAY_IN_SECONDS );
	}
}
