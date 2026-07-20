import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	apiErrorMessage,
	createTicket,
	fetchTicket,
	fetchTicketCategories,
	fetchTicketOrder,
	fetchTickets,
	linkTicketOrder,
	replyTicket,
	updateTicketStatus,
} from '../api';
import { SkeletonPanel } from '../components/Skeleton';
import type { OrderSnapshot, Ticket, TicketCategory } from '../types';

type Props = {
	onToast: ( message: string, tone?: 'ok' | 'danger' ) => void;
};

function statusTone( status: string ): string {
	if ( status === 'open' ) {
		return 'warn';
	}
	if ( status === 'pending' ) {
		return 'info';
	}
	if ( status === 'resolved' || status === 'closed' ) {
		return 'ok';
	}
	return 'neutral';
}

function syncTone( sync: string ): string {
	if ( sync === 'synced' ) {
		return 'ok';
	}
	if ( sync === 'failed' ) {
		return 'danger';
	}
	return 'warn';
}

function formatWhen( value: string ): string {
	if ( ! value ) {
		return '—';
	}
	const date = new Date( value );
	if ( Number.isNaN( date.getTime() ) ) {
		return value;
	}
	return date.toLocaleString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );
}

function labelStatus( value: string ): string {
	return value ? value.charAt( 0 ).toUpperCase() + value.slice( 1 ) : value;
}

export function TicketsScreen( { onToast }: Props ) {
	const [ tickets, setTickets ] = useState< Ticket[] >( [] );
	const [ categories, setCategories ] = useState< TicketCategory[] >( [] );
	const [ selectedId, setSelectedId ] = useState< string | null >( null );
	const [ selected, setSelected ] = useState< Ticket | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ showCreate, setShowCreate ] = useState( false );

	const [ subject, setSubject ] = useState( '' );
	const [ body, setBody ] = useState( '' );
	const [ category, setCategory ] = useState( 'order' );
	const [ orderId, setOrderId ] = useState( '' );
	const [ replyBody, setReplyBody ] = useState( '' );
	const [ replyInternal, setReplyInternal ] = useState( false );
	const [ orderContext, setOrderContext ] = useState< OrderSnapshot | null >( null );
	const [ orderLoading, setOrderLoading ] = useState( false );
	const [ linkOrderId, setLinkOrderId ] = useState( '' );
	const [ showTimeline, setShowTimeline ] = useState( false );

	const loadList = useCallback( () => {
		setLoading( true );
		Promise.all( [ fetchTickets(), fetchTicketCategories() ] )
			.then( ( [ ticketRes, catRes ] ) => {
				setTickets( ticketRes.tickets || [] );
				setCategories( catRes.categories || [] );
				if ( catRes.categories?.[ 0 ] && ! category ) {
					setCategory( catRes.categories[ 0 ].id );
				}
				setLoading( false );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setLoading( false );
			} );
	}, [ category, onToast ] );

	useEffect( () => {
		loadList();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const openTicket = ( id: string ) => {
		setSelectedId( id );
		setShowCreate( false );
		setBusy( true );
		setOrderContext( null );
		setShowTimeline( false );
		setLinkOrderId( '' );
		Promise.all( [ fetchTicket( id ), fetchTicketOrder( id ) ] )
			.then( ( [ ticket, orderRes ] ) => {
				setSelected( ticket );
				setOrderContext( orderRes.order || null );
				setLinkOrderId(
					orderRes.order_id ? String( orderRes.order_id ) : ''
				);
				setBusy( false );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setBusy( false );
			} );
	};

	const onLinkOrder = () => {
		if ( ! selected ) {
			return;
		}
		const parsed = linkOrderId.trim()
			? parseInt( linkOrderId.trim(), 10 )
			: null;
		if ( linkOrderId.trim() && ( ! parsed || Number.isNaN( parsed ) ) ) {
			onToast( __( 'Enter a valid order ID.', 'deskovi' ), 'danger' );
			return;
		}
		setOrderLoading( true );
		linkTicketOrder( selected.id, parsed )
			.then( ( res ) => {
				if ( res.ticket ) {
					setSelected( res.ticket );
				}
				setOrderContext( res.order || null );
				setLinkOrderId( res.order_id ? String( res.order_id ) : '' );
				setOrderLoading( false );
				onToast(
					res.linked
						? __( 'Order linked.', 'deskovi' )
						: __( 'Order unlinked.', 'deskovi' )
				);
				loadList();
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setOrderLoading( false );
			} );
	};

	const onUnlinkOrder = () => {
		if ( ! selected ) {
			return;
		}
		setOrderLoading( true );
		linkTicketOrder( selected.id, null )
			.then( ( res ) => {
				if ( res.ticket ) {
					setSelected( res.ticket );
				}
				setOrderContext( null );
				setLinkOrderId( '' );
				setOrderLoading( false );
				onToast( __( 'Order unlinked.', 'deskovi' ) );
				loadList();
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setOrderLoading( false );
			} );
	};

	const onCreate = () => {
		setBusy( true );
		createTicket( {
			subject,
			body,
			category,
			order_id: orderId ? parseInt( orderId, 10 ) : undefined,
		} )
			.then( ( ticket ) => {
				onToast( __( 'Ticket created.', 'deskovi' ) );
				setSubject( '' );
				setBody( '' );
				setOrderId( '' );
				setShowCreate( false );
				setBusy( false );
				loadList();
				openTicket( ticket.id );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setBusy( false );
			} );
	};

	const onReply = () => {
		if ( ! selected ) {
			return;
		}
		setBusy( true );
		replyTicket( selected.id, { body: replyBody, internal: replyInternal } )
			.then( ( ticket ) => {
				setSelected( ticket );
				setReplyBody( '' );
				setReplyInternal( false );
				setBusy( false );
				onToast( __( 'Reply sent.', 'deskovi' ) );
				loadList();
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setBusy( false );
			} );
	};

	const onStatus = ( status: string ) => {
		if ( ! selected ) {
			return;
		}
		setBusy( true );
		updateTicketStatus( selected.id, status )
			.then( ( ticket ) => {
				setSelected( ticket );
				setBusy( false );
				onToast( __( 'Status updated.', 'deskovi' ) );
				loadList();
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setBusy( false );
			} );
	};

	if ( loading && tickets.length === 0 ) {
		return <SkeletonPanel />;
	}

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2>{ __( 'Tickets', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __(
							'Ticket bridge cache — conversations sync outbound to Deskovi SaaS (mock). Customer REST is available for logged-in shoppers.',
							'deskovi'
						) }
					</p>
				</div>
				<div className="itsdesk-admin__spacer" />
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--secondary"
					onClick={ loadList }
					disabled={ busy }
				>
					{ __( 'Refresh', 'deskovi' ) }
				</button>
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--primary"
					onClick={ () => {
						setShowCreate( true );
						setSelectedId( null );
						setSelected( null );
					} }
				>
					{ __( 'New ticket', 'deskovi' ) }
				</button>
			</div>

			<div className="itsdesk-tickets">
				<aside className="itsdesk-tickets__list">
					{ tickets.length === 0 ? (
						<p className="itsdesk-admin__muted">
							{ __( 'No tickets yet. Create one to test the bridge.', 'deskovi' ) }
						</p>
					) : (
						tickets.map( ( t ) => (
							<button
								key={ t.id }
								type="button"
								className={
									'itsdesk-ticket-row' +
									( selectedId === t.id ? ' is-active' : '' )
								}
								onClick={ () => openTicket( t.id ) }
							>
								<div className="itsdesk-ticket-row__top">
									<strong>{ t.subject }</strong>
									<span
										className={
											'itsdesk-badge itsdesk-badge--' +
											statusTone( t.status )
										}
									>
										<span className="itsdesk-badge__dot" />
										{ labelStatus( t.status ) }
									</span>
								</div>
								<div className="itsdesk-ticket-row__meta">
									<span>{ t.customer_name || t.customer_email || '—' }</span>
									<span
										className={
											'itsdesk-badge itsdesk-badge--' +
											syncTone( t.sync_status )
										}
									>
										<span className="itsdesk-badge__dot" />
										{ labelStatus( t.sync_status ) }
									</span>
								</div>
							</button>
						) )
					) }
				</aside>

				<section className="itsdesk-tickets__detail">
					{ showCreate && (
						<div className="itsdesk-card">
							<div className="itsdesk-card__head">
								<h3>{ __( 'Create ticket', 'deskovi' ) }</h3>
							</div>
							<div className="itsdesk-field">
								<label htmlFor="itsdesk-tkt-subject">
									{ __( 'Subject', 'deskovi' ) }
								</label>
								<input
									id="itsdesk-tkt-subject"
									className="itsdesk-input"
									value={ subject }
									onChange={ ( e ) =>
										setSubject( ( e.target as HTMLInputElement ).value )
									}
								/>
							</div>
							<div className="itsdesk-field">
								<label htmlFor="itsdesk-tkt-category">
									{ __( 'Category', 'deskovi' ) }
								</label>
								<select
									id="itsdesk-tkt-category"
									className="itsdesk-select"
									value={ category }
									onChange={ ( e ) =>
										setCategory( ( e.target as HTMLSelectElement ).value )
									}
								>
									{ categories.map( ( c ) => (
										<option key={ c.id } value={ c.id }>
											{ c.label }
										</option>
									) ) }
								</select>
							</div>
							<div className="itsdesk-field">
								<label htmlFor="itsdesk-tkt-order">
									{ __( 'Order ID (optional)', 'deskovi' ) }
								</label>
								<input
									id="itsdesk-tkt-order"
									className="itsdesk-input"
									value={ orderId }
									onChange={ ( e ) =>
										setOrderId( ( e.target as HTMLInputElement ).value )
									}
									placeholder="1234"
								/>
							</div>
							<div className="itsdesk-field">
								<label htmlFor="itsdesk-tkt-body">
									{ __( 'Message', 'deskovi' ) }
								</label>
								<textarea
									id="itsdesk-tkt-body"
									className="itsdesk-textarea"
									rows={ 5 }
									value={ body }
									onChange={ ( e ) =>
										setBody( ( e.target as HTMLTextAreaElement ).value )
									}
								/>
							</div>
							<div className="itsdesk-admin__actions">
								<button
									type="button"
									className="itsdesk-btn itsdesk-btn--primary"
									disabled={ busy || ! subject || ! body }
									onClick={ onCreate }
								>
									{ busy
										? __( 'Creating…', 'deskovi' )
										: __( 'Create ticket', 'deskovi' ) }
								</button>
								<button
									type="button"
									className="itsdesk-btn itsdesk-btn--ghost"
									onClick={ () => setShowCreate( false ) }
								>
									{ __( 'Cancel', 'deskovi' ) }
								</button>
							</div>
						</div>
					) }

					{ ! showCreate && ! selected && (
						<div className="itsdesk-tickets__empty">
							<p className="itsdesk-admin__muted">
								{ __(
									'Select a ticket or create a new one to exercise the bridge.',
									'deskovi'
								) }
							</p>
						</div>
					) }

					{ ! showCreate && selected && (
						<div className="itsdesk-card">
							<div className="itsdesk-card__head">
								<h3 className="itsdesk-card__title">{ selected.subject }</h3>
								<span
									className={
										'itsdesk-badge itsdesk-badge--' +
										statusTone( selected.status )
									}
								>
									<span className="itsdesk-badge__dot" />
									{ labelStatus( selected.status ) }
								</span>
							</div>
							<p className="itsdesk-admin__muted">
								{ selected.customer_name } · { selected.customer_email }
								{ selected.order_id
									? ` · Order #${ selected.order_id }`
									: '' }
								{ ' · ' }
								{ labelStatus( selected.category ) }
							</p>
							<p className="itsdesk-admin__muted">
								{ __( 'Bridge sync', 'deskovi' ) }:{ ' ' }
								{ labelStatus( selected.sync_status ) }
								{ selected.remote_id
									? ` · ${ selected.remote_id }`
									: '' }
								{ selected.sync_status === 'synced' &&
									' · ' + __( 'mock outbound OK', 'deskovi' ) }
							</p>
							{ selected.saas_url && (
								<p>
									<a
										href={ selected.saas_url }
										target="_blank"
										rel="noopener noreferrer"
									>
										{ __( 'Open mock SaaS deep link', 'deskovi' ) }
									</a>
								</p>
							) }

							<div className="itsdesk-card" style={ { marginTop: 14 } }>
								<div className="itsdesk-card__head">
									<h3>{ __( 'Order context', 'deskovi' ) }</h3>
									{ orderContext && (
										<span
											className={
												'itsdesk-badge itsdesk-badge--' +
												statusTone( orderContext.status )
											}
										>
											<span className="itsdesk-badge__dot" />
											{ labelStatus( orderContext.status ) }
										</span>
									) }
								</div>

								{ orderContext ? (
									<>
										<p className="itsdesk-admin__muted">
											{ __( 'Order', 'deskovi' ) } #
											{ orderContext.number }
											{ ' · ' }
											{ orderContext.currency }{ ' ' }
											{ orderContext.total }
											{ orderContext.payment_method_title
												? ` · ${ orderContext.payment_method_title }`
												: '' }
										</p>
										{ orderContext.shipping_method ? (
											<p className="itsdesk-admin__muted">
												{ __( 'Shipping', 'deskovi' ) }:{ ' ' }
												{ orderContext.shipping_method }
											</p>
										) : null }
										{ orderContext.items?.length > 0 && (
											<ul style={ { margin: '8px 0', paddingLeft: 18 } }>
												{ orderContext.items.map( ( item, idx ) => (
													<li key={ idx }>
														{ item.name } × { item.quantity }
														{ item.sku ? ` (${ item.sku })` : '' }
													</li>
												) ) }
											</ul>
										) }
										{ orderContext.billing && (
											<p className="itsdesk-admin__muted">
												{ __( 'Billing', 'deskovi' ) }:{ ' ' }
												{ [
													orderContext.billing.first_name,
													orderContext.billing.last_name,
													orderContext.billing.city,
													orderContext.billing.country,
												]
													.filter( Boolean )
													.join( ', ' ) }
											</p>
										) }
										{ orderContext.phone && (
											<p className="itsdesk-admin__muted">
												{ __( 'Phone', 'deskovi' ) }:{ ' ' }
												{ orderContext.phone }
											</p>
										) }
										{ ! orderContext.billing && ! orderContext.phone && (
											<p className="itsdesk-admin__muted">
												{ __(
													'Address/phone hidden by privacy settings.',
													'deskovi'
												) }
											</p>
										) }
										{ orderContext.timeline &&
											orderContext.timeline.length > 0 && (
												<>
													<button
														type="button"
														className="itsdesk-btn itsdesk-btn--ghost"
														onClick={ () =>
															setShowTimeline( ( v ) => ! v )
														}
													>
														{ showTimeline
															? __( 'Hide timeline', 'deskovi' )
															: __( 'Show timeline', 'deskovi' ) }
													</button>
													{ showTimeline && (
														<div className="itsdesk-thread">
															{ orderContext.timeline.map(
																( entry, idx ) => (
																	<div
																		key={ idx }
																		className="itsdesk-bubble"
																	>
																		<div className="itsdesk-bubble__meta">
																			<strong>
																				{ labelStatus(
																					entry.type
																				) }
																			</strong>
																			<span className="itsdesk-bubble__time">
																				{ formatWhen(
																					entry.at
																				) }
																			</span>
																		</div>
																		<p>{ entry.message }</p>
																	</div>
																)
															) }
														</div>
													) }
												</>
											) }
										<div className="itsdesk-admin__actions">
											<button
												type="button"
												className="itsdesk-btn itsdesk-btn--secondary"
												disabled={ orderLoading || busy }
												onClick={ onUnlinkOrder }
											>
												{ __( 'Unlink order', 'deskovi' ) }
											</button>
										</div>
									</>
								) : (
									<>
										<p className="itsdesk-admin__muted">
											{ __(
												'No order linked. Enter a WooCommerce order ID to attach context.',
												'deskovi'
											) }
										</p>
										<div className="itsdesk-field">
											<label htmlFor="itsdesk-link-order">
												{ __( 'Order ID', 'deskovi' ) }
											</label>
											<input
												id="itsdesk-link-order"
												className="itsdesk-input"
												value={ linkOrderId }
												onChange={ ( e ) =>
													setLinkOrderId(
														( e.target as HTMLInputElement ).value
													)
												}
												placeholder="1234"
											/>
										</div>
										<div className="itsdesk-admin__actions">
											<button
												type="button"
												className="itsdesk-btn itsdesk-btn--primary"
												disabled={
													orderLoading || busy || ! linkOrderId.trim()
												}
												onClick={ onLinkOrder }
											>
												{ orderLoading
													? __( 'Linking…', 'deskovi' )
													: __( 'Link order', 'deskovi' ) }
											</button>
										</div>
									</>
								) }
							</div>

							<div className="itsdesk-admin__actions">
								{ [ 'open', 'pending', 'resolved', 'closed' ].map(
									( s ) => (
										<button
											key={ s }
											type="button"
											className={
												'itsdesk-btn' +
												( selected.status === s
													? ' itsdesk-btn--primary'
													: ' itsdesk-btn--secondary' )
											}
											disabled={ busy || selected.status === s }
											onClick={ () => onStatus( s ) }
										>
											{ labelStatus( s ) }
										</button>
									)
								) }
							</div>

							<div className="itsdesk-thread">
								{ selected.messages.map( ( m ) => (
									<div
										key={ m.id }
										className={
											'itsdesk-bubble itsdesk-bubble--' +
											m.author +
											( m.internal ? ' is-internal' : '' )
										}
									>
										<div className="itsdesk-bubble__meta">
											<strong>{ labelStatus( m.author ) }</strong>
											{ m.internal && (
												<span>
													{ __( 'internal', 'deskovi' ) }
												</span>
											) }
											<span className="itsdesk-bubble__time">
												{ formatWhen( m.created_at ) }
											</span>
										</div>
										<p>{ m.body }</p>
									</div>
								) ) }
							</div>

							<div className="itsdesk-field" style={ { marginTop: 14 } }>
								<label htmlFor="itsdesk-tkt-reply">
									{ __( 'Reply', 'deskovi' ) }
								</label>
								<textarea
									id="itsdesk-tkt-reply"
									className="itsdesk-textarea"
									rows={ 3 }
									value={ replyBody }
									onChange={ ( e ) =>
										setReplyBody(
											( e.target as HTMLTextAreaElement ).value
										)
									}
								/>
							</div>
							<label className="itsdesk-checkline">
								<input
									type="checkbox"
									checked={ replyInternal }
									onChange={ ( e ) =>
										setReplyInternal(
											( e.target as HTMLInputElement ).checked
										)
									}
								/>
								<span>
									{ __( 'Internal note (not visible to customer)', 'deskovi' ) }
								</span>
							</label>
							<div className="itsdesk-admin__actions">
								<button
									type="button"
									className="itsdesk-btn itsdesk-btn--primary"
									disabled={ busy || ! replyBody }
									onClick={ onReply }
								>
									{ busy
										? __( 'Sending…', 'deskovi' )
										: __( 'Send reply', 'deskovi' ) }
								</button>
							</div>
						</div>
					) }
				</section>
			</div>
		</div>
	);
}
