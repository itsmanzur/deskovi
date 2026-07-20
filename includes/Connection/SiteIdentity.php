<?php
/**
 * Site cryptographic identity (server-side only).
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Connection;

defined( 'ABSPATH' ) || exit;

/**
 * Generates and stores site key material. Private key never exposed via REST.
 */
final class SiteIdentity {

	public const OPTION_KEY = 'itsdesk_site_identity';

	/**
	 * Load identity or empty defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge(
			array(
				'public_key'             => '',
				'private_key'            => '',
				'public_key_fingerprint' => '',
				'algorithm'              => '',
				'created_at'             => null,
			),
			$stored
		);
	}

	/**
	 * Create a new key pair.
	 *
	 * Tries OpenSSL RSA, then libsodium, then a deterministic mock pair so
	 * Local/Windows environments without a working openssl.cnf still connect.
	 *
	 * @return array<string, mixed>|\WP_Error Public fields only.
	 */
	public function generate() {
		$pair = $this->generate_openssl_pair();

		if ( null === $pair ) {
			$pair = $this->generate_sodium_pair();
		}

		if ( null === $pair ) {
			$pair = $this->generate_fallback_pair();
		}

		if ( null === $pair || '' === $pair['public_key'] || '' === $pair['private_key'] ) {
			return new \WP_Error(
				'itsdesk_keygen_failed',
				__( 'Could not generate site key pair.', 'deskovi' ),
				array( 'status' => 500 )
			);
		}

		$fingerprint = 'sha256:' . substr( hash( 'sha256', $pair['public_key'] ), 0, 16 );

		$payload = array(
			'public_key'             => $pair['public_key'],
			'private_key'            => $pair['private_key'],
			'public_key_fingerprint' => $fingerprint,
			'algorithm'              => $pair['algorithm'],
			'created_at'             => gmdate( 'c' ),
		);

		update_option( self::OPTION_KEY, $payload, false );

		return array(
			'public_key'             => $payload['public_key'],
			'public_key_fingerprint' => $fingerprint,
			'algorithm'              => $payload['algorithm'],
			'created_at'             => $payload['created_at'],
		);
	}

	/**
	 * Wipe identity.
	 */
	public function clear(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Public fingerprint for admin UI.
	 */
	public function fingerprint(): string {
		$identity = $this->get();
		return (string) ( $identity['public_key_fingerprint'] ?? '' );
	}

	/**
	 * Attempt RSA key generation via OpenSSL.
	 *
	 * @return array{public_key: string, private_key: string, algorithm: string}|null
	 */
	private function generate_openssl_pair(): ?array {
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			return null;
		}

		$config = array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		);

		$cnf = $this->locate_openssl_config();
		if ( null !== $cnf ) {
			$config['config'] = $cnf;
		}

		$resource = @openssl_pkey_new( $config );
		if ( false === $resource ) {
			return null;
		}

		$private_key = '';
		$exported    = @openssl_pkey_export( $resource, $private_key, null, $config );
		if ( false === $exported || '' === $private_key ) {
			return null;
		}

		$details = openssl_pkey_get_details( $resource );
		$public  = is_array( $details ) && isset( $details['key'] ) ? (string) $details['key'] : '';
		if ( '' === $public ) {
			return null;
		}

		return array(
			'public_key'  => $public,
			'private_key' => $private_key,
			'algorithm'   => 'rsa-2048',
		);
	}

	/**
	 * Attempt Ed25519 via libsodium.
	 *
	 * @return array{public_key: string, private_key: string, algorithm: string}|null
	 */
	private function generate_sodium_pair(): ?array {
		if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
			return null;
		}

		try {
			$keypair = sodium_crypto_sign_keypair();
			$public  = sodium_crypto_sign_publickey( $keypair );
			$secret  = sodium_crypto_sign_secretkey( $keypair );

			return array(
				'public_key'  => 'ed25519:' . base64_encode( $public ),
				'private_key' => 'ed25519:' . base64_encode( $secret ),
				'algorithm'   => 'ed25519',
			);
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Dev/mock-safe random material when crypto extensions are unusable.
	 *
	 * @return array{public_key: string, private_key: string, algorithm: string}
	 */
	private function generate_fallback_pair(): array {
		$seed = wp_generate_password( 64, true, true );

		return array(
			'public_key'  => 'ITSDESK-DEV-PUBLIC:' . hash( 'sha256', 'pub:' . $seed ),
			'private_key' => 'ITSDESK-DEV-PRIVATE:' . hash( 'sha256', 'priv:' . $seed ),
			'algorithm'   => 'dev-mock',
		);
	}

	/**
	 * Find an openssl.cnf that Local/Windows PHP can use.
	 */
	private function locate_openssl_config(): ?string {
		$candidates = array();

		if ( getenv( 'OPENSSL_CONF' ) ) {
			$candidates[] = (string) getenv( 'OPENSSL_CONF' );
		}

		if ( defined( 'OPENSSL_CONF' ) ) {
			$candidates[] = (string) OPENSSL_CONF;
		}

		// Common Local WP / Windows PHP layouts.
		$php_binary = defined( 'PHP_BINARY' ) ? PHP_BINARY : '';
		if ( is_string( $php_binary ) && '' !== $php_binary ) {
			$dir = dirname( $php_binary );
			$candidates[] = $dir . DIRECTORY_SEPARATOR . 'openssl.cnf';
			$candidates[] = $dir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
			$candidates[] = dirname( $dir ) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
		}

		$candidates[] = 'C:\\Windows\\System32\\OpenSSL\\openssl.cnf';
		$candidates[] = '/etc/ssl/openssl.cnf';
		$candidates[] = '/usr/local/ssl/openssl.cnf';
		$candidates[] = '/opt/homebrew/etc/openssl@3/openssl.cnf';

		foreach ( $candidates as $path ) {
			if ( is_string( $path ) && '' !== $path && is_readable( $path ) ) {
				return $path;
			}
		}

		return null;
	}
}
