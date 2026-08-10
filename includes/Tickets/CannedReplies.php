<?php
/**
 * Canned reply templates (macros) agents can insert into the reply composer.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD store for canned replies — a small, admin-managed list kept in a
 * single wp_option rather than a DB table.
 */
final class CannedReplies {

	public const OPTION_KEY = 'itsdesk_canned_replies';

	public const MAX_TITLE_LENGTH = 80;

	public const MAX_BODY_LENGTH = 2000;

	/**
	 * List all canned replies, sorted by title.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		usort(
			$stored,
			static function ( $a, $b ) {
				return strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
			}
		);

		return array_values( $stored );
	}

	/**
	 * Find a single canned reply by id.
	 *
	 * @param string $id Canned reply id.
	 * @return array<string, string>|null
	 */
	public function find( string $id ): ?array {
		foreach ( $this->all() as $reply ) {
			if ( $reply['id'] === $id ) {
				return $reply;
			}
		}

		return null;
	}

	/**
	 * Create a canned reply.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, string>|\WP_Error
	 */
	public function create( array $input ) {
		$title = $this->validate_title( $input['title'] ?? '' );
		if ( is_wp_error( $title ) ) {
			return $title;
		}

		$body = $this->validate_body( $input['body'] ?? '' );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$now    = gmdate( 'c' );
		$record = array(
			'id'         => 'macro_' . wp_generate_uuid4(),
			'title'      => $title,
			'body'       => $body,
			'created_at' => $now,
			'updated_at' => $now,
		);

		$all   = $this->raw();
		$all[] = $record;
		update_option( self::OPTION_KEY, $all, false );

		return $record;
	}

	/**
	 * Partially update a canned reply.
	 *
	 * @param string                $id    Canned reply id.
	 * @param array<string, mixed>  $input Raw input.
	 * @return array<string, string>|\WP_Error
	 */
	public function update( string $id, array $input ) {
		$all   = $this->raw();
		$index = null;
		foreach ( $all as $i => $reply ) {
			if ( $reply['id'] === $id ) {
				$index = $i;
				break;
			}
		}

		if ( null === $index ) {
			return new \WP_Error(
				'itsdesk_macro_not_found',
				__( 'Canned reply not found.', 'deskovi' ),
				array( 'status' => 404 )
			);
		}

		$record = $all[ $index ];

		if ( array_key_exists( 'title', $input ) ) {
			$title = $this->validate_title( $input['title'] );
			if ( is_wp_error( $title ) ) {
				return $title;
			}
			$record['title'] = $title;
		}

		if ( array_key_exists( 'body', $input ) ) {
			$body = $this->validate_body( $input['body'] );
			if ( is_wp_error( $body ) ) {
				return $body;
			}
			$record['body'] = $body;
		}

		$record['updated_at'] = gmdate( 'c' );

		$all[ $index ] = $record;
		update_option( self::OPTION_KEY, $all, false );

		return $record;
	}

	/**
	 * Delete a canned reply.
	 *
	 * @param string $id Canned reply id.
	 * @return bool Whether a matching record was found and removed.
	 */
	public function delete( string $id ): bool {
		$all   = $this->raw();
		$found = false;

		$remaining = array_values(
			array_filter(
				$all,
				static function ( $reply ) use ( $id, &$found ) {
					if ( $reply['id'] === $id ) {
						$found = true;
						return false;
					}
					return true;
				}
			)
		);

		if ( $found ) {
			update_option( self::OPTION_KEY, $remaining, false );
		}

		return $found;
	}

	/**
	 * Raw stored list, unsorted, guarded against a non-array option value.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function raw(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Validate and sanitize a title.
	 *
	 * @param mixed $value Raw title.
	 * @return string|\WP_Error
	 */
	private function validate_title( $value ) {
		$title = sanitize_text_field( (string) $value );
		if ( '' === $title || mb_strlen( $title ) > self::MAX_TITLE_LENGTH ) {
			return new \WP_Error(
				'itsdesk_invalid_macro_title',
				__( 'Title is required and must be 80 characters or fewer.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		return $title;
	}

	/**
	 * Validate and sanitize a body.
	 *
	 * @param mixed $value Raw body.
	 * @return string|\WP_Error
	 */
	private function validate_body( $value ) {
		$body = sanitize_textarea_field( (string) $value );
		if ( '' === $body || mb_strlen( $body ) > self::MAX_BODY_LENGTH ) {
			return new \WP_Error(
				'itsdesk_invalid_macro_body',
				__( 'Body is required and must be 2000 characters or fewer.', 'deskovi' ),
				array( 'status' => 400 )
			);
		}

		return $body;
	}
}
