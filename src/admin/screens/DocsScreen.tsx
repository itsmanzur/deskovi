import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	IconBell,
	IconCart,
	IconCheck,
	IconExternal,
	IconFlag,
	IconPulse,
	IconQueue,
	IconRefund,
	IconShield,
	IconTicket,
	IconWidget,
} from '../components/Icons';

type Tab = 'features' | 'documentation';

type Feature = {
	icon: JSX.Element;
	title: string;
	description: string;
};

type DocSection = {
	id: string;
	title: string;
	intro: string;
	steps?: string[];
};

export function DocsScreen() {
	const [ tab, setTab ] = useState< Tab >( 'features' );

	const features: Feature[] = [
		{
			icon: <IconTicket size={ 16 } />,
			title: __( 'Support ticket system', 'deskovi' ),
			description: __(
				'Customers open tickets — logged in or as a verified guest — with replies, status tracking, and file attachments.',
				'deskovi'
			),
		},
		{
			icon: <IconCart size={ 16 } />,
			title: __( 'Order-aware support', 'deskovi' ),
			description: __(
				'Link a ticket to the relevant WooCommerce order automatically, so agents see order details without leaving the ticket.',
				'deskovi'
			),
		},
		{
			icon: <IconFlag size={ 16 } />,
			title: __( 'Ticket priority', 'deskovi' ),
			description: __(
				'Mark tickets Low, Normal, High, or Urgent so agents always know what needs attention first.',
				'deskovi'
			),
		},
		{
			icon: <IconCheck size={ 16 } />,
			title: __( 'Bulk actions', 'deskovi' ),
			description: __(
				'Select several tickets at once to change their status or delete them — no need to open each one.',
				'deskovi'
			),
		},
		{
			icon: <IconExternal size={ 16 } />,
			title: __( 'CSV export', 'deskovi' ),
			description: __(
				'Export your ticket list to a spreadsheet any time, respecting whatever search or filter is active.',
				'deskovi'
			),
		},
		{
			icon: <IconRefund size={ 16 } />,
			title: __( 'Refunds from a ticket', 'deskovi' ),
			description: __(
				'Issue a full or partial refund, or resend the order invoice email, right from the ticket — no need to open the order separately.',
				'deskovi'
			),
		},
		{
			icon: <IconQueue size={ 16 } />,
			title: __( 'Canned replies', 'deskovi' ),
			description: __(
				'Save reusable reply templates with placeholders like customer name and order number — insert one with a single click.',
				'deskovi'
			),
		},
		{
			icon: <IconBell size={ 16 } />,
			title: __( 'Email notifications', 'deskovi' ),
			description: __(
				'Configurable alerts for new tickets, replies, and assignments — sent locally by WordPress, nothing external.',
				'deskovi'
			),
		},
		{
			icon: <IconWidget size={ 16 } />,
			title: __( 'Storefront chat widget', 'deskovi' ),
			description: __(
				'An optional, lightweight chat launcher for customers. Loads lazily and skips checkout, so it never slows down a sale.',
				'deskovi'
			),
		},
		{
			icon: <IconShield size={ 16 } />,
			title: __( 'Privacy by design', 'deskovi' ),
			description: __(
				'Guest email verification for non-logged-in customers, plus built-in support for WordPress’s own data export and erase tools.',
				'deskovi'
			),
		},
		{
			icon: <IconPulse size={ 16 } />,
			title: __( 'Diagnostics & activity log', 'deskovi' ),
			description: __(
				'Built-in health checks and a running activity log, so you always know what Deskovi has been doing on your store.',
				'deskovi'
			),
		},
	];

	const docs: DocSection[] = [
		{
			id: 'find-deskovi',
			title: __( 'Where do I find Deskovi?', 'deskovi' ),
			intro: __(
				'Look for “Deskovi” in your WordPress admin sidebar (left-hand menu). Click it to open the dashboard — from there, the tabs across the top (Tickets, Widget, Canned Replies, and so on) take you to every part of the plugin.',
				'deskovi'
			),
		},
		{
			id: 'how-customers-open-tickets',
			title: __( 'How customers open a ticket', 'deskovi' ),
			intro: __(
				'Customers can open a support ticket in two ways: if they’re logged into an account on your store, they just fill in a short form. If they’re not logged in, they verify themselves with a one-time code sent to their email — no account required.',
				'deskovi'
			),
		},
		{
			id: 'reply-to-a-ticket',
			title: __( 'Replying to a ticket', 'deskovi' ),
			intro: __(
				'As an agent, open Deskovi → Tickets, click any ticket in the list, and you’ll see the full conversation on the right.',
				'deskovi'
			),
			steps: [
				__( 'Type your reply in the box at the bottom of the ticket.', 'deskovi' ),
				__(
					'Tick “Internal note” if the message is just for your team and should not be visible to the customer.',
					'deskovi'
				),
				__( 'Click “Send reply”. The customer is emailed automatically (if notifications are on).', 'deskovi' ),
			],
		},
		{
			id: 'canned-replies',
			title: __( 'Using canned replies', 'deskovi' ),
			intro: __(
				'Canned replies are ready-made answers for the questions you get asked all the time — no retyping needed.',
				'deskovi'
			),
			steps: [
				__( 'Go to Deskovi → Canned Replies and click “Add canned reply”.', 'deskovi' ),
				__(
					'Write your message. You can use placeholders like {customer_name} or {order_id} — Deskovi fills these in automatically for whichever ticket you’re replying to.',
					'deskovi'
				),
				__(
					'While replying to any ticket, pick your saved reply from the “Insert canned reply” menu above the message box — it’s added to your draft instantly.',
					'deskovi'
				),
			],
		},
		{
			id: 'assign-tickets',
			title: __( 'Assigning tickets to agents', 'deskovi' ),
			intro: __(
				'Open any ticket and use the “Assigned to” dropdown to hand it to a specific agent, or leave it unassigned for anyone to pick up. On the ticket list, use the “Unassigned” or “Assigned to me” buttons to quickly filter what you’re looking at.',
				'deskovi'
			),
		},
		{
			id: 'priority-and-bulk',
			title: __( 'Priority and bulk actions', 'deskovi' ),
			intro: __(
				'Set a ticket’s priority from its “Priority” dropdown so your team knows what’s urgent. To handle several tickets at once, tick the checkbox next to each one in the list — a toolbar appears letting you change their status or delete them all together.',
				'deskovi'
			),
		},
		{
			id: 'export-csv',
			title: __( 'Exporting tickets to a spreadsheet', 'deskovi' ),
			intro: __(
				'On the Tickets screen, search or filter the list the way you want, then click “Export CSV”. A spreadsheet file downloads with exactly the tickets you were looking at — open it in Excel, Google Sheets, or any spreadsheet program.',
				'deskovi'
			),
		},
		{
			id: 'refunds',
			title: __( 'Issuing a refund or resending an invoice', 'deskovi' ),
			intro: __(
				'If a ticket is linked to an order, scroll to the “Refund” section inside that ticket.',
				'deskovi'
			),
			steps: [
				__(
					'Leave the amount blank to refund the full remaining balance, or type a specific amount for a partial refund.',
					'deskovi'
				),
				__( 'Optionally add a short reason — this is saved for your records.', 'deskovi' ),
				__( 'Click “Issue refund”. A note appears in the ticket confirming what happened.', 'deskovi' ),
				__(
					'Need to send the customer their order details again instead? Click “Resend invoice” — no refund involved.',
					'deskovi'
				),
			],
		},
		{
			id: 'widget-setup',
			title: __( 'Setting up the storefront chat widget', 'deskovi' ),
			intro: __(
				'Go to Deskovi → Widget to turn the chat launcher on or off, choose where it sits on the page, and pick a light or dark theme. When it’s off, nothing extra loads on your store — so there’s no cost to leaving it disabled until you’re ready.',
				'deskovi'
			),
		},
		{
			id: 'privacy-data',
			title: __( 'Privacy and your data', 'deskovi' ),
			intro: __(
				'Every ticket, message, and attachment is stored in your own WordPress database — Deskovi never sends data to an outside service. Go to Deskovi → Data & Privacy to control what order information is shared with agents, and how long data is kept. Deskovi also plugs into WordPress’s own built-in tools (Tools → Export/Erase Personal Data) for handling data requests.',
				'deskovi'
			),
		},
	];

	return (
		<div className="itsdesk-admin__panel-inner">
			<section className="itsdesk-hero">
				<div style={ { position: 'relative', zIndex: 1 } }>
					<div className="itsdesk-hero__eyebrow">
						{ __( 'Docs & Features', 'deskovi' ) }
					</div>
					<h2>
						{ __(
							'Everything Deskovi can do for your WooCommerce store',
							'deskovi'
						) }
					</h2>
					<p>
						{ __(
							'Deskovi is a self-contained, local-first WooCommerce helpdesk — support tickets, order-aware replies, and an optional customer chat widget, all stored in your own WordPress database. No external account, no subscription, nothing leaving your site.',
							'deskovi'
						) }
					</p>
				</div>
			</section>

			<div className="itsdesk-admin__actions">
				<button
					type="button"
					className={
						'itsdesk-btn' +
						( tab === 'features' ? ' itsdesk-btn--primary' : ' itsdesk-btn--secondary' )
					}
					onClick={ () => setTab( 'features' ) }
				>
					{ __( 'Features', 'deskovi' ) }
				</button>
				<button
					type="button"
					className={
						'itsdesk-btn' +
						( tab === 'documentation' ? ' itsdesk-btn--primary' : ' itsdesk-btn--secondary' )
					}
					onClick={ () => setTab( 'documentation' ) }
				>
					{ __( 'Documentation', 'deskovi' ) }
				</button>
			</div>

			{ tab === 'features' ? (
				<div className="itsdesk-features" style={ { marginTop: 8 } }>
					{ features.map( ( feature ) => (
						<div className="itsdesk-feature" key={ feature.title }>
							<div className="itsdesk-feature__icon">{ feature.icon }</div>
							<strong>{ feature.title }</strong>
							<p>{ feature.description }</p>
						</div>
					) ) }
				</div>
			) : (
				<div style={ { marginTop: 8 } }>
					<div className="itsdesk-card">
						<div className="itsdesk-card__head">
							<h3>{ __( 'On this page', 'deskovi' ) }</h3>
						</div>
						<ul style={ { margin: '8px 0', paddingLeft: 18, columns: 2 } }>
							{ docs.map( ( section ) => (
								<li key={ section.id } style={ { marginBottom: 6 } }>
									<a href={ `#itsdesk-doc-${ section.id }` }>{ section.title }</a>
								</li>
							) ) }
						</ul>
					</div>

					{ docs.map( ( section ) => (
						<div
							className="itsdesk-card"
							id={ `itsdesk-doc-${ section.id }` }
							key={ section.id }
							style={ { marginTop: 14 } }
						>
							<div className="itsdesk-card__head">
								<h3>{ section.title }</h3>
							</div>
							<p>{ section.intro }</p>
							{ section.steps && (
								<ol style={ { margin: '8px 0 0', paddingLeft: 18 } }>
									{ section.steps.map( ( step, index ) => (
										<li key={ index } style={ { marginBottom: 6 } }>
											{ step }
										</li>
									) ) }
								</ol>
							) }
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
