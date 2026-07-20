type Props = {
	id: string;
	checked: boolean;
	disabled?: boolean;
	onChange: ( checked: boolean ) => void;
	label: string;
	description?: string;
};

export function Toggle( {
	id,
	checked,
	disabled,
	onChange,
	label,
	description,
}: Props ) {
	return (
		<div className={ `itsdesk-toggle${ disabled ? ' is-disabled' : '' }` }>
			<div className="itsdesk-toggle__copy">
				<label htmlFor={ id } className="itsdesk-toggle__label">
					{ label }
				</label>
				{ description && (
					<p className="itsdesk-toggle__desc">{ description }</p>
				) }
			</div>
			<button
				id={ id }
				type="button"
				role="switch"
				aria-checked={ checked }
				disabled={ disabled }
				className={ `itsdesk-switch${ checked ? ' is-on' : '' }` }
				onClick={ () => onChange( ! checked ) }
			>
				<span className="itsdesk-switch__thumb" />
			</button>
		</div>
	);
}
