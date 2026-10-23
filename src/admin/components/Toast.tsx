import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

type Props = {
	message: string | null;
	tone?: 'ok' | 'danger';
	onDismiss: () => void;
};

export function Toast( { message, tone = 'ok', onDismiss }: Props ) {
	useEffect( () => {
		if ( ! message ) {
			return;
		}
		const id = window.setTimeout( onDismiss, 3200 );
		return () => window.clearTimeout( id );
	}, [ message, onDismiss ] );

	if ( ! message ) {
		return null;
	}

	return (
		<div className={ `itsdesk-toast itsdesk-toast--${ tone }` } role={ tone === 'danger' ? 'alert' : 'status' }>
			{ message }
			<button type="button" className="itsdesk-toast__close" aria-label={ __( 'Dismiss', 'deskovi' ) } onClick={ onDismiss }>
				×
			</button>
		</div>
	);
}
