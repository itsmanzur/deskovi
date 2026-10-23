<?php
/**
 * Admin REST API under itsdesk/v1.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Rest;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Admin\Capabilities;
use Itsdesk\Diagnostics\ActivityLogger;
use Itsdesk\Diagnostics\EnvironmentReport;
use Itsdesk\Privacy\Settings as PrivacySettings;
use Itsdesk\Tickets\CannedReplies;
use Itsdesk\Tickets\NotificationSettings;
use Itsdesk\Tickets\TicketService;
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
			'/macros',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_macros' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'create_macro' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/macros/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_macro' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_macro' ),
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
		return Capabilities::can( Capabilities::ABILITY_MANAGE );
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
				'desk'    => $this->desk_stats(),
			),
			200
		);
	}

	/**
	 * The numbers on the Overview: what is waiting now and how many tickets came in each of the last 7 days.
	 *
	 * @return array{open: int, pending: int, unassigned: int, oldest_waiting: int|null, new_7d: int, days: array<int, array{date: string, count: int}>}
	 */
	private function desk_stats(): array {
		$service = new TicketService();
		$now     = time();

		$open       = 0;
		$pending    = 0;
		$unassigned = 0;
		$oldest     = null;
		foreach ( $service->open_rows() as $row ) {
			if ( 'pending' === ( $row['status'] ?? '' ) ) {
				++$pending;
			} else {
				++$open;
			}
			if ( empty( $row['assigned_agent_id'] ) ) {
				++$unassigned;
			}
			$created = strtotime( (string) ( $row['created_at'] ?? '' ) );
			if ( false !== $created && ( null === $oldest || $created < $oldest ) ) {
				$oldest = $created;
			}
		}

		$days = array();
		for ( $i = 6; $i >= 0; --$i ) {
			$days[ gmdate( 'Y-m-d', $now - $i * DAY_IN_SECONDS ) ] = 0;
		}
		$from = (int) strtotime( (string) array_key_first( $days ) . ' 00:00:00 UTC' );
		foreach ( $service->report_rows( gmdate( 'c', $from ), gmdate( 'c', $now + 60 ) ) as $row ) {
			$created = strtotime( (string) ( $row['created_at'] ?? '' ) );
			$key     = false === $created ? '' : gmdate( 'Y-m-d', $created );
			if ( isset( $days[ $key ] ) ) {
				++$days[ $key ];
			}
		}

		$series = array();
		foreach ( $days as $date => $count ) {
			$series[] = array(
				'date'  => (string) $date,
				'count' => $count,
			);
		}

		return array(
			'open'           => $open,
			'pending'        => $pending,
			'unassigned'     => $unassigned,
			'oldest_waiting' => null === $oldest ? null : max( 0, $now - $oldest ),
			'new_7d'         => array_sum( $days ),
			'days'           => $series,
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
	 * GET /macros
	 */
	public function get_macros(): WP_REST_Response {
		return new WP_REST_Response( ( new CannedReplies() )->all(), 200 );
	}

	/**
	 * POST /macros
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_macro( WP_REST_Request $request ) {
		$result = ( new CannedReplies() )->create( $request->get_json_params() ?: array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * POST /macros/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_macro( WP_REST_Request $request ) {
		$result = ( new CannedReplies() )->update(
			(string) $request['id'],
			$request->get_json_params() ?: array()
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * DELETE /macros/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_macro( WP_REST_Request $request ) {
		$deleted = ( new CannedReplies() )->delete( (string) $request['id'] );
		if ( ! $deleted ) {
			return new \WP_Error(
				'itsdesk_macro_not_found',
				__( 'Canned reply not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
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
