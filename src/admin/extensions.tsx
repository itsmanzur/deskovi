/**
 * Public extension API of the Deskovi admin app (contracts/extensions-v1.md).
 *
 * Add-on plugins load their own bundle after this one (script dependency on
 * the `itsdesk-admin` handle) and call `window.itsdesk.admin.register*()`.
 * Registrations may arrive before or after the React tree has mounted — the
 * store is subscribable and the app re-renders when it changes.
 */
import { Component, Fragment, useSyncExternalStore } from '@wordpress/element';
import type { ReactNode } from 'react';
import { apiDelete, apiErrorMessage, apiGet, apiPost } from './api';
import * as Icons from './components/Icons';
import { MetricCard } from './components/MetricCard';
import { SkeletonPanel } from './components/Skeleton';
import { StatusBadge } from './components/StatusBadge';
import { Toggle } from './components/Toggle';
import { ProTeaserScreen } from './screens/ProTeaserScreen';
import type { OrderSnapshot, Ticket } from './types';

/** Bumped only on a breaking change to this contract. Mirrors ITSDESK_API_VERSION. */
export const API_VERSION = 1;

export type ToastFn = ( message: string, tone?: 'ok' | 'danger' ) => void;

export type AdminConfig = NonNullable< Window[ 'itsdeskAdmin' ] >;

export type AdminContext = {
	apiVersion: number;
	config: AdminConfig;
	/** Paths are relative to the REST root, e.g. `/my-addon/v1/things`. */
	api: {
		get: < T >( path: string ) => Promise< T >;
		post: < T >( path: string, data?: unknown ) => Promise< T >;
		del: < T >( path: string ) => Promise< T >;
		errorMessage: ( err: unknown ) => string;
	};
	components: {
		Toggle: typeof Toggle;
		StatusBadge: typeof StatusBadge;
		MetricCard: typeof MetricCard;
		SkeletonPanel: typeof SkeletonPanel;
		Icons: typeof Icons;
	};
};

export type NavGroup = 'work' | 'insights' | 'automate' | 'channels' | 'settings';

export type ScreenProps ={ onToast: ToastFn; ctx: AdminContext };

export type ScreenRegistration = {
	id: string;
	label: string;
	icon?: ReactNode;
	/** Sidebar section. Missing or unknown values land in `settings`. */
	group?: NavGroup;
	/** Sort position inside the group (built-in screens use 10, 20, 30 …). */
	order?: number;
	render: ( props: ScreenProps ) => ReactNode;
};

export type TicketDetailContext = {
	ticket: Ticket;
	order: OrderSnapshot | null;
	currentUser: { id: number; name: string; canManageOrders: boolean };
	busy: boolean;
	onToast: ToastFn;
	/** Reload the ticket list. */
	reload: () => void;
	/** Re-fetch the open ticket (and its order). */
	refreshTicket: () => void;
	replyBody: string;
	setReplyBody: ( value: string | ( ( current: string ) => string ) ) => void;
};

export type TicketRowContext = {
	ticket: Ticket;
	isOpen: boolean;
};

export type BulkContext = {
	selectedIds: string[];
	tickets: Ticket[];
	onToast: ToastFn;
	reload: () => void;
	clearSelection: () => void;
};

export type OverviewContext = {
	/** Open one of the admin screens by id, for example `mailboxes`. */
	navigate: ( screen: string ) => void;
};

export type ListFiltersContext = {
	/** Add-on filter values (the `x` entries of the list request). */
	extra: Record< string, string >;
	/** Set one add-on filter; an empty value removes it. */
	setExtra: ( key: string, value: string ) => void;
};

export type SlotContextMap = {
	'overview.top': OverviewContext;
	'ticket.list.filters': ListFiltersContext;
	'ticket.detail.header': TicketDetailContext;
	'ticket.detail.order': TicketDetailContext;
	'ticket.detail.meta': TicketDetailContext;
	'ticket.reply.toolbar': TicketDetailContext;
	'ticket.list.rowExtra': TicketRowContext;
};

export type SlotName = keyof SlotContextMap;

export type SlotProps< K extends SlotName > = SlotContextMap[ K ] & { ctx: AdminContext };

export type SlotRegistration< K extends SlotName > = {
	id: string;
	order?: number;
	render: ( props: SlotProps< K > ) => ReactNode;
};

export type BulkActionRegistration = {
	id: string;
	label: string;
	order?: number;
	tone?: 'secondary' | 'danger';
	run: ( props: BulkContext & { ctx: AdminContext } ) => Promise< void > | void;
};

/** A card on the Features tab of Docs & Features. */
export type DocsFeature = {
	id: string;
	title: string;
	description: string;
	icon?: ReactNode;
	order?: number;
	/** `pro`: listed under "Deskovi Pro" and replaces the teaser with the same id. */
	plan?: 'pro';
	/** Id of a screen to open from the card. */
	screen?: string;
};

/** A guide on the Documentation tab of Docs & Features. */
export type DocsSection = {
	id: string;
	title: string;
	intro: string;
	steps?: string[];
	order?: number;
	plan?: 'pro';
};

export type ItsdeskAdminApi = {
	apiVersion: number;
	ctx: AdminContext;
	registerScreen: ( screen: ScreenRegistration ) => void;
	registerSlot: < K extends SlotName >( slot: K, registration: SlotRegistration< K > ) => void;
	registerBulkAction: ( action: BulkActionRegistration ) => void;
	registerDocsFeature: ( feature: DocsFeature ) => void;
	registerDocsSection: ( section: DocsSection ) => void;
};

declare global {
	interface Window {
		itsdesk?: { admin?: ItsdeskAdminApi };
	}
}

const DEFAULT_ORDER = 100;

const screens = new Map< string, ScreenRegistration >();
const slots = new Map< SlotName, Map< string, SlotRegistration< SlotName > > >();
const bulkActions = new Map< string, BulkActionRegistration >();
const docsFeatures = new Map< string, DocsFeature >();
const docsSections = new Map< string, DocsSection >();

const listeners = new Set< () => void >();
let version = 0;

function emit(): void {
	version++;
	listeners.forEach( ( listener ) => listener() );
}

function subscribe( listener: () => void ): () => void {
	listeners.add( listener );
	return () => {
		listeners.delete( listener );
	};
}

function getVersion(): number {
	return version;
}

/** Re-render the calling component whenever an add-on registers something. */
export function useExtensions(): number {
	return useSyncExternalStore( subscribe, getVersion );
}

function byOrder< T extends { id: string; order?: number } >( a: T, b: T ): number {
	return ( a.order ?? DEFAULT_ORDER ) - ( b.order ?? DEFAULT_ORDER ) || a.id.localeCompare( b.id );
}

export const adminContext: AdminContext = {
	apiVersion: API_VERSION,
	config: ( window.itsdeskAdmin ?? {} ) as AdminConfig,
	api: {
		get: apiGet,
		post: apiPost,
		del: apiDelete,
		errorMessage: apiErrorMessage,
	},
	components: { Toggle, StatusBadge, MetricCard, SkeletonPanel, Icons },
};

export function getExtensionScreens(): ScreenRegistration[] {
	return Array.from( screens.values() ).sort( ( a, b ) => byOrder( a, b ) );
}

export function getExtensionScreen( id: string ): ScreenRegistration | undefined {
	return screens.get( id );
}

export function getDocsFeatures(): DocsFeature[] {
	return Array.from( docsFeatures.values() ).sort( ( a, b ) => byOrder( a, b ) );
}

export function getDocsSections(): DocsSection[] {
	return Array.from( docsSections.values() ).sort( ( a, b ) => byOrder( a, b ) );
}

export function getBulkActions(): BulkActionRegistration[] {
	return Array.from( bulkActions.values() ).sort( ( a, b ) => byOrder( a, b ) );
}

function getSlotRegistrations( name: SlotName ): Array< SlotRegistration< SlotName > > {
	return Array.from( slots.get( name )?.values() ?? [] ).sort( ( a, b ) => byOrder( a, b ) );
}

/**
 * A faulty add-on component must never take the whole Deskovi admin down.
 */
class Boundary extends Component< { label: string; children?: ReactNode }, { failed: boolean } > {
	state = { failed: false };

	static getDerivedStateFromError() {
		return { failed: true };
	}

	componentDidCatch( error: unknown ) {
		// eslint-disable-next-line no-console
		console.error( `Deskovi extension "${ this.props.label }" failed to render.`, error );
	}

	render() {
		return this.state.failed ? null : this.props.children;
	}
}

// Render a registered add-on screen, or null if the id is unknown.
export function ExtensionScreen( { id, onToast }: { id: string; onToast: ToastFn } ) {
	useExtensions();
	const screen = getExtensionScreen( id );
	if ( ! screen ) {
		return null;
	}
	return (
		<Boundary label={ id }>
			{ screen.render( { onToast, ctx: adminContext } ) }
		</Boundary>
	);
}

// Render everything add-ons registered for a named slot.
export function Slot< K extends SlotName >( { name, context }: { name: K; context: SlotContextMap[ K ] } ) {
	useExtensions();
	const registrations = getSlotRegistrations( name );
	if ( registrations.length === 0 ) {
		return null;
	}
	const props = { ...context, ctx: adminContext } as SlotProps< K >;
	return (
		<>
			{ registrations.map( ( registration ) => (
				<Fragment key={ registration.id }>
					<Boundary label={ `${ name }:${ registration.id }` }>
						{ ( registration as unknown as SlotRegistration< K > ).render( props ) }
					</Boundary>
				</Fragment>
			) ) }
		</>
	);
}

const api: ItsdeskAdminApi = {
	apiVersion: API_VERSION,
	ctx: adminContext,
	registerScreen( screen ) {
		screens.set( screen.id, screen );
		emit();
	},
	registerSlot( slot, registration ) {
		const bucket = slots.get( slot ) ?? new Map< string, SlotRegistration< SlotName > >();
		bucket.set( registration.id, registration as unknown as SlotRegistration< SlotName > );
		slots.set( slot, bucket );
		emit();
	},
	registerBulkAction( action ) {
		bulkActions.set( action.id, action );
		emit();
	},
	registerDocsFeature( feature ) {
		docsFeatures.set( feature.id, feature );
		emit();
	},
	registerDocsSection( section ) {
		docsSections.set( section.id, section );
		emit();
	},
};

window.itsdesk = window.itsdesk || {};
window.itsdesk.admin = api;

// Stores without Deskovi Pro see what it offers. Pro registers its own "pro" screen, which replaces this one.
screens.set( 'pro', {
	id: 'pro',
	label: 'Pro',
	icon: <Icons.IconStar size={ 17 } />,
	group: 'settings',
	order: 900,
	render: ( props ) => <ProTeaserScreen { ...props } />,
} );
