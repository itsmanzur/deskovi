export type WidgetConfig = {
	restRoot: string;
	nonce: string;
	loggedIn: boolean;
	guestAuthenticated?: boolean;
	guestOrderId?: number;
	loginUrl: string;
	placement: 'bottom-right' | 'bottom-left' | string;
	theme: 'system' | 'light' | 'dark' | string;
	assetUrl: string;
	i18n: Record< string, string >;
};

export type TicketMessage = {
	id: string;
	author: string;
	body: string;
	internal?: boolean;
	created_at: string;
	attachments?: Array< {
		id: string;
		filename: string;
		mime?: string;
		size?: number;
		url: string;
	} >;
};

export type Ticket = {
	id: string;
	status: string;
	category: string;
	subject: string;
	order_id?: number | null;
	unread?: boolean;
	created_at: string;
	updated_at: string;
	messages: TicketMessage[];
};

export type OrderSummary = {
	id: number;
	number: string;
	status: string;
	total: string;
	currency: string;
	date_created: string;
};

export type Category = { id: string; label: string };

declare global {
	interface Window {
		itsdeskWidget?: WidgetConfig;
	}

	// eslint-disable-next-line no-var
	var __webpack_public_path__: string;
}

export {};
