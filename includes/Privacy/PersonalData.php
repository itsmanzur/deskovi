<?php
/**
 * WP privacy policy + export/erase for bridge tickets.
 *
 * @package Itsdesk
 */

declare(strict_types=1);

namespace Itsdesk\Privacy;

use Itsdesk\Tickets\TicketRepository;

/**
 * GDPR helpers for local bridge cache only.
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
			'Deskovi is a WooCommerce support connector. When you contact support, this store may keep a temporary ticket bridge cache (subject, messages, linked order ID, email) so conversations can sync to Deskovi SaaS. Payment card data is never collected by Deskovi. Billing address and phone are shared only if the store owner enables those privacy toggles. Guests may verify with order number + billing email (one-time code). You can request export or erasure of local bridge data via WordPress tools; remote Deskovi SaaS deletion is handled separately when connected.',
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
	 * Export tickets for email.
	 *
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$email = sanitize_email( $email );
		$data  = array();
		foreach ( ( new TicketRepository() )->all() as $ticket ) {
			if ( ! is_array( $ticket ) ) {
				continue;
			}
			$ticket_email = strtolower( (string) ( $ticket['customer_email'] ?? '' ) );
			if ( $ticket_email !== strtolower( $email ) ) {
				continue;
			}
			$data[] = array(
				'group_id'          => 'itsdesk-tickets',
				'group_label'       => __( 'Deskovi support tickets', 'deskovi' ),
				'group_description' => __( 'Local ticket bridge cache on this store.', 'deskovi' ),
				'item_id'           => (string) ( $ticket['id'] ?? '' ),
				'data'              => array(
					array(
						'name'  => __( 'Subject', 'deskovi' ),
						'value' => (string) ( $ticket['subject'] ?? '' ),
					),
					array(
						'name'  => __( 'Status', 'deskovi' ),
						'value' => (string) ( $ticket['status'] ?? '' ),
					),
					array(
						'name'  => __( 'Order ID', 'deskovi' ),
						'value' => (string) ( $ticket['order_id'] ?? '' ),
					),
					array(
						'name'  => __( 'Created', 'deskovi' ),
						'value' => (string) ( $ticket['created_at'] ?? '' ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase local tickets for email.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		$email = sanitize_email( $email );
		$repo  = new TicketRepository();
		$all   = $repo->all();
		$kept  = array();
		$removed = 0;
		foreach ( $all as $ticket ) {
			if ( ! is_array( $ticket ) ) {
				continue;
			}
			$ticket_email = strtolower( (string) ( $ticket['customer_email'] ?? '' ) );
			if ( $ticket_email === strtolower( $email ) ) {
				++$removed;
				continue;
			}
			$kept[] = $ticket;
		}
		if ( $removed > 0 ) {
			update_option( TicketRepository::OPTION_KEY, array_values( $kept ), false );
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(
				sprintf(
					/* translators: %d: count */
					__( 'Removed %d Deskovi bridge ticket(s) from this store.', 'deskovi' ),
					$removed
				),
			),
			'done'           => true,
		);
	}
}
