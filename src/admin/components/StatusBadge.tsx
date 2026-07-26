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
