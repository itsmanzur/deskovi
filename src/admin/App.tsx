import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { fetchOverview } from './api';
import { SkeletonPanel } from './components/Skeleton';
import { Toast } from './components/Toast';
import {
	IconActivity,
	IconBell,
	IconFileGeneric,
	IconMark,
	IconOverview,
	IconPulse,
	IconQueue,
	IconShield,
	IconTicket,
	IconWidget,
} from './components/Icons';
import { ActivityScreen } from './screens/ActivityScreen';
import { DiagnosticsScreen } from './screens/DiagnosticsScreen';
import { DocsScreen } from './screens/DocsScreen';
import { MacrosScreen } from './screens/MacrosScreen';
import { NotificationsScreen } from './screens/NotificationsScreen';
import { OverviewScreen } from './screens/OverviewScreen';
import { PrivacyScreen } from './screens/PrivacyScreen';
import { TicketsScreen } from './screens/TicketsScreen';
import { WidgetScreen } from './screens/WidgetScreen';
import {
	ExtensionScreen,
	getExtensionScreen,
	getExtensionScreens,
	useExtensions,
} from './extensions';
import type { NavGroup } from './extensions';
import type { OverviewData, Screen } from './types';

type NavItem = {
	id: Screen;
	label: string;
	icon: ReactNode;
	group: NavGroup;
	order: number;
};

const GROUPS: Array< { id: NavGroup; label: string } > = [
	{ id: 'work', label: __( 'Work', 'deskovi' ) },
	{ id: 'insights', label: __( 'Insights', 'deskovi' ) },
	{ id: 'automate', label: __( 'Automate', 'deskovi' ) },
	{ id: 'channels', label: __( 'Channels', 'deskovi' ) },
	{ id: 'settings', label: __( 'Settings', 'deskovi' ) },
];

const BUILT_IN_NAV: NavItem[] = [
	{ id: 'tickets', label: __( 'Tickets', 'deskovi' ), icon: <IconTicket size={ 17 } />, group: 'work', order: 10 },
	{ id: 'macros', label: __( 'Canned Replies', 'deskovi' ), icon: <IconQueue size={ 17 } />, group: 'work', order: 20 },
	{ id: 'activity', label: __( 'Activity', 'deskovi' ), icon: <IconActivity size={ 17 } />, group: 'insights', order: 10 },
	{ id: 'widget', label: __( 'Widget', 'deskovi' ), icon: <IconWidget size={ 17 } />, group: 'channels', order: 10 },
	{ id: 'notifications', label: __( 'Notifications', 'deskovi' ), icon: <IconBell size={ 17 } />, group: 'settings', order: 10 },
	{ id: 'privacy', label: __( 'Data & Privacy', 'deskovi' ), icon: <IconShield size={ 17 } />, group: 'settings', order: 20 },
	{ id: 'diagnostics', label: __( 'Diagnostics', 'deskovi' ), icon: <IconPulse size={ 17 } />, group: 'settings', order: 30 },
	{ id: 'docs', label: __( 'Docs & Features', 'deskovi' ), icon: <IconFileGeneric size={ 17 } />, group: 'settings', order: 40 },
];

const OVERVIEW_ITEM = { id: 'overview', label: __( 'Overview', 'deskovi' ), icon: <IconOverview size={ 17 } /> };

function screenFromHash(): Screen {
	const id = window.location.hash.replace( /^#\/?/, '' );
	return /^[a-z0-9_-]{1,40}$/.test( id ) ? id : 'overview';
}

export function App() {
	useExtensions();
	const extensionItems: NavItem[] = getExtensionScreens().map( ( item ) => ( {
		id: item.id,
		label: item.label,
		icon: item.icon ?? <IconFileGeneric size={ 17 } />,
		group: GROUPS.some( ( g ) => g.id === item.group ) ? ( item.group as NavGroup ) : 'settings',
		order: item.order ?? 500,
	} ) );
	const allItems = [ ...BUILT_IN_NAV, ...extensionItems ];
	const sections = GROUPS.map( ( group ) => ( {
		...group,
		items: allItems.filter( ( item ) => item.group === group.id ).sort( ( a, b ) => a.order - b.order ),
	} ) ).filter( ( group ) => group.items.length > 0 );
	const [ screen, setScreenState ] = useState< Screen >( screenFromHash );
	const setScreen = useCallback( ( id: Screen ) => {
		setScreenState( id );
		window.history.replaceState( null, '', id === 'overview' ? window.location.pathname + window.location.search : '#/' + id );
	}, [] );
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

	// Links such as "Open" on the Docs page change the hash.
	useEffect( () => {
		const onHash = () => setScreenState( screenFromHash() );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );

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
			case 'macros':
				body = <MacrosScreen onToast={ showToast } />;
				break;
			case 'diagnostics':
				body = <DiagnosticsScreen onToast={ showToast } />;
				break;
			case 'activity':
				body = <ActivityScreen />;
				break;
			case 'docs':
				body = <DocsScreen />;
				break;
			default:
				body = getExtensionScreen( screen ) ? (
					<ExtensionScreen id={ screen } onToast={ showToast } />
				) : (
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
			<div className="itsdesk-admin__shell">
				<aside className="itsdesk-admin__side">
					<div className="itsdesk-admin__brand">
						<div className="itsdesk-admin__logo" aria-hidden="true">
							<IconMark size={ 20 } />
						</div>
						<div>
							<h1>{ __( 'Deskovi', 'deskovi' ) }</h1>
							<span className="itsdesk-admin__version">{ 'v' + ( window.itsdeskAdmin?.version ?? '' ) }</span>
						</div>
					</div>
					<nav className="itsdesk-admin__nav" aria-label={ __( 'Deskovi sections', 'deskovi' ) }>
						<div className="itsdesk-admin__nav-group">
							<button
								key={ OVERVIEW_ITEM.id }
								type="button"
								className={ 'itsdesk-admin__nav-btn' + ( screen === OVERVIEW_ITEM.id ? ' is-active' : '' ) }
								aria-current={ screen === OVERVIEW_ITEM.id ? 'page' : undefined }
								onClick={ () => setScreen( OVERVIEW_ITEM.id ) }
							>
								{ OVERVIEW_ITEM.icon }
								<span>{ OVERVIEW_ITEM.label }</span>
							</button>
						</div>
						{ sections.map( ( section ) => (
							<div className="itsdesk-admin__nav-group" key={ section.id } role="group" aria-label={ section.label }>
								<span className="itsdesk-admin__nav-label">{ section.label }</span>
								{ section.items.map( ( item ) => (
									<button
								key={ item.id }
								type="button"
								className={ 'itsdesk-admin__nav-btn' + ( screen === item.id ? ' is-active' : '' ) }
								aria-current={ screen === item.id ? 'page' : undefined }
								onClick={ () => setScreen( item.id ) }
							>
								{ item.icon }
								<span>{ item.label }</span>
							</button>
								) ) }
							</div>
						) ) }
					</nav>
				</aside>

				<main className="itsdesk-admin__main">
					<div className="itsdesk-admin__panel" key={ screen }>
						{ body }
					</div>
				</main>
			</div>

			<Toast
				message={ toast }
				tone={ toastTone }
				onDismiss={ () => setToast( null ) }
			/>
		</div>
	);
}
