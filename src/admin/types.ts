export type Screen =
	| 'overview'
	| 'connection'
	| 'tickets'
	| 'widget'
	| 'privacy'
	| 'diagnostics'
	| 'activity';

export type TicketMessage = {
	id: string;
	author: string;
	body: string;
	internal: boolean;
	created_at: string;
};

export type Ticket = {
	id: string;
	remote_id?: string | null;
	status: string;
	category: string;
	subject: string;
	order_id?: number | null;
	order_snapshot?: OrderSnapshot | null;
	customer_user_id: number;
	customer_email: string;
	customer_name: string;
	saas_url?: string | null;
	sync_status: string;
	idempotency_key?: string;
	created_at: string;
	updated_at: string;
	messages: TicketMessage[];
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

export type ConnectionState = {
	status: string;
	mode?: string;
	workspace_id?: string;
	workspace_name: string;
	site_uuid: string;
	saas_url: string;
	scopes?: string[];
	public_key_fingerprint?: string;
	connected_at?: string | null;
	last_sync_at: string | null;
	last_health_at: string | null;
	health: string;
};

export type MockWorkspace = {
	id: string;
	name: string;
};

export type ConnectionStartResponse = {
	mode: string;
	state: string;
	authorize_url: string;
	mock_workspaces?: MockWorkspace[];
	expires_in: number;
	site_url?: string;
};

export type WidgetSettings = {
	enabled: boolean;
	placement: string;
	theme: string;
};

export type PrivacySettings = {
	sync_billing_address: boolean;
	sync_phone: boolean;
	diagnostics_consent: boolean;
	historical_import: string;
	retention_days: number;
};

export type OverviewData = {
	connection: ConnectionState;
	queue_failures: number;
	queue_pending: number;
	hpos_enabled: boolean;
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
	summary: { pending: number; failed: number; cursor: string };
	activity: Array<{ when: string; type: string; event: string; result: string }>;
};

declare global {
	interface Window {
		itsdeskAdmin?: {
			restRoot: string;
			nonce: string;
			version: string;
			saasUrl: string;
			pluginUrl: string;
			connectionMode?: string;
		};
	}
}

export {};
