<?php
/**
 * Email notifications for ticket events (local wp_mail() only).
 *
 * @package Itsdesk
 */

declare(strict_types=1);
namespace Itsdesk\Tickets;

defined( 'ABSPATH' ) || exit;

use Itsdesk\Admin\AdminMenu;
use Itsdesk\Admin\Capabilities;
use Itsdesk\Diagnostics\ActivityLogger;

/**
 * Listens on the itsdesk_ticket_* hooks fired by TicketService and emails
 * the relevant people, if the matching NotificationSettings toggle allows it.
 * A failed send is logged and otherwise swallowed — never affects the
 * ticket operation that triggered it, since these hooks fire after save().
 */
final class Notifications {

	private ActivityLogger $logger;

	private NotificationSettings $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger   = new ActivityLogger();
		$this->settings = new NotificationSettings();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'itsdesk_ticket_created', array( $this, 'on_created' ) );
		add_action( 'itsdesk_ticket_replied', array( $this, 'on_replied' ), 10, 2 );
		add_action( 'itsdesk_ticket_assigned', array( $this, 'on_assigned' ), 10, 3 );
	}

	/**
	 * New ticket — notify every support agent.
	 *
	 * @param array<string, mixed> $ticket Ticket.
	 */
	public function on_created( array $ticket ): void {
		$settings = $this->settings->get();
		if ( empty( $settings['agent_new_ticket'] ) ) {
			return;
		}

		$recipients = $this->agent_emails();
		if ( empty( $recipients ) ) {
			return;
		}

		$subject_line = (string) ( $ticket['subject'] ?? '' );
		$first_body   = '';
		foreach ( (array) ( $ticket['messages'] ?? array() ) as $message ) {
			if ( is_array( $message ) ) {
				$first_body = (string) ( $message['body'] ?? '' );
				break;
			}
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: ticket subject */
			__( '[%1$s] New support ticket: %2$s', 'deskovi' ),
			get_bloginfo( 'name' ),
			$subject_line
		);

		$body = sprintf(
			/* translators: 1: ticket subject, 2: customer name, 3: customer email, 4: message excerpt, 5: dashboard URL */
			__(
				"A new support ticket was opened.\n\nSubject: %1\$s\nFrom: %2\$s <%3\$s>\n\nMessage:\n%4\$s\n\nOpen it here: %5\$s",
				'deskovi'
			),
			$subject_line,
			(string) ( $ticket['customer_name'] ?? '' ),
			(string) ( $ticket['customer_email'] ?? '' ),
			$this->excerpt( $first_body ),
			admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG )
		);

		foreach ( $recipients as $email ) {
			$this->send( $email, $subject, $body, $subject_line );
		}
	}

	/**
	 * A message was added to a ticket — notify the other side, unless it's an internal note.
	 *
	 * @param array<string, mixed> $ticket  Ticket.
	 * @param array<string, mixed> $message The message that was just appended.
	 */
	public function on_replied( array $ticket, array $message ): void {
		if ( ! empty( $message['internal'] ) ) {
			return;
		}

		$subject_line = (string) ( $ticket['subject'] ?? '' );
		$excerpt      = $this->excerpt( (string) ( $message['body'] ?? '' ) );

		if ( 'agent' === ( $message['author'] ?? '' ) ) {
			$settings = $this->settings->get();
			if ( empty( $settings['customer_reply'] ) ) {
				return;
			}

			$customer_email = (string) ( $ticket['customer_email'] ?? '' );
			if ( '' === $customer_email ) {
				return;
			}

			$subject = sprintf(
				/* translators: %s: ticket subject */
				__( 'Re: %s', 'deskovi' ),
				$subject_line
			);

			$body = sprintf(
				/* translators: 1: message excerpt */
				__(
					"You have a new reply on your support ticket.\n\n%1\$s\n\nLog back in to view the full reply.",
					'deskovi'
				),
				$excerpt
			);

			$this->send( $customer_email, $subject, $body, $subject_line );
			return;
		}

		if ( 'customer' === ( $message['author'] ?? '' ) ) {
			$settings = $this->settings->get();
			if ( empty( $settings['agent_new_reply'] ) ) {
				return;
			}

			$assigned_agent_id = ! empty( $ticket['assigned_agent_id'] ) ? (int) $ticket['assigned_agent_id'] : 0;
			$recipients        = array();
			if ( $assigned_agent_id > 0 ) {
				$agent = get_userdata( $assigned_agent_id );
				if ( $agent && $agent->user_email ) {
					$recipients[] = $agent->user_email;
				}
			} else {
				$recipients = $this->agent_emails();
			}

			if ( empty( $recipients ) ) {
				return;
			}

			$subject = sprintf(
				/* translators: %s: ticket subject */
				__( 'New reply on ticket: %s', 'deskovi' ),
				$subject_line
			);

			$body = sprintf(
				/* translators: 1: message excerpt, 2: dashboard URL */
				__(
					"A customer replied to a support ticket.\n\n%1\$s\n\nOpen it here: %2\$s",
					'deskovi'
				),
				$excerpt,
				admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG )
			);

			foreach ( $recipients as $email ) {
				$this->send( $email, $subject, $body, $subject_line );
			}
		}
	}

	/**
	 * A ticket was (re)assigned — notify only the newly assigned agent.
	 *
	 * @param array<string, mixed> $ticket             Ticket.
	 * @param int|null             $agent_id           Newly assigned agent id, or null on unassignment.
	 * @param int|null             $previous_agent_id  Previously assigned agent id, if any.
	 */
	public function on_assigned( array $ticket, ?int $agent_id, ?int $previous_agent_id ): void {
		if ( null === $agent_id || $agent_id === $previous_agent_id ) {
			return;
		}

		$settings = $this->settings->get();
		if ( empty( $settings['agent_assigned'] ) ) {
			return;
		}

		$agent = get_userdata( $agent_id );
		if ( ! $agent || ! $agent->user_email ) {
			return;
		}

		$subject_line = (string) ( $ticket['subject'] ?? '' );

		$subject = sprintf(
			/* translators: %s: ticket subject */
			__( 'Ticket assigned to you: %s', 'deskovi' ),
			$subject_line
		);

		$body = sprintf(
			/* translators: 1: ticket subject, 2: dashboard URL */
			__(
				"A support ticket was assigned to you.\n\nSubject: %1\$s\n\nOpen it here: %2\$s",
				'deskovi'
			),
			$subject_line,
			admin_url( 'admin.php?page=' . AdminMenu::PAGE_SLUG )
		);

		$this->send( $agent->user_email, $subject, $body, $subject_line );
	}

	/**
	 * Every WP user with the manage_itsdesk capability, email addresses only.
	 *
	 * @return array<int, string>
	 */
	private function agent_emails(): array {
		$users = get_users(
			array(
				'capability' => Capabilities::CAP,
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);

		$emails = array();
		foreach ( $users as $user ) {
			if ( $user->user_email ) {
				$emails[] = $user->user_email;
			}
		}

		return $emails;
	}

	/**
	 * Send one email, logging the outcome. Never throws — a failed send must
	 * not affect the ticket operation that triggered it.
	 */
	private function send( string $to, string $subject, string $body, string $ticket_subject ): void {
		try {
			$sent = wp_mail( $to, $subject, $body );
		} catch ( \Throwable $e ) {
			$sent = false;
		}

		if ( ! $sent ) {
			$this->logger->log(
				'Notification',
				sprintf(
					/* translators: 1: recipient email, 2: ticket subject */
					__( 'Failed to email %1$s about ticket %2$s', 'deskovi' ),
					$to,
					$ticket_subject
				),
				'Fail'
			);
			return;
		}

		$this->logger->log(
			'Notification',
			sprintf(
				/* translators: 1: recipient email, 2: ticket subject */
				__( 'Emailed %1$s about ticket %2$s', 'deskovi' ),
				$to,
				$ticket_subject
			),
			'OK'
		);
	}

	/**
	 * Strip tags and truncate to a plain-text excerpt.
	 */
	private function excerpt( string $text, int $length = 200 ): string {
		$plain = trim( wp_strip_all_tags( $text ) );
		if ( mb_strlen( $plain ) <= $length ) {
			return $plain;
		}

		return mb_strimwidth( $plain, 0, $length, '…' );
	}
}
