import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import * as Icons from '../components/Icons';
import { getDocsFeatures, getDocsSections, useExtensions } from '../extensions';
import type { DocsFeature, DocsSection } from '../extensions';
import { getProCatalog } from '../proCatalog';

type Tab = 'features' | 'documentation';

type Card = {
	id: string;
	icon: ReactNode;
	title: string;
	description: string;
	screen?: string;
	locked?: boolean;
};

function openScreen( id: string ): void {
	window.location.hash = '#/' + id;
}

function freeFeatures(): Card[] {
	const i = ( Icon: ( p: { size?: number } ) => JSX.Element ) => <Icon size={ 16 } />;
	return [
		{
			id: 'tickets',
			icon: i( Icons.IconTicket ),
			title: __( 'Support tickets', 'deskovi' ),
			description: __( 'Customers open tickets, logged in or as a verified guest. Every ticket has a conversation, a status (Open, Pending, Resolved, Closed) and optional attachments.', 'deskovi' ),
			screen: 'tickets',
		},
		{
			id: 'orders',
			icon: i( Icons.IconCart ),
			title: __( 'Order-aware support', 'deskovi' ),
			description: __( 'Link a ticket to a WooCommerce order and see its items, total, shipping, status and timeline beside the conversation.', 'deskovi' ),
		},
		{
			id: 'refunds',
			icon: i( Icons.IconRefund ),
			title: __( 'Refunds from a ticket', 'deskovi' ),
			description: __( 'Issue a full or partial refund, or resend the order invoice email, without opening the order.', 'deskovi' ),
		},
		{
			id: 'priority',
			icon: i( Icons.IconFlag ),
			title: __( 'Priority and assignment', 'deskovi' ),
			description: __( 'Mark tickets Low, Normal, High or Urgent, hand them to an agent, and filter by Unassigned or Assigned to me.', 'deskovi' ),
		},
		{
			id: 'notes',
			icon: i( Icons.IconQueue ),
			title: __( 'Internal notes', 'deskovi' ),
			description: __( 'Leave a note only your team can see. It is never shown to the customer and its attachments stay private too.', 'deskovi' ),
		},
		{
			id: 'macros',
			icon: i( Icons.IconQueue ),
			title: __( 'Canned replies', 'deskovi' ),
			description: __( 'Save reusable answers with placeholders such as {customer_name} and {order_id}, and insert one with a click.', 'deskovi' ),
			screen: 'macros',
		},
		{
			id: 'attachments',
			icon: i( Icons.IconPaperclip ),
			title: __( 'Attachments', 'deskovi' ),
			description: __( 'Customers and agents can attach JPG, PNG, GIF, WebP, PDF and TXT files, up to 5 MB each. Files are served only to people who may see the ticket.', 'deskovi' ),
		},
		{
			id: 'bulk',
			icon: i( Icons.IconCheck ),
			title: __( 'Bulk actions and CSV export', 'deskovi' ),
			description: __( 'Change the status of, or delete, many tickets at once. Export the list you are looking at to a spreadsheet.', 'deskovi' ),
		},
		{
			id: 'notifications',
			icon: i( Icons.IconBell ),
			title: __( 'Email notifications', 'deskovi' ),
			description: __( 'Switch on or off the emails for a new ticket, a customer reply, an agent reply and an assignment. They are sent by WordPress itself.', 'deskovi' ),
			screen: 'notifications',
		},
		{
			id: 'widget',
			icon: i( Icons.IconWidget ),
			title: __( 'Storefront chat widget', 'deskovi' ),
			description: __( 'A small launcher on your store. It loads only after it is clicked and never on the cart or checkout. Visitors who are not logged in confirm with a code sent to their email.', 'deskovi' ),
			screen: 'widget',
		},
		{
			id: 'overview',
			icon: i( Icons.IconOverview ),
			title: __( 'Overview', 'deskovi' ),
			description: __( 'One screen with what is open, pending and unassigned, how long the oldest ticket has been waiting, new tickets per day and the latest tickets.', 'deskovi' ),
			screen: 'overview',
		},
		{
			id: 'privacy',
			icon: i( Icons.IconShield ),
			title: __( 'Privacy by design', 'deskovi' ),
			description: __( 'Everything stays in your WordPress database. Choose what order details agents see, and use WordPress’s own export and erase tools for personal data.', 'deskovi' ),
			screen: 'privacy',
		},
		{
			id: 'diagnostics',
			icon: i( Icons.IconPulse ),
			title: __( 'Diagnostics and activity log', 'deskovi' ),
			description: __( 'Health checks for your setup and a log of what Deskovi has done, so a problem is easy to explain and fix.', 'deskovi' ),
			screen: 'diagnostics',
		},
		{
			id: 'addons',
			icon: i( Icons.IconLink ),
			title: __( 'Built for add-ons', 'deskovi' ),
			description: __( 'Other plugins can add screens, buttons and panels through a documented extension API, without changing Deskovi.', 'deskovi' ),
		},
	];
}

function freeDocs(): DocsSection[] {
	return [
		{
			id: 'find-deskovi',
			title: __( 'Finding your way around', 'deskovi' ),
			intro: __( 'Open “Deskovi” in the WordPress admin menu. The menu on the left of the Deskovi screen is grouped: Work (Tickets, Canned Replies), Insights (Activity, and Reports with Pro), Automate (Pro), Channels (Widget, and Mailboxes with Pro) and Settings (Notifications, Data & Privacy, Diagnostics, Docs & Features). Overview is the first item.', 'deskovi' ),
		},
		{
			id: 'overview',
			title: __( 'Reading the Overview', 'deskovi' ),
			intro: __( 'The four numbers at the top are the tickets that need you. Open and Pending count the tickets in those statuses. Unassigned counts the open and pending ones that have no agent, and turns amber when there are any. “Oldest waiting” is how long the longest-waiting ticket has been open. The bars show how many tickets arrived on each of the last 7 days (UTC).', 'deskovi' ),
		},
		{
			id: 'how-customers-open-tickets',
			title: __( 'How customers open a ticket', 'deskovi' ),
			intro: __( 'Customers use the chat widget on your store. If they are logged in they fill in a short form (subject, category, message, an optional order). If they are not, they enter their email and the order number and confirm with a one-time code sent to that email, so no account is needed.', 'deskovi' ),
		},
		{
			id: 'reply-to-a-ticket',
			title: __( 'Replying to a ticket', 'deskovi' ),
			intro: __( 'Open Deskovi → Tickets and click a ticket. The conversation is on the right.', 'deskovi' ),
			steps: [
				__( 'Write your answer in the reply box. Insert a canned reply from the menu above it if you have one.', 'deskovi' ),
				__( 'Tick “Internal note (not visible to customer)” for a message only your team should see.', 'deskovi' ),
				__( 'Click “Send reply”. The customer is emailed if the “Agent reply” notification is on. When an agent replies to an open ticket it moves to Pending; when a customer replies to a resolved ticket it opens again.', 'deskovi' ),
			],
		},
		{
			id: 'status-priority',
			title: __( 'Status and priority', 'deskovi' ),
			intro: __( 'Use the four buttons under the ticket to set Open, Pending, Resolved or Closed. Pending means you are waiting for the customer. Use the Priority menu (Low, Normal, High, Urgent) to show what needs attention first. Both are saved at once.', 'deskovi' ),
		},
		{
			id: 'assign-tickets',
			title: __( 'Assigning tickets', 'deskovi' ),
			intro: __( 'Pick an agent in “Assigned to”, or leave it on Unassigned for anyone to take. Above the ticket list, “Unassigned” and “Assigned to me” filter the list. The assigned agent is the one emailed about customer replies. Agents are people who may manage Deskovi (administrators and shop managers by default).', 'deskovi' ),
		},
		{
			id: 'link-order',
			title: __( 'Linking a ticket to an order', 'deskovi' ),
			intro: __( 'If the customer chose an order it is linked already. Otherwise type a WooCommerce order number into “Order ID” and click “Link order”. The ticket then shows the order’s items, total, payment and shipping method and timeline. Billing address and phone only appear if you allowed them under Data & Privacy.', 'deskovi' ),
		},
		{
			id: 'refunds',
			title: __( 'Issuing a refund or resending an invoice', 'deskovi' ),
			intro: __( 'You need the permission to edit WooCommerce orders. In the linked order card:', 'deskovi' ),
			steps: [
				__( 'Leave the amount empty to refund what is left of the order, or type a smaller amount for a partial refund.', 'deskovi' ),
				__( 'Add a short reason if you like; it is saved with the refund.', 'deskovi' ),
				__( 'Click “Issue refund”. If the payment gateway can refund, the money is returned there; if not, the refund is recorded in WooCommerce and you return the money yourself. A note in the ticket says which happened.', 'deskovi' ),
				__( 'To send the customer their order email again instead, click “Resend invoice”. No money moves.', 'deskovi' ),
			],
		},
		{
			id: 'attachments',
			title: __( 'Attachments', 'deskovi' ),
			intro: __( 'Click “Attach” under the reply box. Allowed files: JPG, PNG, GIF, WebP, PDF and TXT, up to 5 MB each. Files are checked on upload and are downloadable only by the customer who owns the ticket and by agents. Attachments on internal notes are never visible to the customer.', 'deskovi' ),
		},
		{
			id: 'canned-replies',
			title: __( 'Canned replies', 'deskovi' ),
			intro: __( 'Answers you give again and again.', 'deskovi' ),
			steps: [
				__( 'Go to Deskovi → Canned Replies and click “Add canned reply”.', 'deskovi' ),
				__( 'Write a title and the message. Placeholders such as {customer_name} and {order_id} are filled in for the ticket you use it on.', 'deskovi' ),
				__( 'In a ticket, choose it from “Insert canned reply”. It is added to your draft, so you can still edit it before sending.', 'deskovi' ),
			],
		},
		{
			id: 'bulk-export',
			title: __( 'Bulk actions and CSV export', 'deskovi' ),
			intro: __( 'Tick the box beside several tickets to change their status or delete them together; deleting also removes their messages and files and cannot be undone. To export, search or filter the list, then click “Export CSV”: the file contains exactly the tickets you are looking at.', 'deskovi' ),
		},
		{
			id: 'notifications',
			title: __( 'Email notifications', 'deskovi' ),
			intro: __( 'Deskovi → Notifications has four switches: a new ticket (emailed to every agent), a customer reply (to the assigned agent, or all agents if nobody is assigned), an agent reply (to the customer) and an assignment (to the agent). Emails are sent by WordPress, so they need a working mail setup on your server; a mail plugin with SMTP makes delivery more reliable.', 'deskovi' ),
		},
		{
			id: 'widget-setup',
			title: __( 'Setting up the chat widget', 'deskovi' ),
			intro: __( 'Deskovi → Widget. Turn on “Enable support widget”, choose the corner (placement), a light or dark theme, and an optional text for the button; leave the text empty for an icon only. The widget does not load on the cart or checkout, and when it is off nothing at all is added to your store. It uses your store’s own font.', 'deskovi' ),
		},
		{
			id: 'privacy-data',
			title: __( 'Privacy and your data', 'deskovi' ),
			intro: __( 'Tickets, messages and attachments live in your own WordPress database; Deskovi sends nothing to an outside service. Under Data & Privacy you choose whether agents may see the billing address and phone number, whether to share diagnostics (only with your consent), the historical import window (off by default) and how long synced order context is kept. Deskovi adds a privacy-policy suggestion and works with Tools → Export / Erase Personal Data: an erase request deletes that customer’s tickets, messages and files.', 'deskovi' ),
		},
		{
			id: 'diagnostics-activity',
			title: __( 'Diagnostics and the activity log', 'deskovi' ),
			intro: __( 'Diagnostics lists the versions of WordPress, WooCommerce and PHP and runs checks on your setup; copy its report when you ask for help. Activity is a short log of what Deskovi did (replies, assignments, refunds, errors). Use these two before contacting support.', 'deskovi' ),
		},
		{
			id: 'permissions',
			title: __( 'Who can use Deskovi', 'deskovi' ),
			intro: __( 'Administrators and shop managers get the “manage_itsdesk” permission when the plugin is activated, and they can use every Deskovi screen. Refunds also need the WooCommerce permission to edit orders. To give another role access, add the manage_itsdesk capability to it with a role editor plugin. Customers never see the admin screens.', 'deskovi' ),
		},
		{
			id: 'addons',
			title: __( 'For developers: add-ons', 'deskovi' ),
			intro: __( 'Add-on plugins can hook into tickets, notifications, refunds and the widget, and can add screens, panels and bulk actions to this admin app. The contract is documented in contracts/extensions-v1.md in the plugin, and it is what Deskovi Pro is built on.', 'deskovi' ),
		},
	];
}

function toCard( feature: DocsFeature ): Card {
	return {
		id: feature.id,
		icon: feature.icon,
		title: feature.title,
		description: feature.description,
		screen: feature.screen,
	};
}

export function DocsScreen() {
	useExtensions();
	const [ tab, setTab ] = useState< Tab >( 'features' );

	const registeredFeatures = getDocsFeatures();
	const registeredSections = getDocsSections();
	const proFeatures = registeredFeatures.filter( ( f ) => f.plan === 'pro' );
	const proSections = registeredSections.filter( ( s ) => s.plan === 'pro' );
	const proActive = proFeatures.length > 0 || proSections.length > 0;

	const free: Card[] = [ ...freeFeatures(), ...registeredFeatures.filter( ( f ) => f.plan !== 'pro' ).map( toCard ) ];
	const proCards: Card[] = proActive
		? proFeatures.map( toCard )
		: getProCatalog().map( ( item ) => {
			const Icon = Icons[ item.icon ];
			return { id: item.id, icon: <Icon size={ 16 } />, title: item.title, description: item.description, locked: true };
		} );

	const freeDocsList = [ ...freeDocs(), ...registeredSections.filter( ( s ) => s.plan !== 'pro' ) ];

	const renderCard = ( card: Card, pro: boolean ) => (
		<div className={ 'itsdesk-feature' + ( card.locked ? ' is-locked' : '' ) } key={ card.id }>
			<div className="itsdesk-feature__icon">{ card.icon }</div>
			<strong>
				{ card.title }
				{ pro && <span className="itsdesk-pro-tag">{ __( 'Pro', 'deskovi' ) }</span> }
			</strong>
			<p>{ card.description }</p>
			{ card.screen && ! card.locked && (
				<button type="button" className="itsdesk-link" onClick={ () => openScreen( card.screen as string ) }>
					{ __( 'Open', 'deskovi' ) } →
				</button>
			) }
		</div>
	);

	const renderSection = ( section: DocsSection ) => (
		<div className="itsdesk-card" id={ `itsdesk-doc-${ section.id }` } key={ section.id } style={ { marginTop: 14 } }>
			<div className="itsdesk-card__head">
				<h3 className="itsdesk-card__title">{ section.title }</h3>
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
	);

	return (
		<div className="itsdesk-admin__panel-inner">
			<div>
				<h2 className="itsdesk-page-title">{ __( 'Docs & Features', 'deskovi' ) }</h2>
				<p className="itsdesk-admin__muted">
					{ __( 'What Deskovi can do and how to set it up. Everything is stored in your own WordPress database; there is no outside account.', 'deskovi' ) }
				</p>
			</div>

			<div className="itsdesk-tabs" role="tablist">
				<button type="button" className={ 'itsdesk-tab' + ( tab === 'features' ? ' is-active' : '' ) } role="tab" aria-selected={ tab === 'features' } onClick={ () => setTab( 'features' ) }>
					{ __( 'Features', 'deskovi' ) }
				</button>
				<button type="button" className={ 'itsdesk-tab' + ( tab === 'documentation' ? ' is-active' : '' ) } role="tab" aria-selected={ tab === 'documentation' } onClick={ () => setTab( 'documentation' ) }>
					{ __( 'Documentation', 'deskovi' ) }
				</button>
			</div>

			{ tab === 'features' ? (
				<>
					<h3 className="itsdesk-admin__section">{ __( 'Deskovi', 'deskovi' ) }</h3>
					<div className="itsdesk-features">{ free.map( ( card ) => renderCard( card, false ) ) }</div>

					<h3 className="itsdesk-admin__section">{ proActive ? __( 'Deskovi Pro', 'deskovi' ) : __( 'Available with Deskovi Pro', 'deskovi' ) }</h3>
					{ ! proActive && (
						<p className="itsdesk-admin__muted">
							{ __( 'Deskovi Pro is a separate add-on. These features turn on when it is installed; they are not part of the free plugin.', 'deskovi' ) }{ ' ' }
							<button type="button" className="itsdesk-link" onClick={ () => openScreen( 'pro' ) }>{ __( 'See what Pro adds', 'deskovi' ) } →</button>
						</p>
					) }
					<div className="itsdesk-features">{ proCards.map( ( card ) => renderCard( card, true ) ) }</div>
				</>
			) : (
				<div>
					<div className="itsdesk-card">
						<div className="itsdesk-card__head">
							<h3>{ __( 'On this page', 'deskovi' ) }</h3>
						</div>
						<ul style={ { margin: '8px 0', paddingLeft: 18, columns: 2 } }>
							{ [ ...freeDocsList, ...proSections ].map( ( section ) => (
								<li key={ section.id } style={ { marginBottom: 6 } }>
									<a href={ `#itsdesk-doc-${ section.id }` } onClick={ ( e ) => { e.preventDefault(); document.getElementById( `itsdesk-doc-${ section.id }` )?.scrollIntoView( { behavior: 'smooth', block: 'start' } ); } }>{ section.title }</a>
								</li>
							) ) }
						</ul>
					</div>

					{ freeDocsList.map( renderSection ) }

					<h3 className="itsdesk-admin__section">{ __( 'Deskovi Pro', 'deskovi' ) }</h3>
					{ proActive ? (
						proSections.map( renderSection )
					) : (
						<div className="itsdesk-card">
							<p>
								{ __( 'The guides for Deskovi Pro (connecting Gmail or Microsoft 365, automation rules, response times, the AI assistant, reports and more) appear here once the add-on is installed. The features it adds are listed under Features.', 'deskovi' ) }
							</p>
						</div>
					) }
				</div>
			) }
		</div>
	);
}
