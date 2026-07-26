import { __ } from '@wordpress/i18n';
import { MetricCard } from '../components/MetricCard';
import {
	IconCart,
	IconCheck,
	IconPulse,
	IconShield,
	IconWidget,
} from '../components/Icons';
import type { OverviewData, Screen } from '../types';

type Props = {
	data: OverviewData;
	onNavigate: ( screen: Screen ) => void;
	onRefresh: () => void;
};

export function OverviewScreen( { data, onNavigate, onRefresh }: Props ) {
	const { widget, privacy } = data;

	return (
		<div className="itsdesk-admin__panel-inner">
			<section className="itsdesk-hero">
				<div style={ { position: 'relative', zIndex: 1 } }>
					<div className="itsdesk-hero__eyebrow">
						{ __( 'Setup · 2 minutes', 'deskovi' ) }
					</div>
					<h2>
						{ __(
							'A support desk that lives inside your store',
							'deskovi'
						) }
					</h2>
					<p>
						{ __(
							'Tickets, replies, and attachments are all stored in your own WordPress database — nothing leaves your site.',
							'deskovi'
						) }
					</p>
					<div className="itsdesk-hero__actions">
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--primary"
							onClick={ () => onNavigate( 'tickets' ) }
						>
							{ __( 'View tickets', 'deskovi' ) }
						</button>
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--secondary"
							onClick={ () => onNavigate( 'widget' ) }
						>
							{ __( 'Set up the widget', 'deskovi' ) }
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
									'HPOS-ready ticket system is active on this store.',
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
							{ widget.enabled ? <IconCheck size={ 12 } /> : '2' }
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
					<div className="itsdesk-checklist__item is-done">
						<span className="itsdesk-checklist__mark">
							<IconCheck size={ 12 } />
						</span>
						<div>
							<strong>{ __( 'Privacy defaults reviewed', 'deskovi' ) }</strong>
							<span>
								{ __(
									'Export/erase tools are wired to WordPress privacy tools.',
									'deskovi'
								) }
							</span>
						</div>
					</div>
				</div>
			</section>

			<div className="itsdesk-admin__row">
				<h2>{ __( 'Store health', 'deskovi' ) }</h2>
			</div>

			<div className="itsdesk-metrics">
				<MetricCard
					label={ __( 'Widget', 'deskovi' ) }
					value={
						widget.enabled ? __( 'Enabled', 'deskovi' ) : __( 'Disabled', 'deskovi' )
					}
					hint={ `${ widget.placement } · ${ widget.theme }` }
					icon={ <IconWidget /> }
					tone={ widget.enabled ? 'ok' : 'default' }
					onClick={ () => onNavigate( 'widget' ) }
				/>
				<MetricCard
					label={ __( 'Privacy', 'deskovi' ) }
					value={
						privacy.historical_import === 'off'
							? __( 'Minimal', 'deskovi' )
							: `${ privacy.historical_import }d`
					}
					hint={ `${ __( 'Retention', 'deskovi' ) }: ${ privacy.retention_days }d` }
					icon={ <IconShield /> }
					onClick={ () => onNavigate( 'privacy' ) }
				/>
				<MetricCard
					label={ __( 'Order storage', 'deskovi' ) }
					value={ __( 'HPOS compatible', 'deskovi' ) }
					hint={ __( 'WooCommerce CRUD compatible', 'deskovi' ) }
					icon={ <IconCart /> }
					tone="ok"
					onClick={ () => onNavigate( 'diagnostics' ) }
				/>
			</div>

			<div className="itsdesk-health">
				<span className="itsdesk-health__icon">
					<IconPulse />
				</span>
				<div>
					<strong>{ __( 'Running locally', 'deskovi' ) }</strong>
					<p>
						{ __(
							'No external service or account is required. All ticket data stays in your WordPress database.',
							'deskovi'
						) }
					</p>
				</div>
			</div>

			<div className="itsdesk-admin__actions">
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--primary"
					onClick={ () => onNavigate( 'tickets' ) }
				>
					{ __( 'View tickets', 'deskovi' ) }
				</button>
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--secondary"
					onClick={ () => onNavigate( 'diagnostics' ) }
				>
					{ __( 'Run diagnostics', 'deskovi' ) }
				</button>
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--ghost"
					onClick={ onRefresh }
				>
					{ __( 'Refresh', 'deskovi' ) }
				</button>
			</div>

			<div className="itsdesk-features">
				<div className="itsdesk-feature">
					<div className="itsdesk-feature__icon">
						<IconShield size={ 16 } />
					</div>
					<strong>{ __( 'Private by default', 'deskovi' ) }</strong>
					<p>
						{ __(
							'No master API key, no external account, no data leaving your site.',
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
							'Agents see relevant WooCommerce context right next to each ticket.',
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
							'No blocking network requests on checkout, cart, or normal page loads.',
							'deskovi'
						) }
					</p>
				</div>
			</div>
		</div>
	);
}
