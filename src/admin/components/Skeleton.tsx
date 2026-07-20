export function SkeletonPanel() {
	return (
		<div className="itsdesk-skeleton" aria-busy="true" aria-live="polite">
			<div className="itsdesk-skeleton__hero" />
			<div className="itsdesk-skeleton__row">
				<span />
				<span />
				<span />
				<span />
			</div>
			<div className="itsdesk-skeleton__block" />
			<div className="itsdesk-skeleton__block itsdesk-skeleton__block--short" />
		</div>
	);
}
