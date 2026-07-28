import type { WidgetConfig } from './types';
import { fetchUnread, isAuthed } from './api';

declare const __webpack_public_path__: string;

function getConfig(): WidgetConfig | null {
	return window.itsdeskWidget || null;
}

function applyPublicPath( cfg: WidgetConfig ): void {
	if ( cfg.assetUrl ) {
		// eslint-disable-next-line camelcase
		__webpack_public_path__ = cfg.assetUrl.endsWith( '/' )
			? cfg.assetUrl
			: cfg.assetUrl + '/';
	}
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

const FAB_CSS = `
:host{all:initial;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
.dk-fab{position:fixed;z-index:99999;bottom:20px;min-width:52px;max-width:min(240px, calc(100vw - 40px));height:52px;padding:0 14px;border-radius:999px;border:none;cursor:pointer;background:#0f766e;color:#fff;box-shadow:0 8px 24px rgba(16,42,51,.22);display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:700}
.dk-fab:hover{background:#0d5f59}
.dk-fab.is-left{left:20px;right:auto}
.dk-fab.is-right{right:20px;left:auto}
.dk-fab.is-icon-only{width:52px;min-width:52px;max-width:52px;padding:0;border-radius:50%}
.dk-fab-icon{display:flex;flex-shrink:0}
.dk-fab-icon svg{display:block}
.dk-fab-label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.dk-badge{min-width:18px;flex-shrink:0;height:18px;padding:0 5px;border-radius:999px;background:#b42318;color:#fff;font-size:11px;line-height:18px;font-weight:700}
:host([data-theme="dark"]) .dk-fab{background:#2dd4bf;color:#102a33}
`;

const FAB_ICON =
	'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">' +
	'<path d="M4 7.5C4 5.567 5.567 4 7.5 4H16.5C18.433 4 20 5.567 20 7.5V13.5C20 15.433 18.433 17 16.5 17H11L7 20V17H7.5C5.567 17 4 15.433 4 13.5V7.5Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>' +
	'<path d="M8 9.5H16M8 12.5H13" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>' +
	'</svg>';

class DeskoviSupport extends HTMLElement {
	#shadow: ShadowRoot;
	#open = false;
	#loading = false;
	#cfg: WidgetConfig | null = null;
	#unread = 0;
	#poll: ReturnType< typeof setInterval > | null = null;
	#btn: HTMLButtonElement | null = null;
	#badge: HTMLSpanElement | null = null;

	constructor() {
		super();
		this.#shadow = this.attachShadow( { mode: 'open' } );
	}

	connectedCallback(): void {
		this.#cfg = getConfig();
		if ( ! this.#cfg ) {
			return;
		}
		applyPublicPath( this.#cfg );
		this.setAttribute( 'data-theme', resolveTheme( this.#cfg.theme ) );
		this.renderFab();
		this.onKey = this.onKey.bind( this );
		document.addEventListener( 'keydown', this.onKey );
		if ( isAuthed( this.#cfg ) ) {
			void this.refreshUnread();
		}
	}

	disconnectedCallback(): void {
		document.removeEventListener( 'keydown', this.onKey );
		this.stopPoll();
	}

	onKey( ev: KeyboardEvent ): void {
		if ( ev.key === 'Escape' && this.#open ) {
			this.closePanel();
		}
	}

	renderFab(): void {
		const cfg = this.#cfg;
		if ( ! cfg ) {
			return;
		}
		this.#shadow.innerHTML = '';
		const style = document.createElement( 'style' );
		style.textContent = FAB_CSS;
		this.#shadow.appendChild( style );

		const label = ( cfg.launcherLabel || '' ).trim();

		const btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className =
			'dk-fab ' +
			( cfg.placement === 'bottom-left' ? 'is-left' : 'is-right' ) +
			( label ? '' : ' is-icon-only' );
		btn.setAttribute( 'aria-label', label || cfg.i18n.support || 'Support' );
		btn.setAttribute( 'aria-expanded', 'false' );
		const icon = document.createElement( 'span' );
		icon.className = 'dk-fab-icon';
		icon.innerHTML = FAB_ICON;
		btn.append( icon );
		if ( label ) {
			const labelEl = document.createElement( 'span' );
			labelEl.className = 'dk-fab-label';
			labelEl.textContent = label;
			btn.append( labelEl );
		}
		btn.addEventListener( 'click', () => {
			void this.toggle();
		} );
		this.#btn = btn;
		this.#shadow.appendChild( btn );
		this.renderBadge();
	}

	renderBadge(): void {
		if ( ! this.#btn ) {
			return;
		}
		this.#badge?.remove();
		this.#badge = null;
		if ( this.#unread <= 0 ) {
			return;
		}
		const badge = document.createElement( 'span' );
		badge.className = 'dk-badge';
		badge.textContent = this.#unread > 9 ? '9+' : String( this.#unread );
		badge.setAttribute( 'aria-label', String( this.#unread ) );
		this.#btn.appendChild( badge );
		this.#badge = badge;
	}

	async refreshUnread(): Promise< void > {
		if ( ! this.#cfg || ! isAuthed( this.#cfg ) ) {
			return;
		}
		try {
			const res = await fetchUnread();
			this.#unread = res.count || 0;
			this.renderBadge();
		} catch {
			// ignore poll errors
		}
	}

	startPoll(): void {
		this.stopPoll();
		this.#poll = setInterval( () => {
			void this.refreshUnread();
		}, 30000 );
	}

	stopPoll(): void {
		if ( this.#poll ) {
			clearInterval( this.#poll );
			this.#poll = null;
		}
	}

	closePanel(): void {
		const panel = this.#shadow.querySelector( '.dk-panel' );
		panel?.remove();
		this.#open = false;
		this.#btn?.setAttribute( 'aria-expanded', 'false' );
		this.stopPoll();
		void this.refreshUnread();
	}

	async toggle(): Promise< void > {
		if ( this.#open ) {
			this.closePanel();
			return;
		}
		if ( this.#loading || ! this.#cfg ) {
			return;
		}
		this.#loading = true;
		try {
			applyPublicPath( this.#cfg );
			const mod = await import( /* webpackChunkName: "panel" */ './panel' );
			mod.mountPanel( this, this.#shadow, this.#cfg, () => {
				this.#open = false;
				this.#btn?.setAttribute( 'aria-expanded', 'false' );
				this.stopPoll();
				void this.refreshUnread();
			} );
			this.#open = true;
			this.#btn?.setAttribute( 'aria-expanded', 'true' );
			if ( isAuthed( this.#cfg ) ) {
				this.startPoll();
				void this.refreshUnread();
			}
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.error( '[Deskovi] failed to load support panel', e );
		} finally {
			this.#loading = false;
		}
	}
}

function boot(): void {
	const cfg = getConfig();
	if ( ! cfg ) {
		return;
	}
	applyPublicPath( cfg );

	if ( ! customElements.get( 'deskovi-support' ) ) {
		customElements.define( 'deskovi-support', DeskoviSupport );
	}

	if ( ! document.querySelector( 'deskovi-support' ) ) {
		document.body.appendChild( document.createElement( 'deskovi-support' ) );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
