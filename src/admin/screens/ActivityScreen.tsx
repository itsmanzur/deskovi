import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchActivity } from '../api';
import { MetricCard } from '../components/MetricCard';
import { SkeletonPanel } from '../components/Skeleton';
import { IconQueue, IconSync } from '../components/Icons';
import type { ActivityData } from '../types';

export function ActivityScreen() {
	const [ data, setData ] = useState< ActivityData | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		fetchActivity()
			.then( ( payload ) => {
				setData( payload );
				setLoading( false );
			} )
			.catch( ( err: Error ) => {
				setError( err?.message || __( 'Failed to load activity.', 'deskovi' ) );
				setLoading( false );
			} );
	}, [] );

	if ( loading ) {
		return <SkeletonPanel />;
	}

	if ( error || ! data ) {
		return (
			<div className="itsdesk-admin__error">
				{ error || __( 'No activity data.', 'deskovi' ) }
			</div>
		);
	}

	const resultTone = ( result: string ) => {
		const r = result.toLowerCase();
		if ( r === 'ok' || r === 'success' ) {
			return 'ok';
		}
		if ( r === 'blocked' || r === 'fail' || r === 'failed' ) {
			return 'danger';
		}
		if ( r === 'retry' ) {
			return 'warn';
		}
		return 'neutral';
	};

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2>{ __( 'Activity', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __(
							'Sync cursor, Action Scheduler visibility, and connection audit trail — so outages never become silent. Mock mode may run sync inline when Action Scheduler is unavailable; use WooCommerce AS for production queues.',
							'deskovi'
						) }
					</p>
				</div>
			</div>

			<div className="itsdesk-metrics">
				<MetricCard
					label={ __( 'Sync cursor', 'deskovi' ) }
					value={ data.summary.cursor || '—' }
					icon={ <IconSync /> }
				/>
				<MetricCard
					label={ __( 'Pending jobs', 'deskovi' ) }
					value={ data.summary.pending }
					icon={ <IconQueue /> }
					tone={ data.summary.pending > 0 ? 'warn' : 'ok' }
				/>
				<MetricCard
					label={ __( 'Failed jobs', 'deskovi' ) }
					value={ data.summary.failed }
					tone={ data.summary.failed > 0 ? 'danger' : 'ok' }
				/>
			</div>

			<table className="itsdesk-table">
				<thead>
					<tr>
						<th>{ __( 'When', 'deskovi' ) }</th>
						<th>{ __( 'Type', 'deskovi' ) }</th>
						<th>{ __( 'Event', 'deskovi' ) }</th>
						<th>{ __( 'Result', 'deskovi' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ data.activity.map( ( row, index ) => (
						<tr key={ index }>
							<td>{ row.when }</td>
							<td className="itsdesk-table__area">{ row.type }</td>
							<td>{ row.event }</td>
							<td>
								<span
									className={
										'itsdesk-badge itsdesk-badge--' +
										resultTone( row.result )
									}
								>
									<span className="itsdesk-badge__dot" />
									{ row.result }
								</span>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
