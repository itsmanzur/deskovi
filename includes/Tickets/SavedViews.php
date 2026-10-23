<?php
/**
 * Saved ticket-list views, one list per agent.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * A view is a name and a filter set. Stored in the agent's own user meta, so one agent's views
 * are never seen or changed by another.
 */
final class SavedViews {

	public const META = 'itsdesk_saved_views';

	public const MAX = 20;

	/**
	 * An agent's views.
	 *
	 * @param int $user_id User.
	 * @return array<int, array{id: string, name: string, filters: array<string, mixed>}>
	 */
	public static function all( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::META, true );
		$out    = array();
		foreach ( is_array( $stored ) ? $stored : array() as $view ) {
			if ( is_array( $view ) && isset( $view['id'], $view['name'] ) ) {
				$out[] = array(
					'id'      => (string) $view['id'],
					'name'    => (string) $view['name'],
					'filters' => ListFilters::normalize( (array) ( $view['filters'] ?? array() ) ),
				);
			}
		}

		return $out;
	}

	/**
	 * Add a view.
	 *
	 * @param int                  $user_id User.
	 * @param string               $name    Name.
	 * @param array<string, mixed> $filters Raw filters.
	 * @return array{id: string, name: string, filters: array<string, mixed>}|WP_Error
	 */
	public static function add( int $user_id, string $name, array $filters ) {
		$name = trim( sanitize_text_field( $name ) );
		$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 40 ) : substr( $name, 0, 40 );
		if ( '' === $name ) {
			return new WP_Error( 'itsdesk_view_name', __( 'Give the view a name.', 'deskovi' ), array( 'status' => 400 ) );
		}

		$clean = ListFilters::normalize( $filters );
		if ( ListFilters::is_empty( $clean ) ) {
			return new WP_Error( 'itsdesk_view_empty', __( 'Choose at least one filter before saving a view.', 'deskovi' ), array( 'status' => 400 ) );
		}

		$views = self::all( $user_id );
		if ( count( $views ) >= self::MAX ) {
			return new WP_Error( 'itsdesk_view_limit', __( 'You have reached the maximum number of saved views. Delete one first.', 'deskovi' ), array( 'status' => 400 ) );
		}
		foreach ( $views as $view ) {
			if ( 0 === strcasecmp( $view['name'], $name ) ) {
				return new WP_Error( 'itsdesk_view_duplicate', __( 'You already have a view with this name.', 'deskovi' ), array( 'status' => 400 ) );
			}
		}

		$new     = array(
			'id'      => substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 ),
			'name'    => $name,
			'filters' => $clean,
		);
		$views[] = $new;
		update_user_meta( $user_id, self::META, $views );

		return $new;
	}

	/**
	 * Delete a view of this agent.
	 *
	 * @param int    $user_id User.
	 * @param string $id      View id.
	 */
	public static function delete( int $user_id, string $id ): bool {
		$views = self::all( $user_id );
		$kept  = array_values( array_filter( $views, static fn( $v ) => $v['id'] !== $id ) );
		if ( count( $kept ) === count( $views ) ) {
			return false;
		}
		update_user_meta( $user_id, self::META, $kept );

		return true;
	}
}
