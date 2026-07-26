<?php
/**
 * Creates and upgrades the local ticket storage tables.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

/**
 * dbDelta-based installer for the tickets + ticket_messages tables.
 */
final class Schema {

	public const DB_VERSION_OPTION = 'itsdesk_tickets_db_version';
	public const DB_VERSION        = '1.3.0';

	/**
	 * Create tables if missing, or upgrade if the schema version changed.
	 * Safe to call on every admin_init — dbDelta() is idempotent.
	 */
	public static function maybe_install(): void {
		$installed = get_option( self::DB_VERSION_OPTION, '' );
		if ( self::DB_VERSION === $installed ) {
			return;
		}
		self::install();
	}

	/**
	 * Run on plugin activation.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tickets_table    = self::tickets_table();
		$messages_table   = self::messages_table();

		$sql = "CREATE TABLE {$tickets_table} (
			id VARCHAR(40) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			category VARCHAR(60) DEFAULT NULL,
			subject VARCHAR(255) NOT NULL DEFAULT '',
			order_id BIGINT UNSIGNED DEFAULT NULL,
			guest_order_id BIGINT UNSIGNED DEFAULT NULL,
			order_snapshot LONGTEXT DEFAULT NULL,
			customer_user_id BIGINT UNSIGNED DEFAULT NULL,
			customer_email VARCHAR(190) DEFAULT '',
			customer_name VARCHAR(190) DEFAULT '',
			idempotency_key VARCHAR(100) DEFAULT NULL,
			customer_last_read_at VARCHAR(32) DEFAULT NULL,
			agent_last_reply_at VARCHAR(32) DEFAULT NULL,
			assigned_agent_id BIGINT UNSIGNED DEFAULT NULL,
			created_at VARCHAR(32) NOT NULL,
			updated_at VARCHAR(32) NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_user_id (customer_user_id),
			KEY customer_email (customer_email),
			KEY order_id (order_id),
			KEY idempotency_key (idempotency_key),
			KEY updated_at (updated_at),
			KEY assigned_agent_id (assigned_agent_id)
		) {$charset_collate};";

		$sql2 = "CREATE TABLE {$messages_table} (
			id VARCHAR(40) NOT NULL,
			ticket_id VARCHAR(40) NOT NULL,
			author VARCHAR(20) NOT NULL DEFAULT 'customer',
			body LONGTEXT NOT NULL,
			internal TINYINT(1) NOT NULL DEFAULT 0,
			created_at VARCHAR(32) NOT NULL,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id)
		) {$charset_collate};";

		dbDelta( $sql );
		dbDelta( $sql2 );

		$attachments_table = self::attachments_table();
		$sql3               = "CREATE TABLE {$attachments_table} (
			id VARCHAR(40) NOT NULL,
			ticket_id VARCHAR(40) NOT NULL,
			message_id VARCHAR(40) DEFAULT NULL,
			filename VARCHAR(255) NOT NULL,
			storage_path VARCHAR(255) NOT NULL,
			mime_type VARCHAR(100) NOT NULL,
			size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
			uploaded_by VARCHAR(20) NOT NULL DEFAULT 'customer',
			created_at VARCHAR(32) NOT NULL,
			PRIMARY KEY  (id),
			KEY ticket_id (ticket_id),
			KEY message_id (message_id)
		) {$charset_collate};";

		dbDelta( $sql3 );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Tickets table name.
	 */
	public static function tickets_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'itsdesk_tickets';
	}

	/**
	 * Ticket messages table name.
	 */
	public static function messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'itsdesk_ticket_messages';
	}

	/**
	 * Ticket attachments table name.
	 */
	public static function attachments_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'itsdesk_ticket_attachments';
	}
}
