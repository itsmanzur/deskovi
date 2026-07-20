<?php
/**
 * Ticket bridge REST routes.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Rest;

use Itsdesk\Auth\GuestSession;
use Itsdesk\Tickets\Categories;
use Itsdesk\Tickets\TicketService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Admin + customer ticket endpoints.
 */
final class TicketController {

	public const REST_NAMESPACE = 'itsdesk/v1';

	/**
	 * Register routes.
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
			'/tickets/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_categories' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tickets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'admin_list' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'admin_create' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tickets/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'admin_get' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tickets/(?P<id>[a-zA-Z0-9_-]+)/replies',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_reply' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tickets/(?P<id>[a-zA-Z0-9_-]+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_status' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customer/tickets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'customer_list' ),
					'permission_callback' => array( $this, 'can_customer' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'customer_create' ),
					'permission_callback' => array( $this, 'can_customer' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customer/tickets/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'customer_get' ),
				'permission_callback' => array( $this, 'can_customer' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customer/tickets/(?P<id>[a-zA-Z0-9_-]+)/replies',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'customer_reply' ),
				'permission_callback' => array( $this, 'can_customer' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customer/unread',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'customer_unread' ),
				'permission_callback' => array( $this, 'can_customer' ),
			)
		);
	}

	/**
	 * Admin capability.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_itsdesk' );
	}

	/**
	 * Logged-in customer or verified guest.
	 */
	public function can_customer(): bool {
		return is_user_logged_in() || ( new GuestSession() )->is_authenticated();
	}

	/**
	 * @return array{email: string, order_id: int}|null
	 */
	private function guest_context(): ?array {
		return ( new GuestSession() )->current();
	}

	/**
	 * GET categories.
	 */
	public function get_categories(): WP_REST_Response {
		$items = array();
		foreach ( Categories::all() as $id => $label ) {
			$items[] = array(
				'id'    => $id,
				'label' => $label,
			);
		}
		return new WP_REST_Response( array( 'categories' => $items ), 200 );
	}

	/**
	 * Admin list.
	 */
	public function admin_list(): WP_REST_Response {
		return new WP_REST_Response(
			array( 'tickets' => ( new TicketService() )->list_all() ),
			200
		);
	}

	/**
	 * Admin get.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_get( WP_REST_Request $request ) {
		$result = ( new TicketService() )->get( (string) $request['id'], null, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Admin create.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_create( WP_REST_Request $request ) {
		$input = $request->get_json_params() ?: array();
		if ( empty( $input['customer_user_id'] ) ) {
			$input['customer_user_id'] = get_current_user_id();
		}
		$header_key = $request->get_header( 'X-Idempotency-Key' );
		if ( $header_key && empty( $input['idempotency_key'] ) ) {
			$input['idempotency_key'] = $header_key;
		}

		$result = ( new TicketService() )->create( $input, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Admin reply.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_reply( WP_REST_Request $request ) {
		$result = ( new TicketService() )->reply(
			(string) $request['id'],
			$request->get_json_params() ?: array(),
			null,
			true,
			null
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Admin status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_status( WP_REST_Request $request ) {
		$body   = $request->get_json_params() ?: array();
		$status = isset( $body['status'] ) ? (string) $body['status'] : '';
		$result = ( new TicketService() )->update_status( (string) $request['id'], $status );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Customer list.
	 */
	public function customer_list(): WP_REST_Response {
		$service = new TicketService();
		if ( is_user_logged_in() ) {
			$tickets = $service->list_for_user( get_current_user_id() );
		} else {
			$guest = $this->guest_context();
			$tickets = $guest ? $service->list_for_guest( $guest ) : array();
		}
		return new WP_REST_Response( array( 'tickets' => $tickets ), 200 );
	}

	/**
	 * Unread count.
	 */
	public function customer_unread(): WP_REST_Response {
		$service = new TicketService();
		if ( is_user_logged_in() ) {
			$count = $service->unread_count_for_user( get_current_user_id() );
		} else {
			$guest = $this->guest_context();
			$count = $guest ? $service->unread_count_for_guest( $guest ) : 0;
		}
		return new WP_REST_Response( array( 'count' => $count ), 200 );
	}

	/**
	 * Customer create.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function customer_create( WP_REST_Request $request ) {
		$input = $request->get_json_params() ?: array();
		if ( is_user_logged_in() ) {
			$input['customer_user_id'] = get_current_user_id();
		} else {
			$input['customer_user_id'] = 0;
		}
		$header_key = $request->get_header( 'X-Idempotency-Key' );
		if ( $header_key && empty( $input['idempotency_key'] ) ) {
			$input['idempotency_key'] = $header_key;
		}

		$result = ( new TicketService() )->create( $input, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Customer get.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function customer_get( WP_REST_Request $request ) {
		$user_id = is_user_logged_in() ? get_current_user_id() : null;
		$guest   = is_user_logged_in() ? null : $this->guest_context();
		$service = new TicketService();
		$result  = $service->get( (string) $request['id'], $user_id, false, $guest );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$marked = $service->mark_read( (string) $request['id'], $user_id, $guest );
		if ( ! is_wp_error( $marked ) ) {
			$result = $marked;
		}
		$messages = array();
		foreach ( $result['messages'] ?? array() as $msg ) {
			if ( is_array( $msg ) && empty( $msg['internal'] ) ) {
				$messages[] = $msg;
			}
		}
		$result['messages'] = $messages;
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Customer reply.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function customer_reply( WP_REST_Request $request ) {
		$user_id = is_user_logged_in() ? get_current_user_id() : null;
		$guest   = is_user_logged_in() ? null : $this->guest_context();
		$result  = ( new TicketService() )->reply(
			(string) $request['id'],
			$request->get_json_params() ?: array(),
			$user_id,
			false,
			$guest
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}
}
