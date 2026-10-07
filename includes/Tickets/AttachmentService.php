<?php
/**
 * Ticket attachment storage: validated upload, DB record, authenticated download.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

/**
 * Files live in a locked-down uploads subfolder with random, unguessable
 * names. They are never linked to directly — every read goes through
 * get_for_download(), which re-checks ticket ownership before streaming.
 */
final class AttachmentService {

	private const SUBDIR = 'itsdesk-attachments';

	/**
	 * Extension => mime allowlist. Anything else is rejected outright.
	 *
	 * @return array<string, string>
	 */
	public static function allowed_types(): array {
		$defaults = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'pdf'  => 'application/pdf',
			'txt'  => 'text/plain',
			'log'  => 'text/plain',
		);

		/**
		 * Filter the allowed attachment file types.
		 *
		 * @param array<string, string> $defaults Extension => mime map.
		 */
		return (array) apply_filters( 'itsdesk_attachment_allowed_types', $defaults );
	}

	/**
	 * Max upload size in bytes (default 5MB).
	 */
	public static function max_size(): int {
		/**
		 * Filter the max attachment size in bytes.
		 *
		 * @param int $bytes Max size.
		 */
		return (int) apply_filters( 'itsdesk_attachment_max_size', 5 * MB_IN_BYTES );
	}

	/**
	 * Max attachments per ticket (basic abuse guard).
	 */
	public static function max_per_ticket(): int {
		return (int) apply_filters( 'itsdesk_attachment_max_per_ticket', 20 );
	}

	/**
	 * Validate + store one uploaded file (from $request->get_file_params()).
	 *
	 * @param array<string, mixed> $file        A single entry from get_file_params(), e.g. $files['file'].
	 * @param string                $ticket_id   Ticket this belongs to.
	 * @param string|null           $message_id  Message this belongs to, if any.
	 * @param string                $uploaded_by 'customer' or 'agent'.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function store( array $file, string $ticket_id, ?string $message_id, string $uploaded_by ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return new \WP_Error( 'itsdesk_attachment_invalid', __( 'No file was uploaded.', 'deskovi' ), array( 'status' => 400 ) );
		}

		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'itsdesk_attachment_upload_error', __( 'The file failed to upload.', 'deskovi' ), array( 'status' => 400 ) );
		}

		return $this->ingest(
			(string) $file['tmp_name'],
			isset( $file['name'] ) ? (string) $file['name'] : '',
			$ticket_id,
			$message_id,
			$uploaded_by
		);
	}

	/**
	 * Store a file that already exists on disk (not an HTTP upload), for example
	 * an attachment an add-on extracted from an email. The source file is copied,
	 * never moved or deleted. Same validation as store().
	 *
	 * @param string      $path        Readable file path.
	 * @param string      $name        Original file name (extension decides the type).
	 * @param string      $ticket_id   Ticket this belongs to.
	 * @param string|null $message_id  Message this belongs to, if any.
	 * @param string      $uploaded_by 'customer' or 'agent'.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function store_from_path( string $path, string $name, string $ticket_id, ?string $message_id, string $uploaded_by ) {
		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new \WP_Error( 'itsdesk_attachment_invalid', __( 'No file was provided.', 'deskovi' ), array( 'status' => 400 ) );
		}

		$tmp = $this->temp_file( $name );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		if ( ! copy( $path, $tmp ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'itsdesk_attachment_write_failed', __( 'Could not save the file.', 'deskovi' ), array( 'status' => 500 ) );
		}

		return $this->ingest_temp( $tmp, $name, $ticket_id, $message_id, $uploaded_by );
	}

	/**
	 * Store file contents held in memory (for example a decoded email part).
	 * Same validation as store().
	 *
	 * @param string      $bytes       File contents.
	 * @param string      $name        Original file name (extension decides the type).
	 * @param string      $ticket_id   Ticket this belongs to.
	 * @param string|null $message_id  Message this belongs to, if any.
	 * @param string      $uploaded_by 'customer' or 'agent'.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function store_from_string( string $bytes, string $name, string $ticket_id, ?string $message_id, string $uploaded_by ) {
		if ( '' === $bytes || strlen( $bytes ) > self::max_size() ) {
			return $this->too_large_error();
		}

		$tmp = $this->temp_file( $name );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		if ( false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- private temp file, moved into place by ingest().
			wp_delete_file( $tmp );
			return new \WP_Error( 'itsdesk_attachment_write_failed', __( 'Could not save the file.', 'deskovi' ), array( 'status' => 500 ) );
		}

		return $this->ingest_temp( $tmp, $name, $ticket_id, $message_id, $uploaded_by );
	}

	/**
	 * Create a private temp file for an incoming attachment.
	 *
	 * @param string $name Original file name.
	 * @return string|\WP_Error
	 */
	private function temp_file( string $name ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = wp_tempnam( '' !== $name ? $name : 'file' );
		if ( ! $tmp ) {
			return new \WP_Error( 'itsdesk_attachment_write_failed', __( 'Could not save the file.', 'deskovi' ), array( 'status' => 500 ) );
		}

		return $tmp;
	}

	/**
	 * Run ingest() on one of our own temp files and make sure it never lingers
	 * after a validation failure (a successful ingest moves it away).
	 *
	 * @param string      $tmp         Temp file created by temp_file().
	 * @param string      $name        Original file name.
	 * @param string      $ticket_id   Ticket id.
	 * @param string|null $message_id  Message id.
	 * @param string      $uploaded_by Uploader.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function ingest_temp( string $tmp, string $name, string $ticket_id, ?string $message_id, string $uploaded_by ) {
		$result = $this->ingest( $tmp, $name, $ticket_id, $message_id, $uploaded_by );
		if ( is_wp_error( $result ) && file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}

		return $result;
	}

	/**
	 * Size-limit error shared by every entry point.
	 */
	private function too_large_error(): \WP_Error {
		return new \WP_Error(
			'itsdesk_attachment_too_large',
			sprintf(
				/* translators: %s: human-readable max size */
				__( 'File exceeds the %s upload limit.', 'deskovi' ),
				size_format( self::max_size() )
			),
			array( 'status' => 400 )
		);
	}

	/**
	 * Shared validation + storage. The size is measured on disk, never taken
	 * from a client-supplied value. Moves $path into the locked folder on success.
	 *
	 * @param string      $path        Existing file to move into place.
	 * @param string      $name        Original file name.
	 * @param string      $ticket_id   Ticket id.
	 * @param string|null $message_id  Message id.
	 * @param string      $uploaded_by Uploader.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function ingest( string $path, string $name, string $ticket_id, ?string $message_id, string $uploaded_by ) {
		clearstatcache( true, $path );
		$size = (int) filesize( $path );
		if ( $size <= 0 || $size > self::max_size() ) {
			return $this->too_large_error();
		}

		global $wpdb;
		$table          = Schema::attachments_table();
		$existing_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ticket_id = %s", $ticket_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $existing_count >= self::max_per_ticket() ) {
			return new \WP_Error( 'itsdesk_attachment_limit', __( 'This ticket has reached its attachment limit.', 'deskovi' ), array( 'status' => 400 ) );
		}

		$original_name = '' !== $name ? sanitize_file_name( $name ) : 'file';
		$ext           = strtolower( (string) pathinfo( $original_name, PATHINFO_EXTENSION ) );

		$allowed = self::allowed_types();
		if ( '' === $ext || ! isset( $allowed[ $ext ] ) ) {
			return new \WP_Error( 'itsdesk_attachment_type', __( 'This file type is not allowed.', 'deskovi' ), array( 'status' => 400 ) );
		}

		// Verify the real file content matches an allowed type — don't trust the extension alone.
		$filetype = wp_check_filetype_and_ext( $path, $original_name );
		$real_ext = $filetype['ext'] ? strtolower( (string) $filetype['ext'] ) : '';
		if ( '' === $real_ext || ! isset( $allowed[ $real_ext ] ) || $real_ext !== $ext ) {
			return new \WP_Error( 'itsdesk_attachment_type', __( 'This file type is not allowed.', 'deskovi' ), array( 'status' => 400 ) );
		}

		$dir = $this->locked_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$random_name = wp_generate_uuid4() . '.' . $ext;
		$destination = trailingslashit( $dir ) . $random_name;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem || ! $wp_filesystem->move( $path, $destination, true ) ) {
			return new \WP_Error( 'itsdesk_attachment_write_failed', __( 'Could not save the uploaded file.', 'deskovi' ), array( 'status' => 500 ) );
		}
		$wp_filesystem->chmod( $destination, 0640 );

		$id = 'att_' . wp_generate_uuid4();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, no core caching API applies.
			Schema::attachments_table(),
			array(
				'id'           => $id,
				'ticket_id'    => $ticket_id,
				'message_id'   => $message_id,
				'filename'     => $original_name,
				'storage_path' => $random_name,
				'mime_type'    => $allowed[ $ext ],
				'size_bytes'   => $size,
				'uploaded_by'  => $uploaded_by,
				'created_at'   => gmdate( 'c' ),
			)
		);

		return array(
			'id'         => $id,
			'ticket_id'  => $ticket_id,
			'message_id' => $message_id,
			'filename'   => $original_name,
			'mime_type'  => $allowed[ $ext ],
			'size_bytes' => $size,
			'created_at' => gmdate( 'c' ),
		);
	}

	/**
	 * All attachment metadata rows for a ticket (no file contents).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function for_ticket( string $ticket_id ): array {
		global $wpdb;
		$table = Schema::attachments_table();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT id, ticket_id, message_id, filename, mime_type, size_bytes, uploaded_by, created_at FROM {$table} WHERE ticket_id = %s ORDER BY created_at ASC", $ticket_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Fetch one attachment row (metadata only) by id.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( string $attachment_id ): ?array {
		global $wpdb;
		$table = Schema::attachments_table();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no core caching API applies.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $attachment_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Absolute path to the stored file for a given attachment row.
	 */
	public function absolute_path( array $attachment ): string {
		$dir = $this->locked_dir();
		if ( is_wp_error( $dir ) ) {
			return '';
		}
		return trailingslashit( $dir ) . (string) $attachment['storage_path'];
	}

	/**
	 * Delete every attachment (DB rows + files) belonging to a ticket.
	 */
	public function delete_for_ticket( string $ticket_id ): void {
		foreach ( $this->for_ticket( $ticket_id ) as $row ) {
			$full = $this->find( (string) $row['id'] );
			if ( null !== $full ) {
				$path = $this->absolute_path( $full );
				if ( '' !== $path && file_exists( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}

		global $wpdb;
		$wpdb->delete( Schema::attachments_table(), array( 'ticket_id' => $ticket_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, no core caching API applies.
	}

	/**
	 * The locked storage directory, created (with hardening files) on first use.
	 *
	 * @return string|\WP_Error
	 */
	private function locked_dir() {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new \WP_Error( 'itsdesk_attachment_dir', (string) $upload_dir['error'] );
		}

		$dir = trailingslashit( $upload_dir['basedir'] ) . self::SUBDIR;

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Require all denied\ndeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $dir;
	}
}
