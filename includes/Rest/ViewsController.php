<?php
/**
 * REST routes for the agent's saved ticket views.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Rest;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Admin\Capabilities;
use Itsdesk\Tickets\SavedViews;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Always the current user's own views.
 */
final class ViewsController {

	private const NS = 'itsdesk/v1';

	/**
	 * Register routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/views',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_views' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_view' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/views/(?P<id>[a-f0-9]{12})',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_view' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Agents only.
	 */
	public function can_manage(): bool {
		return Capabilities::can( Capabilities::ABILITY_MANAGE );
	}

	/**
	 * GET /views
	 */
	public function list_views(): WP_REST_Response {
		return new WP_REST_Response( array( 'views' => SavedViews::all( get_current_user_id() ) ) );
	}

	/**
	 * POST /views  { name, filters }
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_view( WP_REST_Request $request ) {
		$params = (array) $request->get_json_params();
		$view   = SavedViews::add( get_current_user_id(), (string) ( $params['name'] ?? '' ), (array) ( $params['filters'] ?? array() ) );
		if ( is_wp_error( $view ) ) {
			return $view;
		}

		return new WP_REST_Response( array( 'view' => $view, 'views' => SavedViews::all( get_current_user_id() ) ), 201 );
	}

	/**
	 * DELETE /views/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_view( WP_REST_Request $request ) {
		if ( ! SavedViews::delete( get_current_user_id(), (string) $request['id'] ) ) {
			return new WP_Error( 'itsdesk_view_missing', __( 'That view does not exist.', 'deskovi' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'views' => SavedViews::all( get_current_user_id() ) ) );
	}
}
