<?php
/**
 * SaaS → WP inbound event REST.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Rest;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Connection\ActivityLogger;
use Itsdesk\Connection\InboundVerifier;
use Itsdesk\Orders\OrderActionHandler;
use Itsdesk\Tickets\TicketService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public (signed) endpoint — no WP cookie auth.
 */
final class SaasInboundController {

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
			'itsdesk/v1',
			'/saas/events',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle signed SaaS event.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$raw     = $request->get_body();
		$headers = array(
			'timestamp'       => (string) $request->get_header( 'x-deskovi-timestamp' ),
			'nonce'           => (string) $request->get_header( 'x-deskovi-nonce' ),
			'body_hash'       => (string) $request->get_header( 'x-deskovi-body-hash' ),
			'site_id'         => (string) $request->get_header( 'x-deskovi-site-id' ),
			'signature'       => (string) $request->get_header( 'x-deskovi-signature' ),
			'idempotency_key' => (string) $request->get_header( 'x-deskovi-idempotency-key' ),
		);

		$verifier = new InboundVerifier();
		$check    = $verifier->verify( $raw, $headers, '/wp-json/itsdesk/v1/saas/events' );
		if ( is_wp_error( $check ) ) {
			$data = $check->get_error_data();
			if ( is_array( $data ) && ! empty( $data['idempotent'] ) ) {
				return new WP_REST_Response( array( 'ok' => true, 'idempotent' => true ), 200 );
			}
			return $check;
		}

		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'itsdesk_inbound_json', __( 'Invalid JSON body.', 'deskovi' ), array( 'status' => 400 ) );
		}

		$type = (string) ( $payload['type'] ?? '' );
		$svc  = new TicketService();

		if ( 'ticket.message.created' === $type ) {
			$result = $svc->apply_saas_message( $payload );
		} elseif ( 'ticket.status.updated' === $type ) {
			$result = $svc->apply_saas_status( $payload );
		} elseif ( in_array( $type, array( 'order.note.add', 'order.invoice.resend' ), true ) ) {
			$result = ( new OrderActionHandler() )->handle( $payload );
		} else {
			return new \WP_Error( 'itsdesk_inbound_type', __( 'Unknown event type.', 'deskovi' ), array( 'status' => 400 ) );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$verifier->mark_idempotent( $headers['idempotency_key'] );
		( new ActivityLogger() )->log( 'Inbound', 'SaaS event: ' . $type, 'OK' );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
