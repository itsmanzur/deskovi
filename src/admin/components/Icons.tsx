type IconProps = { size?: number; className?: string };

export function IconMark( { size = 22, className }: IconProps ) {
	return (
		<svg
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			className={ className }
			aria-hidden="true"
		>
			<path
				d="M4 7.5C4 5.567 5.567 4 7.5 4H16.5C18.433 4 20 5.567 20 7.5V13.5C20 15.433 18.433 17 16.5 17H11L7 20V17H7.5C5.567 17 4 15.433 4 13.5V7.5Z"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinejoin="round"
			/>
			<path
				d="M8 9.5H16M8 12.5H13"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinecap="round"
			/>
		</svg>
	);
}

export function IconLink( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path
				d="M9 12a4 4 0 0 0 4 4h2a4 4 0 0 0 0-8h-.5M15 12a4 4 0 0 0-4-4H9a4 4 0 1 0 0 8h.5"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinecap="round"
			/>
		</svg>
	);
}

export function IconWidget( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<rect x="4" y="4" width="16" height="16" rx="3" stroke="currentColor" strokeWidth="1.75" />
			<path d="M8 15h4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
			<circle cx="15.5" cy="9.5" r="1.5" fill="currentColor" />
		</svg>
	);
}

export function IconShield( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path
				d="M12 3L5 6v5c0 4.5 2.9 7.9 7 9 4.1-1.1 7-4.5 7-9V6l-7-3Z"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinejoin="round"
			/>
			<path d="M9.5 12l1.8 1.8L14.8 10" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
		</svg>
	);
}

export function IconPulse( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path
				d="M3 12h3.2l2.1-5.2L12.5 18l2.8-6H21"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

export function IconActivity( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path d="M4 6h16M4 12h10M4 18h13" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
		</svg>
	);
}

export function IconOverview( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<rect x="3.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.75" />
			<rect x="13.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.75" />
			<rect x="3.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.75" />
			<rect x="13.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" strokeWidth="1.75" />
		</svg>
	);
}

export function IconSync( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path
				d="M19 8a7 7 0 0 0-12.5-3M5 16a7 7 0 0 0 12.5 3"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinecap="round"
			/>
			<path d="M5 4v4h4M19 20v-4h-4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
		</svg>
	);
}

export function IconCart( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path d="M3 5h2l2.2 10.2a1.5 1.5 0 0 0 1.5 1.2H17a1.5 1.5 0 0 0 1.45-1.1L20 8H7" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
			<circle cx="9.5" cy="19.5" r="1.2" fill="currentColor" />
			<circle cx="16.5" cy="19.5" r="1.2" fill="currentColor" />
		</svg>
	);
}

export function IconCheck( { size = 16, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path d="M5 12.5l5 5L19 7" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
		</svg>
	);
}

export function IconExternal( { size = 14, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path d="M14 5h5v5M19 5l-9 9" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
			<path d="M10 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
		</svg>
	);
}

export function IconQueue( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
		</svg>
	);
}

export function IconTicket( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path
				d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinejoin="round"
			/>
			<path d="M10 8v8" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeDasharray="2 3" />
		</svg>
	);
}

export function IconPaperclip( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path
				d="M21 11.5L12.5 20a5.657 5.657 0 0 1-8-8L13 3.5a3.771 3.771 0 0 1 5.334 5.334L9.83 17.34a1.886 1.886 0 0 1-2.667-2.667L15 7"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

export function IconBell( { size = 18, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path
				d="M6 10a6 6 0 0 1 12 0v4.5l1.5 3H4.5L6 14.5V10Z"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinejoin="round"
			/>
			<path d="M9.5 20a2.5 2.5 0 0 0 5 0" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
		</svg>
	);
}

export function IconFileGeneric( { size = 16, className }: IconProps ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" fill="none" className={ className } aria-hidden="true">
			<path
				d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Z"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinejoin="round"
			/>
			<path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
		</svg>
	);
}
