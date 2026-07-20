import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	apiErrorMessage,
	completeConnection,
	connectionMode,
	disconnectConnection,
	rotateConnection,
	saasUrl,
	startConnection,
	testConnection,
} from '../api';
import { connectionBadge } from '../components/StatusBadge';
import { IconExternal } from '../components/Icons';
import type { ConnectionStartResponse, ConnectionState, MockWorkspace } from '../types';

type Props = {
	connection: ConnectionState;
	onConnectionChange: ( connection: ConnectionState ) => void;
	onToast: ( message: string, tone?: 'ok' | 'danger' ) => void;
};

type WizardStep = 'idle' | 'pick' | 'working';

export function ConnectionScreen( {
	connection,
	onConnectionChange,
	onToast,
}: Props ) {
	const [ confirmDisconnect, setConfirmDisconnect ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ step, setStep ] = useState< WizardStep >( 'idle' );
	const [ session, setSession ] = useState< ConnectionStartResponse | null >( null );
	const [ workspaceId, setWorkspaceId ] = useState( '' );
	const [ authCode, setAuthCode ] = useState( '' );
	const [ simulateFail, setSimulateFail ] = useState( false );

	const connected =
		connection.status === 'connected' || connection.status === 'error';
	const mode = connection.mode || connectionMode();

	const beginConnect = () => {
		setBusy( true );
		setSimulateFail( false );
		setAuthCode( '' );
		startConnection()
			.then( ( res ) => {
				setSession( res );
				const first = res.mock_workspaces?.[ 0 ]?.id || '';
				setWorkspaceId( first );
				setStep( 'pick' );
				setBusy( false );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setBusy( false );
			} );
	};

	const finishConnect = () => {
		if ( ! session?.state || ! workspaceId ) {
			onToast( __( 'Select a workspace to continue.', 'deskovi' ), 'danger' );
			return;
		}
		if ( mode === 'live' && ! authCode.trim() ) {
			onToast( __( 'Paste the authorization code from SaaS.', 'deskovi' ), 'danger' );
			return;
		}
		setBusy( true );
		setStep( 'working' );
		const code =
			mode === 'live'
				? authCode.trim()
				: simulateFail
				? 'fail'
				: 'mock-ok';
		completeConnection( {
			state: session.state,
			workspace_id: workspaceId,
			code,
		} )
			.then( ( next ) => {
				onConnectionChange( next );
				setSession( null );
				setAuthCode( '' );
				setStep( 'idle' );
				setBusy( false );
				onToast(
					mode === 'live'
						? __( 'Store connected to Deskovi.', 'deskovi' )
						: __( 'Store connected to Deskovi (mock).', 'deskovi' )
				);
			} )
			.catch( ( err: unknown ) => {
				setStep( 'pick' );
				setBusy( false );
				onToast( apiErrorMessage( err ), 'danger' );
			} );
	};

	const runTest = () => {
		setBusy( true );
		testConnection()
			.then( ( res ) => {
				onConnectionChange( res.connection );
				const ms = res.result?.latency_ms;
				onToast(
					ms
						? __( 'Connection healthy', 'deskovi' ) + ` · ${ ms }ms`
						: __( 'Connection healthy', 'deskovi' )
				);
				setBusy( false );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setBusy( false );
			} );
	};

	const runRotate = () => {
		setBusy( true );
		rotateConnection()
			.then( ( next ) => {
				onConnectionChange( next );
				onToast( __( 'Site keys rotated.', 'deskovi' ) );
				setBusy( false );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setBusy( false );
			} );
	};

	const runDisconnect = () => {
		setBusy( true );
		disconnectConnection()
			.then( ( next ) => {
				onConnectionChange( next );
				setConfirmDisconnect( false );
				setBusy( false );
				onToast( __( 'Store disconnected.', 'deskovi' ) );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setBusy( false );
			} );
	};

	const workspaces: MockWorkspace[] = session?.mock_workspaces || [];

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2>{ __( 'Connection', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __(
							'Secure site identity via one-time code and asymmetric keys. Mock transport is active until Deskovi SaaS goes live.',
							'deskovi'
						) }
					</p>
				</div>
				<div className="itsdesk-admin__spacer" />
				<span className="itsdesk-badge itsdesk-badge--info">
					<span className="itsdesk-badge__dot" />
					{ mode === 'mock'
						? __( 'Mock mode', 'deskovi' )
						: __( 'Live mode', 'deskovi' ) }
				</span>
				{ connectionBadge( connection.status ) }
			</div>

			{ ! connected && step === 'idle' && (
				<section className="itsdesk-hero" style={ { marginTop: 16 } }>
					<div style={ { position: 'relative', zIndex: 1 } }>
						<div className="itsdesk-hero__eyebrow">
							{ __( 'M2 · Secure connect', 'deskovi' ) }
						</div>
						<h2>
							{ __( 'Link this store to your Deskovi workspace', 'deskovi' ) }
						</h2>
						<p>
							{ __(
								'Mock mode walks the full handshake locally — workspace pick, key generation, and site binding — using the same contract live SaaS will implement.',
								'deskovi'
							) }
						</p>
						<div className="itsdesk-hero__actions">
							<button
								type="button"
								className="itsdesk-btn itsdesk-btn--primary"
								disabled={ busy }
								onClick={ beginConnect }
							>
								{ busy
									? __( 'Starting…', 'deskovi' )
									: __( 'Start secure connect', 'deskovi' ) }
							</button>
							{ mode === 'live' && (
								<a
									className="itsdesk-btn itsdesk-btn--secondary"
									href={ saasUrl() }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ __( 'Open SaaS', 'deskovi' ) }
									<IconExternal />
								</a>
							) }
						</div>
					</div>
					<div
						className="itsdesk-checklist"
						style={ { position: 'relative', zIndex: 1 } }
					>
						<div className="itsdesk-checklist__title">
							{ __( 'Handshake', 'deskovi' ) }
						</div>
						<div className="itsdesk-checklist__item">
							<span className="itsdesk-checklist__mark">1</span>
							<div>
								<strong>{ __( 'Start + CSRF state', 'deskovi' ) }</strong>
								<span>
									{ __(
										'WP creates a short-lived state token.',
										'deskovi'
									) }
								</span>
							</div>
						</div>
						<div className="itsdesk-checklist__item">
							<span className="itsdesk-checklist__mark">2</span>
							<div>
								<strong>{ __( 'Workspace + code', 'deskovi' ) }</strong>
								<span>
									{ __(
										'Pick workspace; mock issues a one-time code.',
										'deskovi'
									) }
								</span>
							</div>
						</div>
						<div className="itsdesk-checklist__item">
							<span className="itsdesk-checklist__mark">3</span>
							<div>
								<strong>{ __( 'Key exchange', 'deskovi' ) }</strong>
								<span>
									{ __(
										'Site key pair generated; private key never leaves WP.',
										'deskovi'
									) }
								</span>
							</div>
						</div>
					</div>
				</section>
			) }

			{ ! connected && step !== 'idle' && session && (
				<div className="itsdesk-card" style={ { marginTop: 16 } }>
					<div className="itsdesk-card__head">
						<h3>{ __( 'Choose workspace', 'deskovi' ) }</h3>
						<span className="itsdesk-badge itsdesk-badge--info">
							<span className="itsdesk-badge__dot" />
							{ __( 'Step 2 of 3', 'deskovi' ) }
						</span>
					</div>
					<p className="itsdesk-admin__muted">
						{ __(
							'In live mode this list comes from your Deskovi account. Mock workspaces are for local development.',
							'deskovi'
						) }
					</p>

					<div className="itsdesk-workspace-list">
						{ workspaces.map( ( ws ) => (
							<button
								key={ ws.id }
								type="button"
								className={
									'itsdesk-workspace' +
									( workspaceId === ws.id ? ' is-selected' : '' )
								}
								disabled={ busy }
								onClick={ () => setWorkspaceId( ws.id ) }
							>
								<strong>{ ws.name }</strong>
								<span>{ ws.id }</span>
							</button>
						) ) }
					</div>

					{ mode === 'live' && (
						<label className="itsdesk-field" style={ { display: 'block', marginTop: 16 } }>
							<span className="itsdesk-admin__muted">
								{ __( 'Authorization code', 'deskovi' ) }
							</span>
							<input
								type="text"
								className="itsdesk-input"
								value={ authCode }
								disabled={ busy }
								placeholder={ __( 'Paste code from deskovi:dev-code', 'deskovi' ) }
								onChange={ ( e ) =>
									setAuthCode( ( e.target as HTMLInputElement ).value )
								}
								style={ { display: 'block', width: '100%', marginTop: 6 } }
							/>
						</label>
					) }

					<div className="itsdesk-admin__actions">
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--primary"
							disabled={
								busy ||
								! workspaceId ||
								( mode === 'mock' && simulateFail ) ||
								( mode === 'live' && ! authCode.trim() )
							}
							onClick={ finishConnect }
						>
							{ step === 'working'
								? __( 'Exchanging keys…', 'deskovi' )
								: __( 'Complete connection', 'deskovi' ) }
						</button>
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--ghost"
							disabled={ busy }
							onClick={ () => {
								setStep( 'idle' );
								setSession( null );
								setSimulateFail( false );
							} }
						>
							{ __( 'Cancel', 'deskovi' ) }
						</button>
					</div>

					{ mode === 'mock' && (
						<details className="itsdesk-devtools">
							<summary>
								{ __( 'Developer tools', 'deskovi' ) }
							</summary>
							<label className="itsdesk-checkline">
								<input
									type="checkbox"
									checked={ simulateFail }
									disabled={ busy }
									onChange={ ( e ) =>
										setSimulateFail(
											( e.target as HTMLInputElement ).checked
										)
									}
								/>
								<span>
									{ __(
										'Simulate failed authorization (for error-path testing)',
										'deskovi'
									) }
								</span>
							</label>
							{ simulateFail && (
								<p className="itsdesk-admin__muted">
									{ __(
										'Uncheck this to enable Complete connection.',
										'deskovi'
									) }
								</p>
							) }
						</details>
					) }
				</div>
			) }

			<div className="itsdesk-grid-2">
				<div className="itsdesk-card">
					<div className="itsdesk-card__head">
						<h3>{ __( 'Workspace', 'deskovi' ) }</h3>
						{ connection.status === 'connected' && (
							<span className="itsdesk-badge itsdesk-badge--ok">
								<span className="itsdesk-badge__dot is-pulse" />
								{ __( 'Live', 'deskovi' ) }
							</span>
						) }
					</div>
					<p>
						<strong>
							{ connection.workspace_name || __( 'Not linked', 'deskovi' ) }
						</strong>
					</p>
					<p className="itsdesk-admin__muted" style={ { marginBottom: 6 } }>
						{ __( 'Site UUID', 'deskovi' ) }
					</p>
					<div className="itsdesk-mono">
						{ connection.site_uuid || '—' }
					</div>
					<p
						className="itsdesk-admin__muted"
						style={ { marginTop: 12, marginBottom: 6 } }
					>
						{ __( 'Key fingerprint', 'deskovi' ) }
					</p>
					<div className="itsdesk-mono">
						{ connection.public_key_fingerprint || '—' }
					</div>
					<p
						className="itsdesk-admin__muted"
						style={ { marginTop: 12, marginBottom: 6 } }
					>
						{ __( 'SaaS', 'deskovi' ) }
					</p>
					<div className="itsdesk-mono">
						{ connection.saas_url || saasUrl() }
					</div>
				</div>
				<div className="itsdesk-card">
					<div className="itsdesk-card__head">
						<h3>{ __( 'Health', 'deskovi' ) }</h3>
						<span
							className={
								'itsdesk-badge itsdesk-badge--' +
								( connection.health === 'healthy'
									? 'ok'
									: connection.health === 'error'
									? 'danger'
									: 'neutral' )
							}
						>
							<span className="itsdesk-badge__dot" />
							{ connection.health || __( 'unknown', 'deskovi' ) }
						</span>
					</div>
					<p>
						{ __( 'Last successful communication:', 'deskovi' ) }{ ' ' }
						<strong>
							{ connection.last_health_at || __( 'Never', 'deskovi' ) }
						</strong>
					</p>
					{ connection.scopes && connection.scopes.length > 0 && (
						<p className="itsdesk-admin__muted">
							{ __( 'Scopes:', 'deskovi' ) }{ ' ' }
							{ connection.scopes.join( ', ' ) }
						</p>
					) }
					<div className="itsdesk-admin__actions">
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--secondary"
							disabled={ ! connected || busy }
							onClick={ runTest }
						>
							{ __( 'Test connection', 'deskovi' ) }
						</button>
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--secondary"
							disabled={ ! connected || busy }
							onClick={ runRotate }
						>
							{ __( 'Rotate keys', 'deskovi' ) }
						</button>
					</div>
				</div>
			</div>

			{ connected && (
				<>
					<hr className="itsdesk-divider" />
					<h3 className="itsdesk-admin__section">
						{ __( 'Disconnect', 'deskovi' ) }
					</h3>
					<div className="itsdesk-health itsdesk-health--warn">
						<div>
							<strong>{ __( 'Destructive action', 'deskovi' ) }</strong>
							<p>
								{ __(
									'Disconnecting revokes site identity and stops outbound sync. Tickets remain in Deskovi SaaS until remote deletion is requested.',
									'deskovi'
								) }
							</p>
						</div>
					</div>
					{ ! confirmDisconnect ? (
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--danger"
							disabled={ busy }
							onClick={ () => setConfirmDisconnect( true ) }
						>
							{ __( 'Disconnect store…', 'deskovi' ) }
						</button>
					) : (
						<div className="itsdesk-admin__actions">
							<button
								type="button"
								className="itsdesk-btn itsdesk-btn--primary"
								disabled={ busy }
								onClick={ runDisconnect }
							>
								{ busy
									? __( 'Disconnecting…', 'deskovi' )
									: __( 'Confirm disconnect', 'deskovi' ) }
							</button>
							<button
								type="button"
								className="itsdesk-btn itsdesk-btn--ghost"
								disabled={ busy }
								onClick={ () => setConfirmDisconnect( false ) }
							>
								{ __( 'Cancel', 'deskovi' ) }
							</button>
						</div>
					) }
				</>
			) }
		</div>
	);
}
