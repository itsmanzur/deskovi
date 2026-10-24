<?php
/**
 * Uninstall cleanup for Deskovi.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$itsdesk_options = array(
	'itsdesk_version',
	'itsdesk_connection',
	'itsdesk_site_identity',
	'itsdesk_privacy_settings',
	'itsdesk_widget_settings',
	'itsdesk_notification_settings',
	'itsdesk_canned_replies',
	'itsdesk_sync_cursor',
	'itsdesk_activity_log',
	'itsdesk_diagnostic_errors',
	'itsdesk_tickets',
	'itsdesk_delivery_secret',
	'itsdesk_tickets_db_version',
	'itsdesk_caps_granted',
);

foreach ( $itsdesk_options as $itsdesk_option ) {
	delete_option( $itsdesk_option );
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}itsdesk_ticket_messages" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup of plugin's own custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}itsdesk_ticket_attachments" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup of plugin's own custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}itsdesk_tickets" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup of plugin's own custom tables.

// Uploaded ticket attachments live in a private folder under uploads.
$itsdesk_uploads = wp_upload_dir();
if ( empty( $itsdesk_uploads['error'] ) ) {
	$itsdesk_attachments_dir = trailingslashit( $itsdesk_uploads['basedir'] ) . 'itsdesk-attachments';
	if ( is_dir( $itsdesk_attachments_dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		if ( WP_Filesystem() && $wp_filesystem ) {
			$wp_filesystem->delete( $itsdesk_attachments_dir, true );
		}
	}
}

foreach ( array( 'administrator', 'shop_manager' ) as $itsdesk_role_name ) {
	$itsdesk_role = get_role( $itsdesk_role_name );
	if ( $itsdesk_role ) {
		$itsdesk_role->remove_cap( 'manage_itsdesk' );
	}
}

remove_role( 'itsdesk_agent' );
