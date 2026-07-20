import { __ } from '@wordpress/i18n';
import { saasUrl } from '../api';
import { connectionBadge } from '../components/StatusBadge';
import { MetricCard } from '../components/MetricCard';
import {
	IconCart,
	IconCheck,
	IconExternal,
	IconLink,
	IconPulse,
	IconQueue,
	IconShield,
	IconSync,
	IconWidget,
} from '../components/Icons';
import type { OverviewData, Screen } from '../types';

type Props = {
	data: OverviewData;
	onNavigate: ( screen: Screen ) => void;
	onRefresh: () => void;
};

export function OverviewScreen( { data, onNavigate, onRefresh }: Props ) {
	const { connection, queue_failures, queue_pending, hpos_enabled, widget, privacy } =
		data;
	const connected =
		connection.status === 'connected' || connection.status === 'error';
	const workspace =
		connection.workspace_name || __( 'Not linked yet', 'deskovi' );
	const lastSync = connection.last_sync_at || __( 'Never', 'deskovi' );

	return (
		<div className="itsdesk-admin__panel-inner">
			{ ! connected && (
				<section className="itsdesk-hero">
					<div style={ { position: 'relative', zIndex: 1 } }>
						<div className="itsdesk-hero__eyebrow">
							{ __( 'Setup · 2 minutes', 'deskovi' ) }
						</div>
						<h2>
							{ __(
								'Connect WooCommerce to Deskovi — without slowing checkout',
								'deskovi'
							) }
						</h2>
						<p>
							{ __(
								'Unlike full helpdesks inside WordPress, Deskovi stays a lightweight connector. Agents get order context in SaaS; your storefront stays fast and private.',
								'deskovi'
							) }
						</p>
						<div className="itsdesk-hero__actions">
							<button
								type="button"
								className="itsdesk-btn itsdesk-btn--primary"
								onClick={ () => onNavigate( 'connection' ) }
							>
								{ __( 'Connect to Deskovi', 'deskovi' ) }
							</button>
							<button
								type="button"
								className="itsdesk-btn itsdesk-btn--secondary"
								onClick={ () => onNavigate( 'privacy' ) }
							>
								{ __( 'Review privacy defaults', 'deskovi' ) }
							</button>
						</div>
					</div>
					<div
						className="itsdesk-checklist"
						style={ { position: 'relative', zIndex: 1 } }
					>
						<div className="itsdesk-checklist__title">
							{ __( 'Launch checklist', 'deskovi' ) }
						</div>
						<div className="itsdesk-checklist__item is-done">
							<span className="itsdesk-checklist__mark">
								<IconCheck size={ 12 } />
							</span>
							<div>
								<strong>{ __( 'Plugin installed', 'deskovi' ) }</strong>
								<span>
									{ __(
										'HPOS-ready connector is active on this store.',
										'deskovi'
									) }
								</span>
							</div>
						</div>
						<div
							className={
								'itsdesk-checklist__item' + ( connected ? ' is-done' : '' )
							}
						>
							<span className="itsdesk-checklist__mark">
								{ connected ? <IconCheck size={ 12 } /> : '2' }
							</span>
							<div>
								<strong>
									{ __( 'Secure workspace connection', 'deskovi' ) }
								</strong>
								<span>
									{ __(
										'One-time code + site keys. No permanent API key in settings.',
										'deskovi'
									) }
								</span>
							</div>
						</div>
						<div
							className={
								'itsdesk-checklist__item' +
								( widget.enabled ? ' is-done' : '' )
							}
						>
							<span className="itsdesk-checklist__mark">
								{ widget.enabled ? <IconCheck size={ 12 } /> : '3' }
							</span>
							<div>
								<strong>{ __( 'Enable support widget', 'deskovi' ) }</strong>
								<span>
									{ __(
										'Lazy launcher only — zero assets when disabled.',
										'deskovi'
									) }
								</span>
							</div>
						</div>
					</div>
				</section>
			) }

			<div className="itsdesk-admin__row">
				<h2>
					{ connected
						? __( 'Store health', 'deskovi' )
						: __( 'Overview', 'deskovi' ) }
				</h2>
				<div className="itsdesk-admin__spacer" />
				{ connectionBadge( connection.status ) }
			</div>

			<div className="itsdesk-metrics">
				<MetricCard
					label={ __( 'Workspace', 'deskovi' ) }
					value={ workspace }
					hint={
						connected
							? __( 'Linked workspace', 'deskovi' )
							: __( 'Connect to choose a workspace', 'deskovi' )
					}
					icon={ <IconLink /> }
					tone={ connected ? 'ok' : 'warn' }
					onClick={ () => onNavigate( 'connection' ) }
				/>
				<MetricCard
					label={ __( 'Last sync', 'deskovi' ) }
					value={ lastSync }
					hint={ __( 'Outbound event delivery', 'deskovi' ) }
					icon={ <IconSync /> }
					onClick={ () => onNavigate( 'activity' ) }
				/>
				<MetricCard
					label={ __( 'Queue', 'deskovi' ) }
					value={ `${ queue_failures }` }
					hint={ `${ queue_pending } ${ __( 'pending', 'deskovi' ) } · ${ __(
						'failures',
						'deskovi'
					) }` }
					icon={ <IconQueue /> }
					tone={ queue_failures > 0 ? 'warn' : 'default' }
					onClick={ () => onNavigate( 'activity' ) }
				/>
				<MetricCard
					label={ __( 'Order storage', 'deskovi' ) }
					value={
						hpos_enabled
							? __( 'HPOS on', 'deskovi' )
							: __( 'HPOS off', 'deskovi' )
					}
					hint={ __( 'WooCommerce CRUD compatible', 'deskovi' ) }
					icon={ <IconCart /> }
					tone={ hpos_enabled ? 'ok' : 'default' }
					onClick={ () => onNavigate( 'diagnostics' ) }
				/>
			</div>

			<div className={ `itsdesk-health${ connected ? '' : ' itsdesk-health--warn' }` }>
				<span className="itsdesk-health__icon">
					{ connected ? <IconPulse /> : <IconShield /> }
				</span>
				<div>
					<strong>
						{ connected
							? __( 'Connector healthy', 'deskovi' )
							: __( 'Waiting for secure connection', 'deskovi' ) }
					</strong>
					<p>
						{ connected
							? __(
									'Outbound events use Action Scheduler. No blocking remote calls on checkout, cart, or normal page loads.',
									'deskovi'
							  )
							: __(
									'Connect your store to unlock order-aware support. Until then, nothing leaves WordPress and checkout stays untouched.',
									'deskovi'
							  ) }
					</p>
				</div>
			</div>

			<div className="itsdesk-admin__actions">
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--primary"
					onClick={ () => onNavigate( 'connection' ) }
				>
					{ connected
						? __( 'Manage connection', 'deskovi' )
						: __( 'Connect to Deskovi', 'deskovi' ) }
				</button>
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--secondary"
					onClick={ () => onNavigate( 'diagnostics' ) }
				>
					{ __( 'Run diagnostics', 'deskovi' ) }
				</button>
				<a
					className="itsdesk-btn itsdesk-btn--secondary"
					href={ saasUrl() }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Open SaaS inbox', 'deskovi' ) }
					<IconExternal />
				</a>
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--ghost"
					onClick={ onRefresh }
				>
					{ __( 'Refresh', 'deskovi' ) }
				</button>
			</div>

			<h3 className="itsdesk-admin__section">
				{ __( 'Quick status', 'deskovi' ) }
			</h3>
			<table className="itsdesk-table">
				<thead>
					<tr>
						<th>{ __( 'Area', 'deskovi' ) }</th>
						<th>{ __( 'State', 'deskovi' ) }</th>
						<th>{ __( 'Detail', 'deskovi' ) }</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td className="itsdesk-table__area">
							{ __( 'Connection', 'deskovi' ) }
						</td>
						<td>{ connectionBadge( connection.status ) }</td>
						<td>{ connection.health || '—' }</td>
					</tr>
					<tr>
						<td className="itsdesk-table__area">
							{ __( 'Widget', 'deskovi' ) }
						</td>
						<td>
							{ widget.enabled ? (
								<span className="itsdesk-badge itsdesk-badge--ok">
									<span className="itsdesk-badge__dot" />
									{ __( 'Enabled', 'deskovi' ) }
								</span>
							) : (
								<span className="itsdesk-badge itsdesk-badge--neutral">
									<span className="itsdesk-badge__dot" />
									{ __( 'Disabled', 'deskovi' ) }
								</span>
							) }
						</td>
						<td>
							{ widget.placement } · { widget.theme }
						</td>
					</tr>
					<tr>
						<td className="itsdesk-table__area">
							{ __( 'Privacy', 'deskovi' ) }
						</td>
						<td>
							<span className="itsdesk-badge itsdesk-badge--info">
								<span className="itsdesk-badge__dot" />
								{ privacy.historical_import === 'off'
									? __( 'Minimal', 'deskovi' )
									: `${ privacy.historical_import }d` }
							</span>
						</td>
						<td>
							{ __( 'Retention', 'deskovi' ) }: { privacy.retention_days }d
						</td>
					</tr>
					<tr>
						<td className="itsdesk-table__area">
							{ __( 'Queue', 'deskovi' ) }
						</td>
						<td>
							<span
								className={
									'itsdesk-badge itsdesk-badge--' +
									( queue_failures > 0 ? 'warn' : 'ok' )
								}
							>
								<span className="itsdesk-badge__dot" />
								{ queue_pending === 0 && queue_failures === 0
									? __( 'Idle', 'deskovi' )
									: __( 'Active', 'deskovi' ) }
							</span>
						</td>
						<td>
							{ queue_pending } { __( 'pending', 'deskovi' ) } ·{ ' ' }
							{ queue_failures } { __( 'failed', 'deskovi' ) }
						</td>
					</tr>
				</tbody>
			</table>

			<div className="itsdesk-features">
				<div className="itsdesk-feature">
					<div className="itsdesk-feature__icon">
						<IconShield size={ 16 } />
					</div>
					<strong>{ __( 'Secure by design', 'deskovi' ) }</strong>
					<p>
						{ __(
							'Signed, replay-protected traffic. Least privilege. No master API key sitting in options.',
							'deskovi'
						) }
					</p>
				</div>
				<div className="itsdesk-feature">
					<div className="itsdesk-feature__icon">
						<IconCart size={ 16 } />
					</div>
					<strong>{ __( 'Order-aware support', 'deskovi' ) }</strong>
					<p>
						{ __(
							'Agents see relevant WooCommerce context — not a full store database copy.',
							'deskovi'
						) }
					</p>
				</div>
				<div className="itsdesk-feature">
					<div className="itsdesk-feature__icon">
						<IconWidget size={ 16 } />
					</div>
					<strong>{ __( 'Checkout stays fast', 'deskovi' ) }</strong>
					<p>
						{ __(
							'Zero blocking Deskovi network requests on checkout, cart, or normal page loads.',
							'deskovi'
						) }
					</p>
				</div>
			</div>
		</div>
	);
}
