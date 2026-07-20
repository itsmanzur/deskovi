<?php
/**
 * Signs outbound SaaS requests per connection-v1.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Connection;

defined( 'ABSPATH' ) || exit;

/**
 * Builds canonical payload + signature for site-authenticated calls.
 */
final class RequestSigner {

	/**
	 * @param array{public_key?: string, private_key?: string, algorithm?: string} $identity
	 * @return array{timestamp: string, nonce: string, body_hash: string, site_id: string, signature: string, idempotency_key: string}|\WP_Error
	 */
	public function sign(
		array $identity,
		string $site_uuid,
		string $method,
		string $path,
		string $raw_body,
		string $idempotency_key
	) {
		$private = (string) ( $identity['private_key'] ?? '' );
		$public  = (string) ( $identity['public_key'] ?? '' );
		$algo    = (string) ( $identity['algorithm'] ?? '' );

		if ( '' === $private || '' === $site_uuid ) {
			return new \WP_Error(
				'itsdesk_missing_identity',
				__( 'Site identity is missing. Reconnect the store.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$timestamp = (string) time();
		$nonce     = wp_generate_password( 24, false, false );
		$body_hash = hash( 'sha256', $raw_body );
		$path      = '/' . ltrim( $path, '/' );

		$canonical = implode(
			"\n",
			array(
				$timestamp,
				$nonce,
				$body_hash,
				$site_uuid,
				$idempotency_key,
				strtoupper( $method ),
				$path,
			)
		);

		$signature = $this->create_signature( $algo, $public, $private, $canonical );
		if ( is_wp_error( $signature ) ) {
			return $signature;
		}

		return array(
			'timestamp'       => $timestamp,
			'nonce'           => $nonce,
			'body_hash'       => $body_hash,
			'site_id'         => $site_uuid,
			'signature'       => $signature,
			'idempotency_key' => $idempotency_key,
		);
	}

	/**
	 * @return string|\WP_Error
	 */
	private function create_signature( string $algo, string $public, string $private, string $canonical ) {
		if ( 'dev-mock' === $algo || str_starts_with( $public, 'ITSDESK-DEV-PUBLIC:' ) ) {
			// Both sides share the public marker; HMAC is local-dev only.
			return base64_encode( hash_hmac( 'sha256', $canonical, $public, true ) );
		}

		if ( 'ed25519' === $algo || str_starts_with( $private, 'ed25519:' ) ) {
			return $this->sign_ed25519( $private, $canonical );
		}

		return $this->sign_openssl( $private, $canonical );
	}

	/**
	 * @return string|\WP_Error
	 */
	private function sign_ed25519( string $private, string $canonical ) {
		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			return new \WP_Error(
				'itsdesk_sodium_missing',
				__( 'Ed25519 signing requires libsodium.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		$sk = base64_decode( substr( $private, strlen( 'ed25519:' ) ), true );
		if ( false === $sk ) {
			return new \WP_Error(
				'itsdesk_bad_private_key',
				__( 'Invalid Ed25519 private key.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		try {
			$sig = sodium_crypto_sign_detached( $canonical, $sk );
			return base64_encode( $sig );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'itsdesk_sign_failed',
				__( 'Failed to sign request.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * @return string|\WP_Error
	 */
	private function sign_openssl( string $private, string $canonical ) {
		if ( ! function_exists( 'openssl_sign' ) ) {
			return new \WP_Error(
				'itsdesk_openssl_missing',
				__( 'OpenSSL signing is unavailable.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		$sig = '';
		$ok  = openssl_sign( $canonical, $sig, $private, OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			return new \WP_Error(
				'itsdesk_sign_failed',
				__( 'Failed to sign request.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		return base64_encode( $sig );
	}
}
