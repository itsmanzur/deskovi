<?php
/**
 * The filters of the admin ticket list, in one validated shape.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

/**
 * Used by the list, the CSV export and saved views, so they can never disagree about what a
 * filter means. Anything unknown is dropped; nothing here reaches SQL except through the
 * repository's prepared statements.
 */
final class ListFilters {

	public const STATUSES = array( 'open', 'pending', 'resolved', 'closed' );

	public const PRIORITIES = array( 'low', 'normal', 'high', 'urgent' );

	public const MAX_EXTRA = 5;

	/**
	 * Make a clean filter set out of anything.
	 *
	 * @param array<string, mixed> $in Raw values (request parameters or a saved view).
	 * @return array{search: string, assignee: string, status: string, priority: string, category: string, extra: array<string, string>}
	 */
	public static function normalize( array $in ): array {
		$assignee = (string) ( $in['assignee'] ?? 'all' );
		if ( 'all' !== $assignee && 'unassigned' !== $assignee && ! ctype_digit( $assignee ) ) {
			$assignee = 'all';
		}

		$status = (string) ( $in['status'] ?? '' );
		if ( 'active' !== $status && ! in_array( $status, self::STATUSES, true ) ) {
			$status = '';
		}

		$priority = (string) ( $in['priority'] ?? '' );
		if ( ! in_array( $priority, self::PRIORITIES, true ) ) {
			$priority = '';
		}

		$category = sanitize_key( (string) ( $in['category'] ?? '' ) );
		if ( '' !== $category && ! Categories::is_valid( $category ) ) {
			$category = '';
		}

		$extra = array();
		// `x` is what a request carries, `extra` what a normalised set (and so a saved view) holds.
		foreach ( (array) ( $in['x'] ?? $in['extra'] ?? array() ) as $key => $value ) {
			$key = (string) $key;
			if ( count( $extra ) >= self::MAX_EXTRA || 1 !== preg_match( '/^[a-z0-9_]{1,24}$/', $key ) || ! is_scalar( $value ) ) {
				continue;
			}
			$value = trim( sanitize_text_field( (string) $value ) );
			if ( '' !== $value ) {
				$extra[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 100 ) : substr( $value, 0, 100 );
			}
		}

		return array(
			'search'   => trim( sanitize_text_field( (string) ( $in['search'] ?? '' ) ) ),
			'assignee' => $assignee,
			'status'   => $status,
			'priority' => $priority,
			'category' => $category,
			'extra'    => $extra,
		);
	}

	/**
	 * Is anything filtered?
	 *
	 * @param array<string, mixed> $filters A normalised set.
	 */
	public static function is_empty( array $filters ): bool {
		return '' === ( $filters['search'] ?? '' ) && 'all' === ( $filters['assignee'] ?? 'all' ) && '' === ( $filters['status'] ?? '' ) && '' === ( $filters['priority'] ?? '' ) && '' === ( $filters['category'] ?? '' ) && array() === ( $filters['extra'] ?? array() );
	}
}
