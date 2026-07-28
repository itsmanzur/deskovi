import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { saveWidget } from '../api';
import { Toggle } from '../components/Toggle';
import type { WidgetSettings } from '../types';

type Props = {
	initial: WidgetSettings;
	onSaved: ( settings: WidgetSettings ) => void;
	onError: ( message: string ) => void;
};

export function WidgetScreen( { initial, onSaved, onError }: Props ) {
	const [ settings, setSettings ] = useState< WidgetSettings >( initial );
	const [ saving, setSaving ] = useState( false );

	const onSave = () => {
		setSaving( true );
		saveWidget( settings )
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
					<h2>{ __( 'Widget', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __(
							'Theme-safe launcher with lazy load after interaction — not another always-on chat bubble that taxes every page view.',
							'deskovi'
						) }
					</p>
				</div>
			</div>

			<div className="itsdesk-card" style={ { marginTop: 16 } }>
				<div className="itsdesk-card__head">
					<h3>{ __( 'Storefront launcher', 'deskovi' ) }</h3>
					<span
						className={
							'itsdesk-badge itsdesk-badge--' +
							( settings.enabled ? 'ok' : 'neutral' )
						}
					>
						<span className="itsdesk-badge__dot" />
						{ settings.enabled
							? __( 'Loads on storefront (skips checkout)', 'deskovi' )
							: __( 'No frontend assets', 'deskovi' ) }
					</span>
				</div>

				<Toggle
					id="itsdesk-widget-enabled"
					checked={ settings.enabled }
					label={ __( 'Enable support widget', 'deskovi' ) }
					description={ __(
						'When off, Deskovi adds zero scripts on the storefront. When on, a lazy launcher appears (not on cart/checkout).',
						'deskovi'
					) }
					onChange={ ( enabled ) => setSettings( { ...settings, enabled } ) }
				/>

				<div className="itsdesk-field" style={ { marginTop: 14 } }>
					<label htmlFor="itsdesk-widget-placement">
						{ __( 'Placement', 'deskovi' ) }
					</label>
					<select
						id="itsdesk-widget-placement"
						className="itsdesk-select"
						value={ settings.placement }
						disabled={ ! settings.enabled }
						onChange={ ( e ) =>
							setSettings( {
								...settings,
								placement: ( e.target as HTMLSelectElement ).value,
							} )
						}
					>
						<option value="bottom-right">
							{ __( 'Bottom right', 'deskovi' ) }
						</option>
						<option value="bottom-left">
							{ __( 'Bottom left', 'deskovi' ) }
						</option>
					</select>
				</div>

				<div className="itsdesk-field">
					<label htmlFor="itsdesk-widget-theme">
						{ __( 'Theme', 'deskovi' ) }
					</label>
					<select
						id="itsdesk-widget-theme"
						className="itsdesk-select"
						value={ settings.theme }
						disabled={ ! settings.enabled }
						onChange={ ( e ) =>
							setSettings( {
								...settings,
								theme: ( e.target as HTMLSelectElement ).value,
							} )
						}
					>
						<option value="system">{ __( 'System', 'deskovi' ) }</option>
						<option value="light">{ __( 'Light', 'deskovi' ) }</option>
						<option value="dark">{ __( 'Dark', 'deskovi' ) }</option>
					</select>
				</div>

				<div className="itsdesk-field">
					<label htmlFor="itsdesk-widget-launcher-label">
						{ __( 'Launcher button label', 'deskovi' ) }
					</label>
					<input
						id="itsdesk-widget-launcher-label"
						className="itsdesk-input"
						maxLength={ 40 }
						value={ settings.launcher_label }
						disabled={ ! settings.enabled }
						onChange={ ( e ) =>
							setSettings( {
								...settings,
								launcher_label: ( e.target as HTMLInputElement ).value,
							} )
						}
					/>
					<p className="itsdesk-admin__muted">
						{ __(
							"Leave empty to show an icon-only button. Add a short label like 'Chat with us' if you want text next to the icon.",
							'deskovi'
						) }
					</p>
				</div>

				<div className="itsdesk-health" style={ { marginTop: 8 } }>
					<div>
						<strong>{ __( 'Live behavior', 'deskovi' ) }</strong>
						<p>
							{ __(
								'Logged-in shoppers and verified guests (order + email OTP) can open tickets. Unread badges poll while the panel is open. Full UI loads only after the launcher is opened; cart/checkout never get the bundle.',
								'deskovi'
							) }
						</p>
					</div>
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
						: __( 'Save widget settings', 'deskovi' ) }
				</button>
			</div>
		</div>
	);
}
