<?php
/**
 * Live HTTPS transport for Deskovi SaaS.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection\Transport;

use Itsdesk\Connection\SaasConfig;
use Itsdesk\Connection\SignedHttpClient;

/**
 * Signed SaaS HTTP calls (connection-v1).
 */
final class HttpTransport implements TransportInterface {

	private SignedHttpClient $http;

	public function __construct( ?SignedHttpClient $http = null ) {
		$this->http = $http ?? new SignedHttpClient();
	}

	/**
	 * {@inheritdoc}
	 */
	public function mode(): string {
		return 'live';
	}

	/**
	 * {@inheritdoc}
	 */
	public function start( string $site_url, string $state ) {
		$base = SaasConfig::base_url();

		return array(
			'mode'            => 'live',
			'state'           => $state,
			'authorize_url'   => $base . '/',
			'mock_workspaces' => array(
				array(
					'id'   => 'ws_local_dev',
					'name' => 'Local Dev',
				),
			),
			'expires_in'      => 600,
			'site_url'        => $site_url,
			'hint'            => 'Generate a connect code in your Deskovi workspace, then paste it here.',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function exchange( array $payload ) {
		$url = SaasConfig::api_v1() . '/connect/exchange';

		$body = array(
			'code'           => (string) ( $payload['code'] ?? '' ),
			'site_url'       => (string) ( $payload['site_url'] ?? '' ),
			'public_key'     => (string) ( $payload['public_key'] ?? '' ),
			'plugin_version' => (string) ( $payload['plugin_version'] ?? '' ),
			'state'          => (string) ( $payload['state'] ?? '' ),
		);

		return $this->http->post_json( $url, $body );
	}

	/**
	 * {@inheritdoc}
	 */
	public function health( array $connection ) {
		if ( ( $connection['status'] ?? '' ) !== 'connected' && ( $connection['status'] ?? '' ) !== 'error' ) {
			return new \WP_Error(
				'itsdesk_not_connected',
				__( 'Store is not connected.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$site_uuid = (string) ( $connection['site_uuid'] ?? '' );
		$path      = '/sites/' . rawurlencode( $site_uuid ) . '/health';
		$started   = microtime( true );

		$result = $this->http->post_signed(
			$connection,
			$path,
			array(),
			'health_' . wp_generate_uuid4()
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$latency = isset( $result['latency_ms'] )
			? (int) $result['latency_ms']
			: (int) round( ( microtime( true ) - $started ) * 1000 );

		return array(
			'ok'         => (bool) ( $result['ok'] ?? true ),
			'health'     => (string) ( $result['health'] ?? 'healthy' ),
			'checked_at' => (string) ( $result['checked_at'] ?? gmdate( 'c' ) ),
			'latency_ms' => $latency,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rotate( array $connection, string $public_key ) {
		if ( ( $connection['status'] ?? '' ) !== 'connected' && ( $connection['status'] ?? '' ) !== 'error' ) {
			return new \WP_Error(
				'itsdesk_not_connected',
				__( 'Store is not connected.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$site_uuid = (string) ( $connection['site_uuid'] ?? '' );
		$path      = '/sites/' . rawurlencode( $site_uuid ) . '/rotate';

		return $this->http->post_signed(
			$connection,
			$path,
			array( 'public_key' => $public_key ),
			'rotate_' . wp_generate_uuid4()
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function disconnect( array $connection ) {
		$site_uuid = (string) ( $connection['site_uuid'] ?? '' );
		if ( '' === $site_uuid ) {
			return true;
		}

		$path   = '/sites/' . rawurlencode( $site_uuid ) . '/disconnect';
		$result = $this->http->post_signed( $connection, $path, array(), 'disconnect_' . wp_generate_uuid4() );

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
			if ( in_array( $code, array( 401, 404 ), true ) ) {
				return true;
			}
			return $result;
		}

		return true;
	}
}
