<?php
/**
 * Admin REST API under itsdesk/v1.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Rest;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Diagnostics\ActivityLogger;
use Itsdesk\Diagnostics\EnvironmentReport;
use Itsdesk\Privacy\Settings as PrivacySettings;
use Itsdesk\Tickets\NotificationSettings;
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
			'/notifications',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notifications' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_notifications' ),
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
		$widget  = ( new WidgetSettings() )->get();
		$privacy = ( new PrivacySettings() )->get();

		return new WP_REST_Response(
			array(
				'widget'  => $widget,
				'privacy' => $privacy,
			),
			200
		);
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
	 * GET /notifications
	 */
	public function get_notifications(): WP_REST_Response {
		return new WP_REST_Response( ( new NotificationSettings() )->get(), 200 );
	}

	/**
	 * PUT/POST /notifications
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_notifications( WP_REST_Request $request ) {
		$result = ( new NotificationSettings() )->update( $request->get_json_params() ?: array() );
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
		return new WP_REST_Response(
			array(
				'activity' => ( new ActivityLogger() )->recent(),
			),
			200
		);
	}
}
