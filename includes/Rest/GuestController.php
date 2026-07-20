<?php
/**
 * Guest verification REST.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Rest;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Auth\GuestSession;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public guest OTP endpoints.
 */
final class GuestController {

	public const REST_NAMESPACE = 'itsdesk/v1';


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
			self::REST_NAMESPACE,
			'/guest/verify/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/guest/verify/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirm' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/guest/session',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'session' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'logout' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * POST start.
	 */
	public function start( WP_REST_Request $request ): WP_REST_Response {
		$body     = $request->get_json_params() ?: array();
		$order_id = isset( $body['order_id'] ) ? absint( $body['order_id'] ) : 0;
		$email    = isset( $body['email'] ) ? (string) $body['email'] : '';
		$result   = ( new GuestSession() )->start( $order_id, $email );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST confirm.
	 */
	public function confirm( WP_REST_Request $request ): WP_REST_Response {
		$body     = $request->get_json_params() ?: array();
		$order_id = isset( $body['order_id'] ) ? absint( $body['order_id'] ) : 0;
		$email    = isset( $body['email'] ) ? (string) $body['email'] : '';
		$code     = isset( $body['code'] ) ? (string) $body['code'] : '';
		$result   = ( new GuestSession() )->confirm( $order_id, $email, $code );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET session.
	 */
	public function session(): WP_REST_Response {
		$guest = ( new GuestSession() )->current();
		return new WP_REST_Response(
			array(
				'authenticated' => null !== $guest,
				'session'       => $guest,
			),
			200
		);
	}

	/**
	 * DELETE session.
	 */
	public function logout(): WP_REST_Response {
		( new GuestSession() )->clear();
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * @param \WP_Error $error Error.
	 */
	private function error( \WP_Error $error ): WP_REST_Response {
		$status = (int) ( $error->get_error_data()['status'] ?? 400 );
		return new WP_REST_Response(
			array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			$status
		);
	}
}
