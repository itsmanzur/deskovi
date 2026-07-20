import type { ReactNode } from 'react';

type Props = {
	label: string;
	value: ReactNode;
	hint?: string;
	icon?: ReactNode;
	tone?: 'default' | 'ok' | 'warn' | 'danger';
	onClick?: () => void;
};

export function MetricCard( {
	label,
	value,
	hint,
	icon,
	tone = 'default',
	onClick,
}: Props ) {
	const className = `itsdesk-metric itsdesk-metric--${ tone }${
		onClick ? ' is-clickable' : ''
	}`;

	const body = (
		<>
			<div className="itsdesk-metric__top">
				<span className="itsdesk-metric__label">{ label }</span>
				{ icon && <span className="itsdesk-metric__icon">{ icon }</span> }
			</div>
			<div className="itsdesk-metric__value">{ value }</div>
			{ hint && <div className="itsdesk-metric__hint">{ hint }</div> }
		</>
	);

	if ( onClick ) {
		return (
			<button type="button" className={ className } onClick={ onClick }>
				{ body }
			</button>
		);
	}

	return <div className={ className }>{ body }</div>;
}
