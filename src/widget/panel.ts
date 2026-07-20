import {
	createTicket,
	fetchCategories,
	fetchOrders,
	fetchTicket,
	fetchTickets,
	guestConfirm,
	guestStart,
	isAuthed,
	replyTicket,
} from './api';
import type { Category, OrderSummary, Ticket, WidgetConfig } from './types';
import { panelStyles } from './panel-styles';

type View = 'list' | 'create' | 'thread' | 'welcome' | 'guest';

type PanelState = {
	view: View;
	tickets: Ticket[];
	categories: Category[];
	orders: OrderSummary[];
	selected: Ticket | null;
	busy: boolean;
	error: string;
	subject: string;
	body: string;
	category: string;
	orderId: string;
	reply: string;
	guestOrder: string;
	guestEmail: string;
	guestCode: string;
	guestStep: 'form' | 'code';
};

function t( cfg: WidgetConfig, key: string ): string {
	return cfg.i18n[ key ] || key;
}

function resolveTheme( theme: string ): 'light' | 'dark' {
	if ( theme === 'dark' || theme === 'light' ) {
		return theme;
	}
	if (
		typeof window.matchMedia === 'function' &&
		window.matchMedia( '(prefers-color-scheme: dark)' ).matches
	) {
		return 'dark';
	}
	return 'light';
}

function formatWhen( value: string ): string {
	if ( ! value ) {
		return '';
	}
	const d = new Date( value );
	if ( Number.isNaN( d.getTime() ) ) {
		return value;
	}
	return d.toLocaleString( undefined, {
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );
}

function esc( s: string ): string {
	return s
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

/**
 * Mount conversation UI into an existing shadow root.
 */
export function mountPanel(
	host: HTMLElement,
	shadow: ShadowRoot,
	cfg: WidgetConfig,
	onClose: () => void
): void {
	const panel = document.createElement( 'div' );
	panel.className =
		'dk-panel ' + ( cfg.placement === 'bottom-left' ? 'is-left' : 'is-right' );
	panel.setAttribute( 'role', 'dialog' );
	panel.setAttribute( 'aria-label', t( cfg, 'support' ) );

	const state: PanelState = {
		view: isAuthed( cfg ) ? 'list' : 'welcome',
		tickets: [],
		categories: [],
		orders: [],
		selected: null,
		busy: false,
		error: '',
		subject: '',
		body: '',
		category: 'order',
		orderId: cfg.guestOrderId ? String( cfg.guestOrderId ) : '',
		reply: '',
		guestOrder: cfg.guestOrderId ? String( cfg.guestOrderId ) : '',
		guestEmail: '',
		guestCode: '',
		guestStep: 'form',
	};

	const styleEl = document.createElement( 'style' );
	styleEl.textContent = panelStyles;
	if ( ! shadow.querySelector( 'style[data-dk-panel]' ) ) {
		styleEl.setAttribute( 'data-dk-panel', '1' );
		shadow.appendChild( styleEl );
	}

	shadow.appendChild( panel );

	const withBusy = async ( fn: () => Promise< void > ) => {
		state.busy = true;
		state.error = '';
		render();
		try {
			await fn();
		} catch ( e ) {
			state.error = e instanceof Error ? e.message : t( cfg, 'error' );
		} finally {
			state.busy = false;
			render();
		}
	};

	const loadList = () =>
		withBusy( async () => {
			const res = await fetchTickets();
			state.tickets = res.tickets || [];
			state.view = 'list';
			state.selected = null;
		} );

	const openCreate = () =>
		withBusy( async () => {
			const [ cats, orders ] = await Promise.all( [
				fetchCategories(),
				fetchOrders(),
			] );
			state.categories = cats.categories || [];
			state.orders = orders.orders || [];
			if ( state.categories[ 0 ] ) {
				state.category = state.categories[ 0 ].id;
			}
			if ( ! state.orderId && state.orders[ 0 ] ) {
				state.orderId = String( state.orders[ 0 ].id );
			}
			state.view = 'create';
			state.subject = '';
			state.body = '';
		} );

	const openThread = ( id: string ) =>
		withBusy( async () => {
			const ticket = await fetchTicket( id );
			state.selected = ticket;
			state.view = 'thread';
			state.reply = '';
		} );

	const submitCreate = () =>
		withBusy( async () => {
			if ( ! state.subject.trim() || ! state.body.trim() ) {
				state.error = t( cfg, 'error' );
				return;
			}
			const payload: {
				subject: string;
				body: string;
				category: string;
				order_id?: number;
			} = {
				subject: state.subject.trim(),
				body: state.body.trim(),
				category: state.category,
			};
			if ( state.orderId ) {
				payload.order_id = parseInt( state.orderId, 10 );
			}
			const ticket = await createTicket( payload );
			state.selected = ticket;
			state.view = 'thread';
		} );

	const submitReply = () =>
		withBusy( async () => {
			if ( ! state.selected || ! state.reply.trim() ) {
				return;
			}
			const ticket = await replyTicket(
				state.selected.id,
				state.reply.trim()
			);
			state.selected = ticket;
			state.reply = '';
		} );

	const sendGuestCode = () =>
		withBusy( async () => {
			const orderId = parseInt( state.guestOrder, 10 );
			await guestStart( orderId, state.guestEmail.trim() );
			state.guestStep = 'code';
			state.error = '';
		} );

	const confirmGuest = () =>
		withBusy( async () => {
			const orderId = parseInt( state.guestOrder, 10 );
			await guestConfirm(
				orderId,
				state.guestEmail.trim(),
				state.guestCode.trim()
			);
			cfg.guestAuthenticated = true;
			cfg.guestOrderId = orderId;
			state.orderId = String( orderId );
			await loadList();
		} );

	function renderHead( title: string, showBack: boolean ): string {
		return `
			<div class="dk-head">
				<div class="dk-row">
					${
						showBack
							? `<button type="button" class="dk-icon-btn" data-act="back" aria-label="${ esc(
									t( cfg, 'back' )
							  ) }">←</button>`
							: ''
					}
					<h2>${ esc( title ) }</h2>
				</div>
				<button type="button" class="dk-icon-btn" data-act="close" aria-label="${ esc(
					t( cfg, 'close' )
				) }">×</button>
			</div>
		`;
	}

	function renderWelcome(): string {
		return `
			${ renderHead( t( cfg, 'support' ), false ) }
			<div class="dk-body">
				<p><strong>${ esc( t( cfg, 'loginTitle' ) ) }</strong></p>
				<p class="dk-muted">${ esc( t( cfg, 'loginBody' ) ) }</p>
				${ state.error ? `<p class="dk-error">${ esc( state.error ) }</p>` : '' }
			</div>
			<div class="dk-foot">
				<a class="dk-btn dk-btn-primary" href="${ esc( cfg.loginUrl ) }">${ esc(
					t( cfg, 'loginAction' )
				) }</a>
				<button type="button" class="dk-btn dk-btn-secondary" data-act="guest">
					${ esc( t( cfg, 'guestAction' ) ) }
				</button>
			</div>
		`;
	}

	function renderGuest(): string {
		if ( state.guestStep === 'code' ) {
			return `
				${ renderHead( t( cfg, 'guestAction' ), true ) }
				<div class="dk-body">
					<p class="dk-muted">${ esc( t( cfg, 'guestSent' ) ) }</p>
					${ state.error ? `<p class="dk-error">${ esc( state.error ) }</p>` : '' }
					<div class="dk-field">
						<label>${ esc( t( cfg, 'guestCode' ) ) }</label>
						<input type="text" inputmode="numeric" data-field="guestCode" value="${ esc(
							state.guestCode
						) }" />
					</div>
				</div>
				<div class="dk-foot">
					<button type="button" class="dk-btn dk-btn-primary" data-act="guest-confirm" ${
						state.busy ? 'disabled' : ''
					}>${ esc( t( cfg, 'guestConfirm' ) ) }</button>
				</div>
			`;
		}
		return `
			${ renderHead( t( cfg, 'guestAction' ), true ) }
			<div class="dk-body">
				${ state.error ? `<p class="dk-error">${ esc( state.error ) }</p>` : '' }
				<div class="dk-field">
					<label>${ esc( t( cfg, 'guestOrder' ) ) }</label>
					<input type="text" data-field="guestOrder" value="${ esc( state.guestOrder ) }" />
				</div>
				<div class="dk-field">
					<label>${ esc( t( cfg, 'guestEmail' ) ) }</label>
					<input type="email" data-field="guestEmail" value="${ esc( state.guestEmail ) }" />
				</div>
			</div>
			<div class="dk-foot">
				<button type="button" class="dk-btn dk-btn-primary" data-act="guest-send" ${
					state.busy ? 'disabled' : ''
				}>${ esc( t( cfg, 'guestSendCode' ) ) }</button>
			</div>
		`;
	}

	function renderList(): string {
		const rows =
			state.tickets.length === 0
				? `<p class="dk-muted">${ esc( t( cfg, 'empty' ) ) }</p>`
				: state.tickets
						.map(
							( ticket ) => `
					<button type="button" class="dk-ticket" data-act="open" data-id="${ esc(
						ticket.id
					) }">
						<strong>${ esc( ticket.subject ) }${
								ticket.unread ? ` · ${ esc( t( cfg, 'unread' ) ) }` : ''
						}</strong>
						<span>${ esc( ticket.status ) } · ${ esc( formatWhen( ticket.updated_at ) ) }</span>
					</button>`
						)
						.join( '' );

		return `
			${ renderHead( t( cfg, 'tickets' ), false ) }
			<div class="dk-body">
				${ state.error ? `<p class="dk-error">${ esc( state.error ) }</p>` : '' }
				${ state.busy ? `<p class="dk-muted">${ esc( t( cfg, 'loading' ) ) }</p>` : rows }
			</div>
			<div class="dk-foot">
				<button type="button" class="dk-btn dk-btn-primary" data-act="create" ${
					state.busy ? 'disabled' : ''
				}>${ esc( t( cfg, 'newTicket' ) ) }</button>
			</div>
		`;
	}

	function renderCreate(): string {
		const catOpts = state.categories
			.map(
				( c ) =>
					`<option value="${ esc( c.id ) }" ${
						c.id === state.category ? 'selected' : ''
					}>${ esc( c.label ) }</option>`
			)
			.join( '' );
		const orderOpts =
			`<option value="">${ esc( t( cfg, 'orderNone' ) ) }</option>` +
			state.orders
				.map(
					( o ) =>
						`<option value="${ o.id }" ${
							String( o.id ) === state.orderId ? 'selected' : ''
						}>#${ esc( o.number ) } · ${ esc( o.status ) } · ${ esc(
							o.currency
						) } ${ esc( o.total ) }</option>`
				)
				.join( '' );

		return `
			${ renderHead( t( cfg, 'newTicket' ), true ) }
			<div class="dk-body">
				${ state.error ? `<p class="dk-error">${ esc( state.error ) }</p>` : '' }
				<div class="dk-field">
					<label>${ esc( t( cfg, 'subject' ) ) }</label>
					<input type="text" data-field="subject" value="${ esc( state.subject ) }" />
				</div>
				<div class="dk-field">
					<label>${ esc( t( cfg, 'category' ) ) }</label>
					<select data-field="category">${ catOpts }</select>
				</div>
				<div class="dk-field">
					<label>${ esc( t( cfg, 'order' ) ) }</label>
					<select data-field="orderId">${ orderOpts }</select>
				</div>
				<div class="dk-field">
					<label>${ esc( t( cfg, 'message' ) ) }</label>
					<textarea data-field="body">${ esc( state.body ) }</textarea>
				</div>
			</div>
			<div class="dk-foot">
				<button type="button" class="dk-btn dk-btn-primary" data-act="submit-create" ${
					state.busy ? 'disabled' : ''
				}>${ esc( t( cfg, 'create' ) ) }</button>
			</div>
		`;
	}

	function renderThread(): string {
		const ticket = state.selected;
		if ( ! ticket ) {
			return renderList();
		}
		const bubbles = ( ticket.messages || [] )
			.map( ( m ) => {
				const files = ( m.attachments || [] )
					.map(
						( a ) =>
							`<div class="dk-attach"><a href="${ esc(
								a.url
							) }" target="_blank" rel="noopener">${ esc(
								a.filename
							) }</a></div>`
					)
					.join( '' );
				return `
				<div class="dk-bubble ${ m.author === 'agent' ? 'is-agent' : '' }">
					<div class="dk-bubble__meta">${ esc( m.author ) } · ${ esc(
					formatWhen( m.created_at )
				) }</div>
					<p>${ esc( m.body ) }</p>
					${ files }
				</div>`;
			} )
			.join( '' );

		return `
			${ renderHead( ticket.subject, true ) }
			<div class="dk-body">
				${ state.error ? `<p class="dk-error">${ esc( state.error ) }</p>` : '' }
				<p class="dk-muted">${ esc( ticket.status ) }${
					ticket.order_id ? ` · Order #${ ticket.order_id }` : ''
				}</p>
				${ bubbles || `<p class="dk-muted">${ esc( t( cfg, 'loading' ) ) }</p>` }
			</div>
			<div class="dk-foot">
				<textarea data-field="reply" placeholder="${ esc( t( cfg, 'reply' ) ) }" rows="2">${ esc(
					state.reply
				) }</textarea>
				<button type="button" class="dk-btn dk-btn-primary" data-act="submit-reply" ${
					state.busy ? 'disabled' : ''
				}>${ esc( t( cfg, 'send' ) ) }</button>
			</div>
		`;
	}

	function render(): void {
		if ( state.view === 'welcome' ) {
			panel.innerHTML = renderWelcome();
		} else if ( state.view === 'guest' ) {
			panel.innerHTML = renderGuest();
		} else if ( state.view === 'create' ) {
			panel.innerHTML = renderCreate();
		} else if ( state.view === 'thread' ) {
			panel.innerHTML = renderThread();
		} else {
			panel.innerHTML = renderList();
		}
	}

	panel.addEventListener( 'click', ( ev ) => {
		const target = ev.target as HTMLElement | null;
		if ( ! target ) {
			return;
		}
		const actEl = target.closest( '[data-act]' ) as HTMLElement | null;
		if ( ! actEl ) {
			return;
		}
		const act = actEl.getAttribute( 'data-act' );
		if ( act === 'close' ) {
			panel.remove();
			onClose();
			return;
		}
		if ( act === 'back' ) {
			if ( state.view === 'guest' && state.guestStep === 'code' ) {
				state.guestStep = 'form';
				render();
				return;
			}
			if ( state.view === 'guest' ) {
				state.view = 'welcome';
				render();
				return;
			}
			if ( state.view === 'thread' || state.view === 'create' ) {
				void loadList();
			}
			return;
		}
		if ( act === 'guest' ) {
			state.view = 'guest';
			state.guestStep = 'form';
			render();
			return;
		}
		if ( act === 'guest-send' ) {
			void sendGuestCode();
			return;
		}
		if ( act === 'guest-confirm' ) {
			void confirmGuest();
			return;
		}
		if ( act === 'create' ) {
			void openCreate();
			return;
		}
		if ( act === 'open' ) {
			const id = actEl.getAttribute( 'data-id' );
			if ( id ) {
				void openThread( id );
			}
			return;
		}
		if ( act === 'submit-create' ) {
			void submitCreate();
			return;
		}
		if ( act === 'submit-reply' ) {
			void submitReply();
		}
	} );

	panel.addEventListener( 'input', ( ev ) => {
		const target = ev.target as
			| HTMLInputElement
			| HTMLTextAreaElement
			| HTMLSelectElement
			| null;
		if ( ! target ) {
			return;
		}
		const field = target.getAttribute( 'data-field' );
		if ( ! field ) {
			return;
		}
		const map: Record< string, keyof PanelState > = {
			subject: 'subject',
			body: 'body',
			category: 'category',
			orderId: 'orderId',
			reply: 'reply',
			guestOrder: 'guestOrder',
			guestEmail: 'guestEmail',
			guestCode: 'guestCode',
		};
		const key = map[ field ];
		if ( key ) {
			( state as unknown as Record< string, string > )[ key ] = target.value;
		}
	} );

	panel.addEventListener( 'change', ( ev ) => {
		const target = ev.target as HTMLSelectElement | null;
		if ( ! target ) {
			return;
		}
		const field = target.getAttribute( 'data-field' );
		if ( field === 'category' ) {
			state.category = target.value;
		} else if ( field === 'orderId' ) {
			state.orderId = target.value;
		}
	} );

	host.setAttribute( 'data-theme', resolveTheme( cfg.theme ) );
	render();
	if ( isAuthed( cfg ) ) {
		void loadList();
	}
}

export { resolveTheme };
