import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { fetchTickets } from '../api';
import { Slot } from '../extensions';
import { MetricCard } from '../components/MetricCard';
import { IconCart, IconCheck, IconPulse, IconShield, IconTicket, IconWidget } from '../components/Icons';
import type { DeskStats, OverviewData, Screen, Ticket } from '../types';

type Props = {
	data: OverviewData;
	onNavigate: ( screen: Screen ) => void;
	onRefresh: () => void;
};

function age( seconds: number ): string {
	const minutes = Math.floor( seconds / 60 );
	if ( minutes < 1 ) {
		return __( 'just now', 'deskovi' );
	}
	if ( minutes < 60 ) {
		return sprintf( /* translators: %d: minutes */ _n( '%d minute', '%d minutes', minutes, 'deskovi' ), minutes );
	}
	const hours = Math.floor( minutes / 60 );
	if ( hours < 48 ) {
		return sprintf( /* translators: %d: hours */ _n( '%d hour', '%d hours', hours, 'deskovi' ), hours );
	}
	const days = Math.floor( hours / 24 );
	return sprintf( /* translators: %d: days */ _n( '%d day', '%d days', days, 'deskovi' ), days );
}

function statusTone( status: string ): string {
	if ( status === 'open' ) {
		return 'warn';
	}
	if ( status === 'pending' ) {
		return 'info';
	}
	return status === 'resolved' || status === 'closed' ? 'ok' : 'neutral';
}

function statusLabel( status: string ): string {
	switch ( status ) {
		case 'open':
			return __( 'Open', 'deskovi' );
		case 'pending':
			return __( 'Pending', 'deskovi' );
		case 'resolved':
			return __( 'Resolved', 'deskovi' );
		case 'closed':
			return __( 'Closed', 'deskovi' );
		default:
			return status;
	}
}

/** Tickets per day for the last 7 days. */
function WeekChart( { days }: { days: DeskStats[ 'days' ] } ) {
	const max = Math.max( 1, ...days.map( ( d ) => d.count ) );
	return (
		<div className="itsdesk-week" role="img" aria-label={ __( 'New tickets per day, last 7 days', 'deskovi' ) }>
			{ days.map( ( day ) => {
				const label = new Date( day.date + 'T12:00:00Z' ).toLocaleDateString( undefined, { weekday: 'short', timeZone: 'UTC' } );
				return (
					<div className="itsdesk-week__col" key={ day.date } title={ `${ day.date }: ${ day.count }` }>
						<span className="itsdesk-week__n">{ day.count }</span>
						<span className="itsdesk-week__bar">
							<i style={ { height: `${ Math.max( day.count > 0 ? 6 : 2, ( day.count / max ) * 100 ) }%` } } />
						</span>
						<span className="itsdesk-week__d">{ label }</span>
					</div>
				);
			} ) }
		</div>
	);
}

export function OverviewScreen( { data, onNavigate, onRefresh }: Props ) {
	const { widget, privacy, desk } = data;
	const [ recent, setRecent ] = useState< Ticket[] | null >( null );

	useEffect( () => {
		let live = true;
		fetchTickets( { per_page: 6 } )
			.then( ( res ) => live && setRecent( res.tickets ) )
			.catch( () => live && setRecent( [] ) );
		return () => {
			live = false;
		};
	}, [ data ] );

	const waiting = desk ? desk.open + desk.pending : 0;

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2 className="itsdesk-page-title">{ __( 'Overview', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">{ __( 'What needs you today.', 'deskovi' ) }</p>
				</div>
				<div className="itsdesk-admin__spacer" />
				<button type="button" className="itsdesk-btn itsdesk-btn--ghost" onClick={ onRefresh }>
					{ __( 'Refresh', 'deskovi' ) }
				</button>
				<button type="button" className="itsdesk-btn itsdesk-btn--primary" onClick={ () => onNavigate( 'tickets' ) }>
					{ __( 'Open tickets', 'deskovi' ) }
				</button>
			</div>

			<Slot name="overview.top" context={ { navigate: onNavigate } } />

			{ ! widget.enabled && (
				<div className="itsdesk-callout">
					<div>
						<strong>{ __( 'Customers cannot reach you yet', 'deskovi' ) }</strong>
						<p>{ __( 'The chat widget is off. Turn it on so customers can write to you from your store.', 'deskovi' ) }</p>
					</div>
					<button type="button" className="itsdesk-btn itsdesk-btn--secondary" onClick={ () => onNavigate( 'widget' ) }>
						{ __( 'Set up the widget', 'deskovi' ) }
					</button>
				</div>
			) }

			{ desk && (
				<div className="itsdesk-metrics">
					<MetricCard
						label={ __( 'Open', 'deskovi' ) }
						value={ desk.open }
						hint={ desk.oldest_waiting !== null ? sprintf( /* translators: %s: how long */ __( 'oldest waiting %s', 'deskovi' ), age( desk.oldest_waiting ) ) : __( 'nothing waiting', 'deskovi' ) }
						icon={ <IconTicket /> }
						onClick={ () => onNavigate( 'tickets' ) }
					/>
					<MetricCard label={ __( 'Pending', 'deskovi' ) } value={ desk.pending } hint={ __( 'waiting for the customer', 'deskovi' ) } icon={ <IconPulse /> } onClick={ () => onNavigate( 'tickets' ) } />
					<MetricCard
						label={ __( 'Unassigned', 'deskovi' ) }
						value={ desk.unassigned }
						hint={ desk.unassigned > 0 ? __( 'nobody owns these yet', 'deskovi' ) : __( 'everything has an owner', 'deskovi' ) }
						tone={ desk.unassigned > 0 ? 'warn' : 'default' }
						icon={ <IconShield /> }
						onClick={ () => onNavigate( 'tickets' ) }
					/>
					<MetricCard label={ __( 'New, last 7 days', 'deskovi' ) } value={ desk.new_7d } hint={ waiting > 0 ? sprintf( /* translators: %d: tickets */ _n( '%d ticket waiting now', '%d tickets waiting now', waiting, 'deskovi' ), waiting ) : __( 'all caught up', 'deskovi' ) } icon={ <IconWidget /> } />
				</div>
			) }

			<div className="itsdesk-two">
				{ desk && (
					<section className="itsdesk-box">
						<h3>{ __( 'New tickets, last 7 days', 'deskovi' ) }</h3>
						<WeekChart days={ desk.days } />
					</section>
				) }
				<section className="itsdesk-box">
					<div className="itsdesk-box__head">
						<h3>{ __( 'Latest tickets', 'deskovi' ) }</h3>
						<button type="button" className="itsdesk-link" onClick={ () => onNavigate( 'tickets' ) }>
							{ __( 'View all', 'deskovi' ) }
						</button>
					</div>
					{ recent === null && <p className="itsdesk-admin__muted">{ __( 'Loading…', 'deskovi' ) }</p> }
					{ recent !== null && recent.length === 0 && <p className="itsdesk-admin__muted">{ __( 'No tickets yet. They show up here as soon as a customer writes in.', 'deskovi' ) }</p> }
					{ recent !== null && recent.length > 0 && (
						<ul className="itsdesk-list">
							{ recent.map( ( t ) => (
								<li key={ t.id }>
									<button type="button" onClick={ () => onNavigate( 'tickets' ) }>
										<span className="itsdesk-list__main">
											<strong>{ t.subject }</strong>
											<span>{ t.customer_name || t.customer_email }</span>
										</span>
										<span className={ `itsdesk-badge itsdesk-badge--${ statusTone( t.status ) }` }>
											<span className="itsdesk-badge__dot" />
											{ statusLabel( t.status ) }
										</span>
									</button>
								</li>
							) ) }
						</ul>
					) }
				</section>
			</div>

			<section className="itsdesk-box">
				<h3>{ __( 'Setup and status', 'deskovi' ) }</h3>
				<ul className="itsdesk-status-list">
					<li>
						<span className="itsdesk-status-list__icon"><IconWidget size={ 16 } /></span>
						<span className="itsdesk-status-list__text">
							<strong>{ __( 'Support widget', 'deskovi' ) }</strong>
							<span>{ widget.enabled ? `${ widget.placement } · ${ widget.theme }` : __( 'Off: customers cannot start a ticket from your store.', 'deskovi' ) }</span>
						</span>
						<span className={ `itsdesk-badge itsdesk-badge--${ widget.enabled ? 'ok' : 'warn' }` }>
							<span className="itsdesk-badge__dot" />
							{ widget.enabled ? __( 'On', 'deskovi' ) : __( 'Off', 'deskovi' ) }
						</span>
						<button type="button" className="itsdesk-link" onClick={ () => onNavigate( 'widget' ) }>{ __( 'Edit', 'deskovi' ) }</button>
					</li>
					<li>
						<span className="itsdesk-status-list__icon"><IconShield size={ 16 } /></span>
						<span className="itsdesk-status-list__text">
							<strong>{ __( 'Privacy', 'deskovi' ) }</strong>
							<span>{ sprintf( /* translators: %d: days */ __( 'Tickets are kept for %d days.', 'deskovi' ), privacy.retention_days ) }</span>
						</span>
						<span className="itsdesk-badge itsdesk-badge--ok"><span className="itsdesk-badge__dot" />{ __( 'Set', 'deskovi' ) }</span>
						<button type="button" className="itsdesk-link" onClick={ () => onNavigate( 'privacy' ) }>{ __( 'Edit', 'deskovi' ) }</button>
					</li>
					<li>
						<span className="itsdesk-status-list__icon"><IconCart size={ 16 } /></span>
						<span className="itsdesk-status-list__text">
							<strong>{ __( 'Order storage', 'deskovi' ) }</strong>
							<span>{ __( 'Works with WooCommerce high-performance order storage.', 'deskovi' ) }</span>
						</span>
						<span className="itsdesk-badge itsdesk-badge--ok"><span className="itsdesk-badge__dot" /><IconCheck size={ 12 } />{ __( 'Compatible', 'deskovi' ) }</span>
						<button type="button" className="itsdesk-link" onClick={ () => onNavigate( 'diagnostics' ) }>{ __( 'Diagnostics', 'deskovi' ) }</button>
					</li>
				</ul>
				<p className="itsdesk-admin__muted">{ __( 'Everything is stored in your own WordPress database. No outside account is needed.', 'deskovi' ) }</p>
			</section>
		</div>
	);
}
