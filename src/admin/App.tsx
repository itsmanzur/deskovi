import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchOverview } from './api';
import { SkeletonPanel } from './components/Skeleton';
import { Toast } from './components/Toast';
import {
	IconActivity,
	IconBell,
	IconMark,
	IconOverview,
	IconPulse,
	IconShield,
	IconTicket,
	IconWidget,
} from './components/Icons';
import { ActivityScreen } from './screens/ActivityScreen';
import { DiagnosticsScreen } from './screens/DiagnosticsScreen';
import { NotificationsScreen } from './screens/NotificationsScreen';
import { OverviewScreen } from './screens/OverviewScreen';
import { PrivacyScreen } from './screens/PrivacyScreen';
import { TicketsScreen } from './screens/TicketsScreen';
import { WidgetScreen } from './screens/WidgetScreen';
import type { OverviewData, Screen } from './types';

const NAV: Array< {
	id: Screen;
	label: string;
	icon: ReturnType< typeof IconOverview >;
} > = [
	{
		id: 'overview',
		label: __( 'Overview', 'deskovi' ),
		icon: <IconOverview size={ 17 } />,
	},
	{
		id: 'tickets',
		label: __( 'Tickets', 'deskovi' ),
		icon: <IconTicket size={ 17 } />,
	},
	{
		id: 'widget',
		label: __( 'Widget', 'deskovi' ),
		icon: <IconWidget size={ 17 } />,
	},
	{
		id: 'privacy',
		label: __( 'Data & Privacy', 'deskovi' ),
		icon: <IconShield size={ 17 } />,
	},
	{
		id: 'notifications',
		label: __( 'Notifications', 'deskovi' ),
		icon: <IconBell size={ 17 } />,
	},
	{
		id: 'diagnostics',
		label: __( 'Diagnostics', 'deskovi' ),
		icon: <IconPulse size={ 17 } />,
	},
	{
		id: 'activity',
		label: __( 'Activity', 'deskovi' ),
		icon: <IconActivity size={ 17 } />,
	},
];

export function App() {
	const [ screen, setScreen ] = useState< Screen >( 'overview' );
	const [ overview, setOverview ] = useState< OverviewData | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ toast, setToast ] = useState< string | null >( null );
	const [ toastTone, setToastTone ] = useState< 'ok' | 'danger' >( 'ok' );

	const showToast = useCallback( ( message: string, tone: 'ok' | 'danger' = 'ok' ) => {
		setToastTone( tone );
		setToast( message );
	}, [] );

	const reloadOverview = useCallback( () => {
		setLoading( true );
		setError( null );
		fetchOverview()
			.then( ( data ) => {
				setOverview( data );
				setLoading( false );
			} )
			.catch( ( err: Error ) => {
				setError(
					err?.message || __( 'Failed to load Deskovi admin data.', 'deskovi' )
				);
				setLoading( false );
			} );
	}, [] );

	useEffect( () => {
		reloadOverview();
	}, [ reloadOverview ] );

	let body = null;
	if ( loading && ! overview ) {
		body = <SkeletonPanel />;
	} else if ( error && ! overview ) {
		body = <div className="itsdesk-admin__error">{ error }</div>;
	} else if ( overview ) {
		switch ( screen ) {
			case 'tickets':
				body = <TicketsScreen onToast={ showToast } />;
				break;
			case 'widget':
				body = (
					<WidgetScreen
						initial={ overview.widget }
						onSaved={ ( widget ) => {
							setOverview( { ...overview, widget } );
							showToast( __( 'Widget settings saved.', 'deskovi' ) );
						} }
						onError={ ( msg ) => showToast( msg, 'danger' ) }
					/>
				);
				break;
			case 'privacy':
				body = (
					<PrivacyScreen
						initial={ overview.privacy }
						onSaved={ ( privacy ) => {
							setOverview( { ...overview, privacy } );
							showToast( __( 'Privacy settings saved.', 'deskovi' ) );
						} }
						onError={ ( msg ) => showToast( msg, 'danger' ) }
					/>
				);
				break;
			case 'notifications':
				body = <NotificationsScreen onToast={ showToast } />;
				break;
			case 'diagnostics':
				body = <DiagnosticsScreen onToast={ showToast } />;
				break;
			case 'activity':
				body = <ActivityScreen />;
				break;
			default:
				body = (
					<OverviewScreen
						data={ overview }
						onNavigate={ setScreen }
						onRefresh={ reloadOverview }
					/>
				);
		}
	}

	return (
		<div className="itsdesk-admin">
			<header className="itsdesk-admin__chrome">
				<div className="itsdesk-admin__brand">
					<div className="itsdesk-admin__logo" aria-hidden="true">
						<IconMark size={ 22 } />
					</div>
					<div>
						<h1>{ __( 'Deskovi', 'deskovi' ) }</h1>
						<p className="itsdesk-admin__sub">
							{ __(
								'Support tickets and order-aware customer service for WooCommerce.',
								'deskovi'
							) }
						</p>
					</div>
				</div>
			</header>

			<nav
				className="itsdesk-admin__nav"
				aria-label={ __( 'Deskovi sections', 'deskovi' ) }
			>
				{ NAV.map( ( item ) => (
					<button
						key={ item.id }
						type="button"
						className={
							'itsdesk-admin__nav-btn' +
							( screen === item.id ? ' is-active' : '' )
						}
						onClick={ () => setScreen( item.id ) }
					>
						{ item.icon }
						{ item.label }
					</button>
				) ) }
			</nav>

			<div className="itsdesk-admin__panel" key={ screen }>
				{ body }
			</div>

			<Toast
				message={ toast }
				tone={ toastTone }
				onDismiss={ () => setToast( null ) }
			/>
		</div>
	);
}
