import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchDiagnostics } from '../api';
import { MetricCard } from '../components/MetricCard';
import { SkeletonPanel } from '../components/Skeleton';
import type { DiagnosticsData } from '../types';

type Props = {
	onToast: ( message: string, tone?: 'ok' | 'danger' ) => void;
};

export function DiagnosticsScreen( { onToast }: Props ) {
	const [ data, setData ] = useState< DiagnosticsData | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ loading, setLoading ] = useState( true );

	const load = () => {
		setLoading( true );
		setError( null );
		fetchDiagnostics()
			.then( ( report ) => {
				setData( report );
				setLoading( false );
				onToast( __( 'Diagnostics refreshed.', 'deskovi' ) );
			} )
			.catch( ( err: Error ) => {
				setError(
					err?.message || __( 'Failed to load diagnostics.', 'deskovi' )
				);
				setLoading( false );
			} );
	};

	useEffect( () => {
		fetchDiagnostics()
			.then( ( report ) => {
				setData( report );
				setLoading( false );
			} )
			.catch( ( err: Error ) => {
				setError(
					err?.message || __( 'Failed to load diagnostics.', 'deskovi' )
				);
				setLoading( false );
			} );
		// Initial load should not toast.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const copyReport = () => {
		if ( ! data ) {
			return;
		}
		void navigator.clipboard.writeText( JSON.stringify( data, null, 2 ) ).then(
			() => onToast( __( 'Report copied to clipboard.', 'deskovi' ) ),
			() => onToast( __( 'Could not copy report.', 'deskovi' ), 'danger' )
		);
	};

	if ( loading && ! data ) {
		return <SkeletonPanel />;
	}

	if ( error && ! data ) {
		return <div className="itsdesk-admin__error">{ error }</div>;
	}

	if ( ! data ) {
		return null;
	}

	const statusTone = ( status: string ) => {
		if ( status === 'pass' || status === 'ok' ) {
			return 'ok';
		}
		if ( status === 'fail' ) {
			return 'danger';
		}
		return 'info';
	};

	const statusLabel = ( status: string ) => {
		if ( status === 'pass' ) {
			return __( 'Pass', 'deskovi' );
		}
		if ( status === 'ok' ) {
			return __( 'OK', 'deskovi' );
		}
		if ( status === 'fail' ) {
			return __( 'Fail', 'deskovi' );
		}
		if ( status === 'info' ) {
			return __( 'Info', 'deskovi' );
		}
		return status;
	};

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2>{ __( 'Diagnostics', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __(
							'Support-safe environment snapshot. Share intentionally — never automatic full log upload.',
							'deskovi'
						) }
					</p>
				</div>
				<div className="itsdesk-admin__spacer" />
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--secondary"
					onClick={ copyReport }
				>
					{ __( 'Copy report', 'deskovi' ) }
				</button>
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--primary"
					onClick={ load }
				>
					{ __( 'Run health check', 'deskovi' ) }
				</button>
			</div>

			<div className="itsdesk-metrics">
				<MetricCard label="WordPress" value={ data.wordpress } />
				<MetricCard label="WooCommerce" value={ data.woocommerce || '—' } />
				<MetricCard label="PHP" value={ data.php } />
				<MetricCard label="Deskovi" value={ data.plugin } />
			</div>

			<table className="itsdesk-table">
				<thead>
					<tr>
						<th>{ __( 'Check', 'deskovi' ) }</th>
						<th>{ __( 'Value', 'deskovi' ) }</th>
						<th>{ __( 'Status', 'deskovi' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ data.checks.map( ( check ) => (
						<tr key={ check.name }>
							<td className="itsdesk-table__area">{ check.name }</td>
							<td>{ check.value }</td>
							<td>
								<span
									className={
										'itsdesk-badge itsdesk-badge--' +
										statusTone( check.status )
									}
								>
									<span className="itsdesk-badge__dot" />
									{ statusLabel( check.status ) }
								</span>
							</td>
						</tr>
					) ) }
					<tr>
						<td className="itsdesk-table__area">
							{ __( 'Active theme', 'deskovi' ) }
						</td>
						<td>{ data.theme }</td>
						<td>
							<span className="itsdesk-badge itsdesk-badge--info">
								<span className="itsdesk-badge__dot" />
								{ __( 'Info', 'deskovi' ) }
							</span>
						</td>
					</tr>
				</tbody>
			</table>

			<h3 className="itsdesk-admin__section">
				{ __( 'Sanitized recent errors', 'deskovi' ) }
			</h3>
			{ data.errors.length === 0 ? (
				<p className="itsdesk-admin__muted">
					{ __( 'No diagnostic errors recorded.', 'deskovi' ) }
				</p>
			) : (
				<table className="itsdesk-table">
					<thead>
						<tr>
							<th>{ __( 'Time', 'deskovi' ) }</th>
							<th>{ __( 'Code', 'deskovi' ) }</th>
							<th>{ __( 'Message', 'deskovi' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ data.errors.map( ( row, index ) => (
							<tr key={ index }>
								<td>{ row.time || '—' }</td>
								<td>{ row.code || '—' }</td>
								<td>{ row.message || '—' }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
