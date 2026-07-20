import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { savePrivacy } from '../api';
import { Toggle } from '../components/Toggle';
import type { PrivacySettings } from '../types';

type Props = {
	initial: PrivacySettings;
	onSaved: ( settings: PrivacySettings ) => void;
	onError: ( message: string ) => void;
};

export function PrivacyScreen( { initial, onSaved, onError }: Props ) {
	const [ settings, setSettings ] = useState< PrivacySettings >( initial );
	const [ saving, setSaving ] = useState( false );

	const onSave = () => {
		setSaving( true );
		savePrivacy( settings )
			.then( ( saved ) => {
				onSaved( saved );
				setSettings( saved );
				setSaving( false );
			} )
			.catch( ( err: Error ) => {
				onError( err?.message || __( 'Save failed.', 'deskovi' ) );
				setSaving( false );
			} );
	};

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2>{ __( 'Data & Privacy', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __(
							'Data minimization by default — sync only what agents need. Historical import stays off unless you opt in.',
							'deskovi'
						) }
					</p>
				</div>
			</div>

			<div className="itsdesk-card" style={ { marginTop: 16 } }>
				<div className="itsdesk-card__head">
					<h3>{ __( 'Customer fields', 'deskovi' ) }</h3>
				</div>
				<Toggle
					id="itsdesk-privacy-billing"
					checked={ settings.sync_billing_address }
					label={ __( 'Allow billing address sync', 'deskovi' ) }
					description={ __(
						'Useful for shipping disputes. Off by default.',
						'deskovi'
					) }
					onChange={ ( sync_billing_address ) =>
						setSettings( { ...settings, sync_billing_address } )
					}
				/>
				<Toggle
					id="itsdesk-privacy-phone"
					checked={ settings.sync_phone }
					label={ __( 'Allow phone number sync', 'deskovi' ) }
					description={ __(
						'Only when agents need to call the customer.',
						'deskovi'
					) }
					onChange={ ( sync_phone ) =>
						setSettings( { ...settings, sync_phone } )
					}
				/>
				<Toggle
					id="itsdesk-privacy-diag"
					checked={ settings.diagnostics_consent }
					label={ __( 'Share diagnostics with consent', 'deskovi' ) }
					description={ __(
						'Sanitized environment data only — never automatic full log upload.',
						'deskovi'
					) }
					onChange={ ( diagnostics_consent ) =>
						setSettings( { ...settings, diagnostics_consent } )
					}
				/>
			</div>

			<div className="itsdesk-grid-2">
				<div className="itsdesk-card">
					<h3>{ __( 'Historical import', 'deskovi' ) }</h3>
					<div className="itsdesk-field" style={ { marginTop: 10 } }>
						<label htmlFor="itsdesk-privacy-import">
							{ __( 'Import window', 'deskovi' ) }
						</label>
						<select
							id="itsdesk-privacy-import"
							className="itsdesk-select"
							value={ settings.historical_import }
							onChange={ ( e ) =>
								setSettings( {
									...settings,
									historical_import: ( e.target as HTMLSelectElement )
										.value,
								} )
							}
						>
							<option value="off">
								{ __( 'Off (recommended)', 'deskovi' ) }
							</option>
							<option value="30">{ __( 'Last 30 days', 'deskovi' ) }</option>
							<option value="60">{ __( 'Last 60 days', 'deskovi' ) }</option>
							<option value="90">{ __( 'Last 90 days', 'deskovi' ) }</option>
						</select>
					</div>
				</div>
				<div className="itsdesk-card">
					<h3>{ __( 'Retention', 'deskovi' ) }</h3>
					<div className="itsdesk-field" style={ { marginTop: 10 } }>
						<label htmlFor="itsdesk-privacy-retention">
							{ __( 'Synced context retention (days)', 'deskovi' ) }
						</label>
						<select
							id="itsdesk-privacy-retention"
							className="itsdesk-select"
							value={ String( settings.retention_days ) }
							onChange={ ( e ) =>
								setSettings( {
									...settings,
									retention_days: parseInt(
										( e.target as HTMLSelectElement ).value,
										10
									),
								} )
							}
						>
							<option value="30">30</option>
							<option value="60">60</option>
							<option value="90">90</option>
							<option value="180">180</option>
						</select>
					</div>
				</div>
			</div>

			<div className="itsdesk-health">
				<div>
					<strong>{ __( 'WordPress privacy tools', 'deskovi' ) }</strong>
					<p>
						{ __(
							'Deskovi registers a privacy-policy suggestion and local ticket export/erase under Tools → Export/Erase Personal Data. Remote SaaS deletion stays disabled until the cloud connection can fulfill it.',
							'deskovi'
						) }
					</p>
				</div>
			</div>

			<div className="itsdesk-health itsdesk-health--warn">
				<div>
					<strong>{ __( 'Remote SaaS deletion', 'deskovi' ) }</strong>
					<p>
						{ __(
							'Requesting deletion from Deskovi cloud requires a live SaaS connection (later). Local bridge tickets can already be erased via WordPress privacy tools.',
							'deskovi'
						) }
					</p>
				</div>
			</div>

			<div className="itsdesk-admin__actions">
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--primary"
					disabled={ saving }
					onClick={ onSave }
				>
					{ saving
						? __( 'Saving…', 'deskovi' )
						: __( 'Save privacy settings', 'deskovi' ) }
				</button>
				<button type="button" className="itsdesk-btn itsdesk-btn--secondary" disabled>
					{ __( 'Request remote data deletion', 'deskovi' ) }
				</button>
			</div>
		</div>
	);
}
