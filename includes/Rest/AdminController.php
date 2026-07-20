<?php
/**
 * Admin REST API under itsdesk/v1.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Rest;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Connection\ConnectionManager;
use Itsdesk\Connection\ConnectionStatus;
use Itsdesk\Diagnostics\EnvironmentReport;
use Itsdesk\Privacy\Settings as PrivacySettings;
use Itsdesk\Queue\Status as QueueStatus;
use Itsdesk\Widget\Settings as WidgetSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers authenticated admin REST routes.
 */
final class AdminController {

	public const REST_NAMESPACE = 'itsdesk/v1';


	/**
	 * Hook rest_api_init.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Route map.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connection',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connection/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connection/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connection/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connection/rotate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rotate_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/connection/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect_connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/widget',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_widget' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_widget' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/privacy',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_privacy' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_privacy' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/diagnostics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_diagnostics' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/activity',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_activity' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Capability gate.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_itsdesk' );
	}

	/**
	 * GET /overview
	 */
	public function get_overview(): WP_REST_Response {
		$overview = ( new ConnectionStatus() )->overview();
		$widget   = ( new WidgetSettings() )->get();
		$privacy  = ( new PrivacySettings() )->get();
		$manager  = new ConnectionManager();

		return new WP_REST_Response(
			array(
				'connection'     => $manager->get_public_state(),
				'queue_failures' => $overview['queue_failures'],
				'queue_pending'  => $overview['queue_pending'],
				'hpos_enabled'   => $overview['hpos_enabled'],
				'widget'         => $widget,
				'privacy'        => $privacy,
			),
			200
		);
	}

	/**
	 * GET /connection
	 */
	public function get_connection(): WP_REST_Response {
		return new WP_REST_Response( ( new ConnectionManager() )->get_public_state(), 200 );
	}

	/**
	 * POST /connection/start
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_connection() {
		$result = ( new ConnectionManager() )->start();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /connection/complete
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function complete_connection( WP_REST_Request $request ) {
		$result = ( new ConnectionManager() )->complete( $request->get_json_params() ?: array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /connection/test
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_connection() {
		$result = ( new ConnectionManager() )->test();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /connection/rotate
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rotate_connection() {
		$result = ( new ConnectionManager() )->rotate();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /connection/disconnect
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function disconnect_connection() {
		$result = ( new ConnectionManager() )->disconnect();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /widget
	 */
	public function get_widget(): WP_REST_Response {
		return new WP_REST_Response( ( new WidgetSettings() )->get(), 200 );
	}

	/**
	 * PUT/POST /widget
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_widget( WP_REST_Request $request ) {
		$result = ( new WidgetSettings() )->update( $request->get_json_params() ?: array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /privacy
	 */
	public function get_privacy(): WP_REST_Response {
		return new WP_REST_Response( ( new PrivacySettings() )->get(), 200 );
	}

	/**
	 * PUT/POST /privacy
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_privacy( WP_REST_Request $request ) {
		$result = ( new PrivacySettings() )->update( $request->get_json_params() ?: array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /diagnostics
	 */
	public function get_diagnostics(): WP_REST_Response {
		return new WP_REST_Response( ( new EnvironmentReport() )->collect(), 200 );
	}

	/**
	 * GET /activity
	 */
	public function get_activity(): WP_REST_Response {
		$queue = new QueueStatus();

		return new WP_REST_Response(
			array(
				'summary'  => $queue->summary(),
				'activity' => $queue->activity(),
			),
			200
		);
	}
}
