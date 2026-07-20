<?php
/**
 * Local mock SaaS transport for M2 UI development.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection\Transport;

/**
 * Deterministic mock responses — no network.
 */
final class MockTransport implements TransportInterface {

	/**
	 * Available mock workspaces.
	 *
	 * @return array<int, array{id: string, name: string}>
	 */
	public static function workspaces(): array {
		return array(
			array(
				'id'   => 'ws_acme',
				'name' => 'Acme Support',
			),
			array(
				'id'   => 'ws_agency',
				'name' => 'Demo Agency Desk',
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function mode(): string {
		return 'mock';
	}

	/**
	 * {@inheritdoc}
	 */
	public function start( string $site_url, string $state ) {
		return array(
			'mode'            => 'mock',
			'state'           => $state,
			'authorize_url'   => '',
			'mock_workspaces' => self::workspaces(),
			'expires_in'      => 600,
			'site_url'        => $site_url,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function exchange( array $payload ) {
		$code = isset( $payload['code'] ) ? strtolower( trim( (string) $payload['code'] ) ) : '';
		if ( 'fail' === $code ) {
			return new \WP_Error(
				'itsdesk_exchange_rejected',
				__( 'Mock rejection: uncheck “Simulate failed authorization” and try Complete connection again.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$workspace_id = isset( $payload['workspace_id'] ) ? (string) $payload['workspace_id'] : '';
		$name         = '';
		foreach ( self::workspaces() as $workspace ) {
			if ( $workspace['id'] === $workspace_id ) {
				$name = $workspace['name'];
				break;
			}
		}

		if ( '' === $name ) {
			return new \WP_Error(
				'itsdesk_unknown_workspace',
				__( 'Unknown mock workspace.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'site_uuid'      => wp_generate_uuid4(),
			'workspace_id'   => $workspace_id,
			'workspace_name' => $name,
			'saas_url'       => 'https://app.deskovi.com',
			'scopes'         => array( 'orders.read', 'tickets.write', 'diagnostics.limited' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function health( array $connection ) {
		if ( ( $connection['status'] ?? '' ) !== 'connected' ) {
			return new \WP_Error(
				'itsdesk_not_connected',
				__( 'Store is not connected.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'ok'         => true,
			'health'     => 'healthy',
			'checked_at' => gmdate( 'c' ),
			'latency_ms' => 12,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rotate( array $connection, string $public_key ) {
		if ( ( $connection['status'] ?? '' ) !== 'connected' ) {
			return new \WP_Error(
				'itsdesk_not_connected',
				__( 'Store is not connected.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'ok'         => true,
			'rotated_at' => gmdate( 'c' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function disconnect( array $connection ) {
		return true;
	}
}
