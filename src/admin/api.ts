import apiFetch from '@wordpress/api-fetch';
import type {
	ActivityData,
	Agent,
	DiagnosticsData,
	OrderSnapshot,
	OverviewData,
	PrivacySettings,
	Ticket,
	TicketCategory,
	TicketOrderResponse,
	WidgetSettings,
} from './types';

const config = window.itsdeskAdmin;

if ( config?.nonce && config?.restRoot ) {
	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
	apiFetch.use( apiFetch.createRootURLMiddleware( config.restRoot ) );
}

const base = '/itsdesk/v1';

function apiErrorMessage( err: unknown ): string {
	if ( err && typeof err === 'object' ) {
		const e = err as {
			message?: string;
			data?: { message?: string };
		};
		return e.message || e.data?.message || 'Request failed.';
	}
	return 'Request failed.';
}

export function fetchOverview(): Promise< OverviewData > {
	return apiFetch( { path: `${ base }/overview` } );
}

export function fetchWidget(): Promise< WidgetSettings > {
	return apiFetch( { path: `${ base }/widget` } );
}

export function saveWidget( data: Partial< WidgetSettings > ): Promise< WidgetSettings > {
	return apiFetch( {
		path: `${ base }/widget`,
		method: 'POST',
		data,
	} );
}

export function fetchPrivacy(): Promise< PrivacySettings > {
	return apiFetch( { path: `${ base }/privacy` } );
}

export function savePrivacy( data: Partial< PrivacySettings > ): Promise< PrivacySettings > {
	return apiFetch( {
		path: `${ base }/privacy`,
		method: 'POST',
		data,
	} );
}

export function fetchDiagnostics(): Promise< DiagnosticsData > {
	return apiFetch( { path: `${ base }/diagnostics` } );
}

export function fetchActivity(): Promise< ActivityData > {
	return apiFetch( { path: `${ base }/activity` } );
}

export function fetchTickets(): Promise< { tickets: Ticket[] } > {
	return apiFetch( { path: `${ base }/tickets` } );
}

export function fetchTicket( id: string ): Promise< Ticket > {
	return apiFetch( { path: `${ base }/tickets/${ id }` } );
}

export function fetchTicketCategories(): Promise< { categories: TicketCategory[] } > {
	return apiFetch( { path: `${ base }/tickets/categories` } );
}

export function createTicket( data: {
	subject: string;
	body: string;
	category: string;
	order_id?: number;
	customer_user_id?: number;
} ): Promise< Ticket > {
	return apiFetch( {
		path: `${ base }/tickets`,
		method: 'POST',
		data,
	} );
}

export function replyTicket(
	id: string,
	data: { body: string; internal?: boolean }
): Promise< Ticket > {
	return apiFetch( {
		path: `${ base }/tickets/${ id }/replies`,
		method: 'POST',
		data,
	} );
}

export function updateTicketStatus( id: string, status: string ): Promise< Ticket > {
	return apiFetch( {
		path: `${ base }/tickets/${ id }/status`,
		method: 'POST',
		data: { status },
	} );
}

export function fetchAgents(): Promise< { agents: Agent[] } > {
	return apiFetch( { path: `${ base }/agents` } );
}

export function assignTicket(
	id: string,
	agentId: number | null
): Promise< Ticket > {
	return apiFetch( {
		path: `${ base }/tickets/${ id }/assign`,
		method: 'POST',
		data: { agent_id: agentId },
	} );
}

export function uploadTicketAttachment(
	id: string,
	file: File,
	messageId?: string
): Promise< { id: string; ticket_id: string; message_id: string | null; filename: string; mime_type: string; size_bytes: number; created_at: string } > {
	const formData = new FormData();
	formData.append( 'file', file );
	if ( messageId ) {
		formData.append( 'message_id', messageId );
	}
	return apiFetch( {
		path: `${ base }/tickets/${ id }/attachments`,
		method: 'POST',
		body: formData,
	} );
}

export function fetchTicketOrder( ticketId: string ): Promise< TicketOrderResponse > {
	return apiFetch( { path: `${ base }/tickets/${ ticketId }/order` } );
}

export function linkTicketOrder(
	ticketId: string,
	orderId: number | null
): Promise< TicketOrderResponse > {
	return apiFetch( {
		path: `${ base }/tickets/${ ticketId }/order`,
		method: 'POST',
		data: { order_id: orderId },
	} );
}

export function fetchOrder( orderId: number ): Promise< OrderSnapshot > {
	return apiFetch( { path: `${ base }/orders/${ orderId }` } );
}

export { apiErrorMessage };
