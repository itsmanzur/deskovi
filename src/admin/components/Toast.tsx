import { useEffect } from '@wordpress/element';

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
		<div className={ `itsdesk-toast itsdesk-toast--${ tone }` } role="status">
			{ message }
			<button type="button" className="itsdesk-toast__close" onClick={ onDismiss }>
				×
			</button>
		</div>
	);
}
