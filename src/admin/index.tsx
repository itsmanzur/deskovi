import { createRoot } from '@wordpress/element';
import { App } from './App';
// Side effect: exposes window.itsdesk.admin for add-on bundles.
import './extensions';
import './styles.css';

const rootEl = document.getElementById( 'itsdesk-admin-root' );

if ( rootEl ) {
	const root = createRoot( rootEl );
	root.render( <App /> );
}
