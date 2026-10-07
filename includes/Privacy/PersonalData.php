<?php
/**
 * WP privacy policy + export/erase for bridge tickets.
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Privacy;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Tickets\TicketRepository;

/**
 * GDPR export and erase for the support tickets stored in this store's database.
 * Add-ons holding per-ticket data hook itsdesk_tickets_deleted, and register
 * their own exporter/eraser through the core wp_privacy_* filters.
 */
final class PersonalData {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_policy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Suggested privacy policy text.
	 */
	public function register_policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__(
			'Deskovi is a WooCommerce support ticket plugin. When you contact support, this store stores your ticket (subject, messages, attachments, linked order ID, email) in its own database. Payment card data is never collected by Deskovi. Billing address and phone are shared with support agents only if the store owner enables those privacy toggles. Guests may verify with order number + billing email (one-time code). You can request export or erasure of this data via WordPress tools.',
			'deskovi'
		) . '</p>';

		wp_add_privacy_policy_content( 'Deskovi', wp_kses_post( $content ) );
	}

	/**
	 * @param array<string, mixed> $exporters Exporters.
	 * @return array<string, mixed>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['itsdesk-tickets'] = array(
			'exporter_friendly_name' => __( 'Deskovi support tickets', 'deskovi' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string, mixed> $erasers Erasers.
	 * @return array<string, mixed>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['itsdesk-tickets'] = array(
			'eraser_friendly_name' => __( 'Deskovi support tickets', 'deskovi' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Tickets per exporter / eraser request page.
	 */
	private const BATCH_SIZE = 25;

	/**
	 * Export tickets for email: ticket fields, customer-visible messages and
	 * attachment file names. Internal agent notes are not part of the export.
	 *
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$email   = sanitize_email( $email );
		$page    = max( 1, $page );
		$tickets = ( new TicketRepository() )->for_guest_email( $email );
		$batch   = array_slice( $tickets, ( $page - 1 ) * self::BATCH_SIZE, self::BATCH_SIZE );
		$data    = array();

		foreach ( $batch as $ticket ) {
			$fields = array(
				array(
					'name'  => __( 'Subject', 'deskovi' ),
					'value' => (string) ( $ticket['subject'] ?? '' ),
				),
				array(
					'name'  => __( 'Status', 'deskovi' ),
					'value' => (string) ( $ticket['status'] ?? '' ),
				),
				array(
					'name'  => __( 'Name', 'deskovi' ),
					'value' => (string) ( $ticket['customer_name'] ?? '' ),
				),
				array(
					'name'  => __( 'Email', 'deskovi' ),
					'value' => (string) ( $ticket['customer_email'] ?? '' ),
				),
				array(
					'name'  => __( 'Order ID', 'deskovi' ),
					'value' => (string) ( $ticket['order_id'] ?? '' ),
				),
				array(
					'name'  => __( 'Created', 'deskovi' ),
					'value' => (string) ( $ticket['created_at'] ?? '' ),
				),
			);

			foreach ( (array) ( $ticket['messages'] ?? array() ) as $message ) {
				if ( ! is_array( $message ) || ! empty( $message['internal'] ) ) {
					continue;
				}
				$fields[] = array(
					'name'  => sprintf(
						/* translators: 1: message author (customer/agent/system), 2: date */
						__( 'Message (%1$s, %2$s)', 'deskovi' ),
						(string) ( $message['author'] ?? '' ),
						(string) ( $message['created_at'] ?? '' )
					),
					'value' => (string) ( $message['body'] ?? '' ),
				);
			}

			foreach ( (array) ( $ticket['attachments'] ?? array() ) as $attachment ) {
				if ( ! is_array( $attachment ) ) {
					continue;
				}
				$fields[] = array(
					'name'  => __( 'Attachment', 'deskovi' ),
					'value' => (string) ( $attachment['filename'] ?? '' ),
				);
			}

			$data[] = array(
				'group_id'          => 'itsdesk-tickets',
				'group_label'       => __( 'Deskovi support tickets', 'deskovi' ),
				'group_description' => __( 'Support tickets stored on this store.', 'deskovi' ),
				'item_id'           => (string) ( $ticket['id'] ?? '' ),
				'data'              => $fields,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $tickets ) <= $page * self::BATCH_SIZE,
		);
	}

	/**
	 * Erase tickets for email: ticket rows, messages and attachment files.
	 * Works in batches — each call removes up to BATCH_SIZE tickets and reports
	 * done only when none are left, so the $page argument is intentionally unused
	 * (the remaining set shrinks as tickets are deleted).
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		$email   = sanitize_email( $email );
		$repo    = new TicketRepository();
		$tickets = $repo->for_guest_email( $email );
		$batch   = array_slice( $tickets, 0, self::BATCH_SIZE );

		$ids = array();
		foreach ( $batch as $ticket ) {
			$ids[] = (string) ( $ticket['id'] ?? '' );
		}
		$removed = $repo->delete_ids( $ids );

		// A batch that removed nothing means the delete failed; stop instead of
		// letting WordPress re-request the same page forever.
		if ( ! empty( $ids ) && 0 === $removed ) {
			return array(
				'items_removed'  => false,
				'items_retained' => true,
				'messages'       => array(
					__( 'Deskovi support tickets could not be removed from this store.', 'deskovi' ),
				),
				'done'           => true,
			);
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(
				sprintf(
					/* translators: %d: count */
					__( 'Removed %d Deskovi support ticket(s) from this store.', 'deskovi' ),
					$removed
				),
			),
			'done'           => count( $tickets ) <= self::BATCH_SIZE,
		);
	}
}
