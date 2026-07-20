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
	'itsdesk_sync_cursor',
	'itsdesk_activity_log',
	'itsdesk_diagnostic_errors',
	'itsdesk_tickets',
	'itsdesk_delivery_secret',
);

foreach ( $itsdesk_options as $itsdesk_option ) {
	delete_option( $itsdesk_option );
}

$itsdesk_role = get_role( 'administrator' );
if ( $itsdesk_role ) {
	$itsdesk_role->remove_cap( 'manage_itsdesk' );
}
