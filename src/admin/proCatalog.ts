import { __ } from '@wordpress/i18n';

export type ProFeatureInfo = {
	/** Same id Deskovi Pro uses when it registers the real entry, which then replaces this one. */
	id: string;
	title: string;
	description: string;
	bullets: string[];
	icon: 'IconMail' | 'IconBolt' | 'IconClock' | 'IconBook' | 'IconSparkle' | 'IconChart' | 'IconQueue' | 'IconFileGeneric' | 'IconShield' | 'IconTicket' | 'IconCart' | 'IconLink';
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
			id: 'pro-tags',
			icon: 'IconQueue',
			title: __( 'Tags', 'deskovi' ),
			description: __( 'Put your own labels on tickets and find them again.', 'deskovi' ),
			bullets: [
				__( 'Labels such as “vip” or “damaged-item”, shown in the ticket list', 'deskovi' ),
				__( 'Filter the list by tag, save it as a view, and let automation rules add tags', 'deskovi' ),
			],
		},
		{
			id: 'pro-fields',
			icon: 'IconFileGeneric',
			title: __( 'Custom fields', 'deskovi' ),
			description: __( 'Add your own details to tickets, such as the return reason or the carrier.', 'deskovi' ),
			bullets: [
				__( 'Text, number, dropdown, checkbox and date fields that only your team sees', 'deskovi' ),
				__( 'They move with a merge and are deleted with the ticket', 'deskovi' ),
			],
		},
		{
			id: 'pro-roles',
			icon: 'IconShield',
			title: __( 'Roles & permissions', 'deskovi' ),
			description: __( 'Decide which role may refund, see reports, set up rules or manage mailboxes.', 'deskovi' ),
			bullets: [
				__( 'A Support Manager role next to the Support Agent role', 'deskovi' ),
				__( 'One table that shows what every role may do', 'deskovi' ),
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
			id: 'pro-team',
			icon: 'IconTicket',
			title: __( 'Team tools', 'deskovi' ),
			description: __( 'Work on tickets together without stepping on each other.', 'deskovi' ),
			bullets: [
				__( 'A notice when a colleague has the same ticket open or is writing a reply', 'deskovi' ),
				__( '@mentions in internal notes, reply drafts, and handover with a note', 'deskovi' ),
				__( 'Star and watch tickets, log the time you spend, and work from the keyboard', 'deskovi' ),
			],
		},
		{
			id: 'pro-orders',
			icon: 'IconCart',
			title: __( 'Order tools', 'deskovi' ),
			description: __( 'Do more with the order without leaving the ticket.', 'deskovi' ),
			bullets: [
				__( 'Refund chosen items or an amount, change the status, cancel, add a note or fix the shipping address', 'deskovi' ),
				__( 'A customer card with lifetime value, orders and earlier tickets', 'deskovi' ),
				__( 'Send a replacement order or a one-time coupon', 'deskovi' ),
			],
		},
		{
			id: 'pro-import',
			icon: 'IconFileGeneric',
			title: __( 'Import tickets', 'deskovi' ),
			description: __( 'Move your history from another helpdesk without emailing a single customer.', 'deskovi' ),
			bullets: [
				__( 'Bring in a CSV export from Help Scout, Zendesk, Freshdesk and others', 'deskovi' ),
				__( 'Check first, keep the original dates and tags, and never import the same ticket twice', 'deskovi' ),
			],
		},
		{
			id: 'pro-connections',
			icon: 'IconLink',
			title: __( 'Connections and API', 'deskovi' ),
			description: __( 'Tell the other tools you use what happens in your helpdesk.', 'deskovi' ),
			bullets: [
				__( 'Signed webhooks for Zapier, Make or your CRM, and messages to Slack, Discord, Teams or Telegram', 'deskovi' ),
				__( 'A REST API with read and write keys, a customer lookup for your CRM, and a hook for your own code', 'deskovi' ),
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
