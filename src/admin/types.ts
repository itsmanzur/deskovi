export type Screen =
	| 'overview'
	| 'tickets'
	| 'widget'
	| 'privacy'
	| 'notifications'
	| 'diagnostics'
	| 'activity';

export type TicketAttachment = {
	id: string;
	message_id?: string | null;
	filename: string;
	mime_type: string;
	size_bytes: number;
	uploaded_by: string;
	created_at: string;
};

export type TicketMessage = {
	id: string;
	author: string;
	body: string;
	internal: boolean;
	created_at: string;
	attachments?: TicketAttachment[];
};

export type Ticket = {
	id: string;
	status: string;
	category: string;
	subject: string;
	order_id?: number | null;
	order_snapshot?: OrderSnapshot | null;
	customer_user_id: number;
	customer_email: string;
	customer_name: string;
	idempotency_key?: string;
	assigned_agent_id?: number | null;
	assigned_agent_name?: string | null;
	created_at: string;
	updated_at: string;
	messages: TicketMessage[];
	attachments?: TicketAttachment[];
};

export type Agent = {
	id: number;
	name: string;
	avatar_url?: string;
};

export type OrderTimelineEntry = {
	at: string;
	type: string;
	message: string;
};

export type OrderAddress = {
	first_name?: string;
	last_name?: string;
	company?: string;
	address_1?: string;
	address_2?: string;
	city?: string;
	state?: string;
	postcode?: string;
	country?: string;
	email?: string;
};

export type OrderSnapshot = {
	id: number;
	number: string;
	status: string;
	date_created: string;
	currency: string;
	total: string;
	payment_method_title?: string;
	shipping_method?: string;
	items: Array< { name: string; quantity: number; sku?: string } >;
	billing?: OrderAddress | null;
	shipping?: OrderAddress | null;
	phone?: string | null;
	timeline?: OrderTimelineEntry[];
};

export type TicketOrderResponse = {
	linked: boolean;
	order_id?: number | null;
	order: OrderSnapshot | null;
	error?: string;
	ticket?: Ticket;
};

export type TicketCategory = {
	id: string;
	label: string;
};

export type WidgetSettings = {
	enabled: boolean;
	placement: string;
	theme: string;
	launcher_label: string;
};

export type NotificationSettings = {
	agent_new_ticket: boolean;
	agent_new_reply: boolean;
	customer_reply: boolean;
	agent_assigned: boolean;
};

export type PrivacySettings = {
	sync_billing_address: boolean;
	sync_phone: boolean;
	diagnostics_consent: boolean;
	historical_import: string;
	retention_days: number;
};

export type OverviewData = {
	hpos_enabled?: boolean;
	widget: WidgetSettings;
	privacy: PrivacySettings;
};

export type DiagnosticsData = {
	wordpress: string;
	woocommerce: string;
	php: string;
	plugin: string;
	theme: string;
	checks: Array<{ name: string; value: string; status: string }>;
	errors: Array<{ time?: string; code?: string; message?: string }>;
};

export type ActivityData = {
	activity: Array<{ when: string; type: string; event: string; result: string }>;
};

declare global {
	interface Window {
		itsdeskAdmin?: {
			restRoot: string;
			nonce: string;
			version: string;
			pluginUrl: string;
			currentUserId: number;
		};
	}
}

export {};
