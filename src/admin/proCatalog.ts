import { __ } from '@wordpress/i18n';

export type ProFeatureInfo = {
	/** Same id Deskovi Pro uses when it registers the real entry, which then replaces this one. */
	id: string;
	title: string;
	description: string;
	bullets: string[];
	icon: 'IconMail' | 'IconBolt' | 'IconClock' | 'IconBook' | 'IconSparkle' | 'IconChart';
};

/**
 * What the Deskovi Pro add-on offers, shown to stores that do not have it.
 * Plain text only: this list never changes behaviour. When Pro is active it registers the
 * real entries under the same ids and these are no longer shown.
 */
export function getProCatalog(): ProFeatureInfo[] {
	return [
		{
			id: 'pro-email',
			icon: 'IconMail',
			title: __( 'Email channel', 'deskovi' ),
			description: __( 'Customers write to your support address and the mail becomes a ticket. Your replies go back as email and thread with the customer’s own mail.', 'deskovi' ),
			bullets: [
				__( 'Gmail / Google Workspace, Microsoft 365 / Outlook or any IMAP mailbox, with your own app, no relay server', 'deskovi' ),
				__( 'Replies are matched to the right ticket, quoted history and signatures are removed, attachments are saved', 'deskovi' ),
				__( 'Agents can answer a notification by replying to the email', 'deskovi' ),
			],
		},
		{
			id: 'pro-automation',
			icon: 'IconBolt',
			title: __( 'Automation', 'deskovi' ),
			description: __( 'Rules that assign, prioritise and annotate tickets for you when something happens.', 'deskovi' ),
			bullets: [
				__( 'Assign to one agent, in turn, or to whoever has the fewest open tickets', 'deskovi' ),
				__( 'Practice mode shows what a rule would do before it changes anything', 'deskovi' ),
				__( 'Rules never email customers, refund or delete anything', 'deskovi' ),
			],
		},
		{
			id: 'pro-sla',
			icon: 'IconClock',
			title: __( 'Response times (SLA)', 'deskovi' ),
			description: __( 'First-reply and resolution targets per priority, counted in your working hours.', 'deskovi' ),
			bullets: [
				__( 'Timers on every ticket and a “Needs attention” queue', 'deskovi' ),
				__( 'The clock can pause while you wait for the customer', 'deskovi' ),
				__( 'One-time warning and breach emails to your team', 'deskovi' ),
			],
		},
		{
			id: 'pro-selfservice',
			icon: 'IconBook',
			title: __( 'Self-service', 'deskovi' ),
			description: __( 'Before a customer sends a ticket, the chat widget shows where their order is and pages of your store that may already answer the question.', 'deskovi' ),
			bullets: [
				__( 'Order status and tracking for the customer’s own order', 'deskovi' ),
				__( 'Suggested public pages, then “Did this answer your question?”', 'deskovi' ),
			],
		},
		{
			id: 'pro-ai',
			icon: 'IconSparkle',
			title: __( 'AI assistant', 'deskovi' ),
			description: __( 'Order-aware reply drafts, summaries, translation and suggestions inside the ticket, with your own OpenAI or Anthropic key.', 'deskovi' ),
			bullets: [
				__( 'Customer details are masked before anything leaves your site', 'deskovi' ),
				__( 'Drafts only: nothing is sent or changed unless an agent does it', 'deskovi' ),
			],
		},
		{
			id: 'pro-reports',
			icon: 'IconChart',
			title: __( 'Reports and satisfaction', 'deskovi' ),
			description: __( 'How fast you answer, what is waiting, who is busy and how customers feel, plus store views such as tickets per product.', 'deskovi' ),
			bullets: [
				__( 'Optional one-click satisfaction survey when a ticket is resolved', 'deskovi' ),
				__( 'Weekly or monthly summary email and optional deletion of old tickets', 'deskovi' ),
			],
		},
	];
}
