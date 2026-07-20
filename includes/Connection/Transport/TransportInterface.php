<?php
/**
 * SaaS connection transport contract.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection\Transport;

/**
 * Swappable mock / HTTP transport.
 */
interface TransportInterface {

	/**
	 * Transport mode label.
	 */
	public function mode(): string;

	/**
	 * Begin authorize handshake.
	 *
	 * @param string $site_url Store URL.
	 * @param string $state    CSRF state.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function start( string $site_url, string $state );

	/**
	 * Exchange one-time code for site binding.
	 *
	 * @param array<string, mixed> $payload Exchange payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function exchange( array $payload );

	/**
	 * Signed health ping.
	 *
	 * @param array<string, mixed> $connection Current connection state.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function health( array $connection );

	/**
	 * Notify SaaS of key rotation.
	 *
	 * @param array<string, mixed> $connection Current connection.
	 * @param string               $public_key New public key.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function rotate( array $connection, string $public_key );

	/**
	 * Revoke site on SaaS.
	 *
	 * @param array<string, mixed> $connection Current connection.
	 * @return true|\WP_Error
	 */
	public function disconnect( array $connection );
}
