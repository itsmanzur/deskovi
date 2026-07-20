<?php
/**
 * Orchestrates secure connection lifecycle.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Connection;

use Itsdesk\Connection\Transport\TransportFactory;
use Itsdesk\Connection\Transport\TransportInterface;

/**
 * Connect / test / rotate / disconnect for admin REST.
 */
final class ConnectionManager {

	public const STATE_TRANSIENT = 'itsdesk_connect_state';

	private TransportInterface $transport;
	private ConnectionStatus $status;
	private SiteIdentity $identity;
	private ActivityLogger $logger;

	/**
	 * Constructor.
	 */
	public function __construct( ?TransportInterface $transport = null ) {
		$this->transport = $transport ?? TransportFactory::make();
		$this->status    = new ConnectionStatus();
		$this->identity  = new SiteIdentity();
		$this->logger    = new ActivityLogger();
	}

	/**
	 * Public connection payload for REST (no secrets).
	 *
	 * @return array<string, mixed>
	 */
	public function get_public_state(): array {
		$state         = $this->status->get();
		$state['mode'] = TransportFactory::mode();
		if ( empty( $state['public_key_fingerprint'] ) ) {
			$state['public_key_fingerprint'] = $this->identity->fingerprint();
		}
		return $state;
	}

	/**
	 * Start authorize handshake.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function start() {
		$state = wp_generate_password( 32, false, false );
		set_transient(
			self::STATE_TRANSIENT,
			array(
				'state'      => $state,
				'created_at' => time(),
			),
			10 * MINUTE_IN_SECONDS
		);

		$result = $this->transport->start( home_url( '/' ), $state );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->logger->log( 'Connect', 'Connection handshake started (' . $this->transport->mode() . ')', 'OK' );

		return $result;
	}

	/**
	 * Complete code exchange.
	 *
	 * @param array<string, mixed> $input Request body.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function complete( array $input ) {
		$pending = get_transient( self::STATE_TRANSIENT );
		if ( ! is_array( $pending ) || empty( $pending['state'] ) ) {
			return new \WP_Error(
				'itsdesk_state_expired',
				__( 'Connection session expired. Start again.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$state = isset( $input['state'] ) ? (string) $input['state'] : '';
		if ( ! hash_equals( (string) $pending['state'], $state ) ) {
			return new \WP_Error(
				'itsdesk_state_mismatch',
				__( 'Invalid connection state.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$keys = $this->identity->generate();
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		$payload = array(
			'code'         => isset( $input['code'] ) ? sanitize_text_field( (string) $input['code'] ) : 'mock-ok',
			'workspace_id' => isset( $input['workspace_id'] ) ? sanitize_text_field( (string) $input['workspace_id'] ) : '',
			'site_url'     => home_url( '/' ),
			'public_key'   => $keys['public_key'],
			'plugin_version' => ITSDESK_VERSION,
			'state'        => $state,
		);

		$remote = $this->transport->exchange( $payload );
		if ( is_wp_error( $remote ) ) {
			$this->identity->clear();
			$this->logger->log( 'Connect', $remote->get_error_message(), 'Failed' );
			return $remote;
		}

		$connection = array(
			'status'                 => 'connected',
			'mode'                   => $this->transport->mode(),
			'workspace_id'           => (string) ( $remote['workspace_id'] ?? '' ),
			'workspace_name'         => (string) ( $remote['workspace_name'] ?? '' ),
			'site_uuid'              => (string) ( $remote['site_uuid'] ?? '' ),
			'saas_url'               => (string) ( $remote['saas_url'] ?? 'https://app.deskovi.com' ),
			'scopes'                 => isset( $remote['scopes'] ) && is_array( $remote['scopes'] ) ? array_values( $remote['scopes'] ) : array(),
			'public_key_fingerprint' => (string) $keys['public_key_fingerprint'],
			'connected_at'           => gmdate( 'c' ),
			'last_sync_at'           => null,
			'last_health_at'         => gmdate( 'c' ),
			'health'                 => 'healthy',
		);

		$this->status->save( $connection );

		if ( ! empty( $remote['delivery_secret'] ) && is_string( $remote['delivery_secret'] ) ) {
			( new DeliverySecret() )->set( (string) $remote['delivery_secret'] );
		}

		delete_transient( self::STATE_TRANSIENT );

		$this->logger->log(
			'Connect',
			sprintf(
				/* translators: %s: workspace name */
				__( 'Site linked to %s', 'deskovi' ),
				$connection['workspace_name']
			),
			'OK'
		);

		return $this->get_public_state();
	}

	/**
	 * Run health check.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function test() {
		$connection = $this->status->get();
		$result     = $this->transport->health( $connection );
		if ( is_wp_error( $result ) ) {
			$this->status->patch(
				array(
					'health' => 'error',
					'status' => 'connected' === ( $connection['status'] ?? '' ) ? 'error' : $connection['status'],
				)
			);
			$this->logger->log( 'Health', $result->get_error_message(), 'Failed' );
			return $result;
		}

		$this->status->patch(
			array(
				'health'         => (string) ( $result['health'] ?? 'healthy' ),
				'last_health_at' => (string) ( $result['checked_at'] ?? gmdate( 'c' ) ),
				'status'         => 'connected',
			)
		);

		$this->logger->log( 'Health', __( 'Test connection succeeded', 'deskovi' ), 'OK' );

		return array(
			'result'     => $result,
			'connection' => $this->get_public_state(),
		);
	}

	/**
	 * Rotate site keys.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function rotate() {
		$connection = $this->status->get();
		if ( ( $connection['status'] ?? '' ) !== 'connected' && ( $connection['status'] ?? '' ) !== 'error' ) {
			return new \WP_Error(
				'itsdesk_not_connected',
				__( 'Connect the store before rotating keys.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		$keys = $this->identity->generate();
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		$remote = $this->transport->rotate( $connection, (string) $keys['public_key'] );
		if ( is_wp_error( $remote ) ) {
			$this->logger->log( 'Auth', $remote->get_error_message(), 'Failed' );
			return $remote;
		}

		if ( ! empty( $remote['delivery_secret'] ) && is_string( $remote['delivery_secret'] ) ) {
			( new DeliverySecret() )->set( (string) $remote['delivery_secret'] );
		}

		$this->status->patch(
			array(
				'public_key_fingerprint' => (string) $keys['public_key_fingerprint'],
				'last_health_at'         => gmdate( 'c' ),
				'health'                 => 'healthy',
				'status'                 => 'connected',
			)
		);

		$this->logger->log( 'Auth', __( 'Site keys rotated', 'deskovi' ), 'OK' );

		return $this->get_public_state();
	}

	/**
	 * Disconnect and wipe local identity.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function disconnect() {
		$connection = $this->status->get();
		if ( ( $connection['status'] ?? '' ) === 'disconnected' ) {
			return $this->get_public_state();
		}

		$remote = $this->transport->disconnect( $connection );
		if ( is_wp_error( $remote ) ) {
			$this->logger->log( 'Connect', $remote->get_error_message(), 'Failed' );
			return $remote;
		}

		$this->identity->clear();
		( new DeliverySecret() )->clear();
		$this->status->save( ConnectionStatus::defaults() );
		delete_transient( self::STATE_TRANSIENT );

		$this->logger->log( 'Connect', __( 'Store disconnected from Deskovi', 'deskovi' ), 'OK' );

		return $this->get_public_state();
	}
}
