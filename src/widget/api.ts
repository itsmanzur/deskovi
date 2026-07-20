import type { Category, OrderSummary, Ticket, WidgetConfig } from './types';

function cfg(): WidgetConfig {
	const c = window.itsdeskWidget;
	if ( ! c ) {
		throw new Error( 'itsdeskWidget bootstrap missing' );
	}
	return c;
}

function join( path: string ): string {
	const root = cfg().restRoot.replace( /\/?$/, '/' );
	const clean = path.replace( /^\//, '' );
	return root + clean;
}

async function request< T >(
	path: string,
	options: RequestInit = {}
): Promise< T > {
	const c = cfg();
	const headers: Record< string, string > = {
		Accept: 'application/json',
		...( options.headers as Record< string, string > | undefined ),
	};
	if ( c.nonce ) {
		headers[ 'X-WP-Nonce' ] = c.nonce;
	}
	if ( options.body && ! headers[ 'Content-Type' ] ) {
		headers[ 'Content-Type' ] = 'application/json';
	}

	const res = await fetch( join( path ), {
		credentials: 'same-origin',
		...options,
		headers,
	} );

	const data = await res.json().catch( () => ( {} ) );
	if ( ! res.ok ) {
		const msg =
			( data && ( data.message || data.code ) ) ||
			c.i18n.error ||
			'Request failed';
		throw new Error( String( msg ) );
	}
	return data as T;
}

export function isAuthed( c: WidgetConfig = cfg() ): boolean {
	return !! c.loggedIn || !! c.guestAuthenticated;
}

export function fetchTickets(): Promise< { tickets: Ticket[] } > {
	return request( 'itsdesk/v1/customer/tickets' );
}

export function fetchUnread(): Promise< { count: number } > {
	return request( 'itsdesk/v1/customer/unread' );
}

export function fetchTicket( id: string ): Promise< Ticket > {
	return request( `itsdesk/v1/customer/tickets/${ encodeURIComponent( id ) }` );
}

export function createTicket( data: {
	subject: string;
	body: string;
	category: string;
	order_id?: number;
} ): Promise< Ticket > {
	return request( 'itsdesk/v1/customer/tickets', {
		method: 'POST',
		body: JSON.stringify( data ),
	} );
}

export function replyTicket( id: string, body: string ): Promise< Ticket > {
	return request(
		`itsdesk/v1/customer/tickets/${ encodeURIComponent( id ) }/replies`,
		{
			method: 'POST',
			body: JSON.stringify( { body } ),
		}
	);
}

export function fetchCategories(): Promise< { categories: Category[] } > {
	return request( 'itsdesk/v1/tickets/categories' );
}

export function fetchOrders(): Promise< {
	orders: OrderSummary[];
	window_days?: number;
} > {
	return request( 'itsdesk/v1/customer/orders' );
}

export function guestStart(
	orderId: number,
	email: string
): Promise< { ok: boolean; mail_sent?: boolean } > {
	return request( 'itsdesk/v1/guest/verify/start', {
		method: 'POST',
		body: JSON.stringify( { order_id: orderId, email } ),
	} );
}

export function guestConfirm(
	orderId: number,
	email: string,
	code: string
): Promise< { ok: boolean } > {
	return request( 'itsdesk/v1/guest/verify/confirm', {
		method: 'POST',
		body: JSON.stringify( { order_id: orderId, email, code } ),
	} );
}
