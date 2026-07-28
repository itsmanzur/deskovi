import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	apiErrorMessage,
	fetchNotificationSettings,
	saveNotificationSettings,
} from '../api';
import { SkeletonPanel } from '../components/Skeleton';
import { Toggle } from '../components/Toggle';
import type { NotificationSettings } from '../types';

type Props = {
	onToast: ( message: string, tone?: 'ok' | 'danger' ) => void;
};

export function NotificationsScreen( { onToast }: Props ) {
	const [ settings, setSettings ] = useState< NotificationSettings | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		fetchNotificationSettings()
			.then( ( data ) => {
				setSettings( data );
				setLoading( false );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setLoading( false );
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const onSave = () => {
		if ( ! settings ) {
			return;
		}
		setSaving( true );
		saveNotificationSettings( settings )
			.then( ( saved ) => {
				setSettings( saved );
				setSaving( false );
				onToast( __( 'Notification settings saved.', 'deskovi' ) );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setSaving( false );
			} );
	};

	if ( loading || ! settings ) {
		return <SkeletonPanel />;
	}

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2>{ __( 'Notifications', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __(
							'Local email notifications only — sent via wp_mail(), nothing leaves your site.',
							'deskovi'
						) }
					</p>
				</div>
			</div>

			<div className="itsdesk-card" style={ { marginTop: 16 } }>
				<div className="itsdesk-card__head">
					<h3>{ __( 'Ticket event emails', 'deskovi' ) }</h3>
				</div>

				<Toggle
					id="itsdesk-notif-agent-new-ticket"
					checked={ settings.agent_new_ticket }
					label={ __( 'New ticket', 'deskovi' ) }
					description={ __(
						'Email every support agent when a customer opens a new ticket.',
						'deskovi'
					) }
					onChange={ ( agent_new_ticket ) =>
						setSettings( { ...settings, agent_new_ticket } )
					}
				/>

				<Toggle
					id="itsdesk-notif-agent-new-reply"
					checked={ settings.agent_new_reply }
					label={ __( 'Customer reply', 'deskovi' ) }
					description={ __(
						'Email the assigned agent (or all agents, if unassigned) when a customer replies.',
						'deskovi'
					) }
					onChange={ ( agent_new_reply ) =>
						setSettings( { ...settings, agent_new_reply } )
					}
				/>

				<Toggle
					id="itsdesk-notif-customer-reply"
					checked={ settings.customer_reply }
					label={ __( 'Agent reply', 'deskovi' ) }
					description={ __(
						'Email the customer when an agent replies to their ticket.',
						'deskovi'
					) }
					onChange={ ( customer_reply ) =>
						setSettings( { ...settings, customer_reply } )
					}
				/>

				<Toggle
					id="itsdesk-notif-agent-assigned"
					checked={ settings.agent_assigned }
					label={ __( 'Ticket assigned', 'deskovi' ) }
					description={ __(
						'Email an agent when a ticket is assigned to them.',
						'deskovi'
					) }
					onChange={ ( agent_assigned ) =>
						setSettings( { ...settings, agent_assigned } )
					}
				/>
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
						: __( 'Save notification settings', 'deskovi' ) }
				</button>
			</div>
		</div>
	);
}
