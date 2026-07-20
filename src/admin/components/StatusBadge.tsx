import { __ } from '@wordpress/i18n';

type Tone = 'ok' | 'warn' | 'danger' | 'neutral' | 'info';

type Props = {
	tone?: Tone;
	pulse?: boolean;
	children: string;
};

export function StatusBadge( { tone = 'neutral', pulse = false, children }: Props ) {
	return (
		<span className={ `itsdesk-badge itsdesk-badge--${ tone }` }>
			<span className={ `itsdesk-badge__dot${ pulse ? ' is-pulse' : '' }` } />
			{ children }
		</span>
	);
}

export function connectionBadge( status: string ) {
	if ( status === 'connected' ) {
		return (
			<StatusBadge tone="ok" pulse>
				{ __( 'Connected', 'deskovi' ) }
			</StatusBadge>
		);
	}
	if ( status === 'error' ) {
		return (
			<StatusBadge tone="danger">
				{ __( 'Needs attention', 'deskovi' ) }
			</StatusBadge>
		);
	}
	return (
		<StatusBadge tone="warn">
			{ __( 'Not connected', 'deskovi' ) }
		</StatusBadge>
	);
}
