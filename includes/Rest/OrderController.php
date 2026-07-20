<?php
/**
 * Order context REST routes.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Rest;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Auth\GuestSession;
use Itsdesk\Orders\OrderContext;
use Itsdesk\Tickets\TicketService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Admin + customer order endpoints.
 */
final class OrderController {

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
			'/customer/orders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'customer_list' ),
				'permission_callback' => array( $this, 'can_customer' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customer/orders/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'customer_get' ),
				'permission_callback' => array( $this, 'can_customer' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'admin_get' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/tickets/(?P<id>[a-zA-Z0-9_-]+)/order',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'ticket_order_get' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ticket_order_link' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Permission: manage Deskovi.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_itsdesk' );
	}

	/**
	 * Permission: logged-in customer or verified guest.
	 */
	public function can_customer(): bool {
		return is_user_logged_in() || ( new GuestSession() )->is_authenticated();
	}

	/**
	 * GET /customer/orders
	 */
	public function customer_list(): WP_REST_Response {
		if ( ! is_user_logged_in() ) {
			$guest = ( new GuestSession() )->current();
			if ( null === $guest ) {
				return new WP_REST_Response( array( 'orders' => array(), 'window_days' => 0 ), 200 );
			}
			// Guests only see the verified order as a list of one.
			$snap = ( new OrderContext() )->get_snapshot( (int) $guest['order_id'], null, true );
			if ( is_wp_error( $snap ) ) {
				return new WP_REST_Response( array( 'orders' => array(), 'window_days' => 0 ), 200 );
			}
			return new WP_REST_Response(
				array(
					'orders' => array(
						array(
							'id'           => $snap['id'],
							'number'       => $snap['number'],
							'status'       => $snap['status'],
							'date_created' => $snap['date_created'],
							'currency'     => $snap['currency'],
							'total'        => $snap['total'],
						),
					),
					'window_days' => 0,
				),
				200
			);
		}

		$ctx    = new OrderContext();
		$result = $ctx->list_for_customer( get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return new WP_REST_Response(
			array(
				'orders'      => $result,
				'window_days' => $ctx->list_window_days(),
			),
			200
		);
	}

	/**
	 * GET /customer/orders/{id}
	 */
	public function customer_get( WP_REST_Request $request ): WP_REST_Response {
		$order_id = absint( $request['id'] );
		if ( is_user_logged_in() ) {
			$result = ( new OrderContext() )->get_snapshot( $order_id, get_current_user_id(), false );
		} else {
			$guest = ( new GuestSession() )->current();
			if ( null === $guest || (int) $guest['order_id'] !== $order_id ) {
				return new WP_REST_Response(
					array(
						'code'    => 'itsdesk_order_forbidden',
						'message' => __( 'You can only view your verified order.', 'deskovi' ),
					),
					403
				);
			}
			$result = ( new OrderContext() )->get_snapshot( $order_id, null, true );
		}
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /orders/{id}
	 */
	public function admin_get( WP_REST_Request $request ): WP_REST_Response {
		$order_id = absint( $request['id'] );
		$result   = ( new OrderContext() )->get_snapshot( $order_id, null, true );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /tickets/{id}/order
	 */
	public function ticket_order_get( WP_REST_Request $request ): WP_REST_Response {
		$ticket_id = sanitize_text_field( (string) $request['id'] );
		$ticket    = ( new TicketService() )->get( $ticket_id, null, true );
		if ( is_wp_error( $ticket ) ) {
			return $this->error_response( $ticket );
		}

		$order_id = isset( $ticket['order_id'] ) ? absint( $ticket['order_id'] ) : 0;
		if ( $order_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'linked'  => false,
					'order'   => null,
				),
				200
			);
		}

		$snapshot = ( new OrderContext() )->get_snapshot( $order_id, null, true );
		if ( is_wp_error( $snapshot ) ) {
			return new WP_REST_Response(
				array(
					'linked'  => true,
					'order_id'=> $order_id,
					'order'   => null,
					'error'   => $snapshot->get_error_message(),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'linked'   => true,
				'order_id' => $order_id,
				'order'    => $snapshot,
			),
			200
		);
	}

	/**
	 * POST /tickets/{id}/order
	 */
	public function ticket_order_link( WP_REST_Request $request ): WP_REST_Response {
		$ticket_id = sanitize_text_field( (string) $request['id'] );
		$params    = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$order_id = null;
		if ( array_key_exists( 'order_id', $params ) ) {
			$raw = $params['order_id'];
			if ( null === $raw || '' === $raw || false === $raw ) {
				$order_id = null;
			} else {
				$order_id = absint( $raw );
				if ( $order_id <= 0 ) {
					$order_id = null;
				}
			}
		}

		$result = ( new TicketService() )->link_order( $ticket_id, $order_id, true );
		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result );
		}

		$linked_id = isset( $result['order_id'] ) ? absint( $result['order_id'] ) : 0;
		$order     = null;
		if ( $linked_id > 0 ) {
			$snap = ( new OrderContext() )->get_snapshot( $linked_id, null, true );
			if ( ! is_wp_error( $snap ) ) {
				$order = $snap;
			}
		}

		return new WP_REST_Response(
			array(
				'ticket'   => $result,
				'linked'   => $linked_id > 0,
				'order_id' => $linked_id > 0 ? $linked_id : null,
				'order'    => $order,
			),
			200
		);
	}

	/**
	 * @param \WP_Error $error Error.
	 */
	private function error_response( \WP_Error $error ): WP_REST_Response {
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
