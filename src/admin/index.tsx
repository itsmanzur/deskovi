import { createRoot } from '@wordpress/element';
import { App } from './App';
import './styles.css';

const rootEl = document.getElementById( 'itsdesk-admin-root' );

if ( rootEl ) {
	const root = createRoot( rootEl );
	root.render( <App /> );
}
