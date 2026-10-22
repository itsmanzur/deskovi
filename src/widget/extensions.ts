/**
 * Public extension API of the storefront widget (contracts/extensions-v1.md).
 *
 * Add-on scripts depend on the `itsdesk-widget` script handle and call
 * `window.itsdesk.widget.registerSlot()`. The widget bootstraps this API
 * synchronously when its loader runs, long before the (lazy) panel opens.
 */
import { apiGet, apiPost } from './api';
import type { WidgetConfig } from './types';

/** Bumped only on a breaking change to this contract. */
export const WIDGET_API_VERSION = 1;

export type CreateForm = {
	subject: string;
	body: string;
	category: string;
	order_id: number | null;
};

export type CreateSlotContext = {
	config: WidgetConfig;
	/** Current values of the new-ticket form. */
	form: () => CreateForm;
	/** Called after the customer changes a field. Returns an unsubscribe function. */
	onChange: ( callback: ( form: CreateForm ) => void ) => () => void;
	/** Calls on this site's REST API (nonce and cookies attached). Paths are relative to the REST root. */
	api: {
		get: < T >( path: string ) => Promise< T >;
		post: < T >( path: string, data?: unknown ) => Promise< T >;
	};
	/** Leave the form without creating a ticket. */
	dismiss: () => void;
};

export type CreateSlotRegistration = {
	id: string;
	order?: number;
	/** Fill `host`. May return a cleanup function, run when the form closes. */
	mount: ( host: HTMLElement, ctx: CreateSlotContext ) => void | ( () => void );
};

export type SlotName = 'ticket.create.aside';

const slots = new Map< SlotName, Map< string, CreateSlotRegistration > >();

export function registerSlot( name: SlotName, registration: CreateSlotRegistration ): void {
	if ( name !== 'ticket.create.aside' || ! registration || ! registration.id || typeof registration.mount !== 'function' ) {
		return;
	}
	const bucket = slots.get( name ) ?? new Map< string, CreateSlotRegistration >();
	bucket.set( registration.id, registration );
	slots.set( name, bucket );
}

export function getSlots( name: SlotName ): CreateSlotRegistration[] {
	return Array.from( slots.get( name )?.values() ?? [] ).sort( ( a, b ) => ( a.order ?? 100 ) - ( b.order ?? 100 ) );
}

export const slotApi = { get: apiGet, post: apiPost };

/** Make `window.itsdesk.widget` available to add-on scripts. */
export function exposeWidgetApi(): void {
	const root = window as unknown as { itsdesk?: Record< string, unknown > };
	root.itsdesk = root.itsdesk ?? {};
	root.itsdesk.widget = {
		apiVersion: WIDGET_API_VERSION,
		registerSlot,
	};
}
