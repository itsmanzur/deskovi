import { __ } from '@wordpress/i18n';
import * as Icons from '../components/Icons';
import { getProCatalog } from '../proCatalog';
import type { ScreenProps } from '../extensions';

/**
 * Shown to stores without Deskovi Pro. Deskovi Pro registers its own screen under the same id
 * ("pro"), which replaces this one.
 */
export function ProTeaserScreen( { ctx }: ScreenProps ) {
	const url = String( ctx.config.proUrl ?? '' );

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2 className="itsdesk-page-title">{ __( 'Deskovi Pro', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __( 'A separate add-on for stores with more tickets to handle. It works on top of this plugin and keeps everything on your own site.', 'deskovi' ) }
					</p>
				</div>
				<div className="itsdesk-admin__spacer" />
				{ url && (
					<a className="itsdesk-btn itsdesk-btn--primary" href={ url } target="_blank" rel="noopener noreferrer">
						{ __( 'Learn more', 'deskovi' ) }
					</a>
				) }
			</div>

			<div className="itsdesk-features">
				{ getProCatalog().map( ( item ) => {
					const Icon = Icons[ item.icon ];
					return (
						<div className="itsdesk-feature is-locked" key={ item.id }>
							<div className="itsdesk-feature__icon"><Icon size={ 16 } /></div>
							<strong>{ item.title }<span className="itsdesk-pro-tag">{ __( 'Pro', 'deskovi' ) }</span></strong>
							<p>{ item.description }</p>
							<ul className="itsdesk-feature__list">
								{ item.bullets.map( ( bullet ) => (
									<li key={ bullet }>{ bullet }</li>
								) ) }
							</ul>
						</div>
					);
				} ) }
			</div>

			<p className="itsdesk-admin__muted">
				{ __( 'Everything in Deskovi (free) keeps working as it is. Installing Deskovi Pro turns these features on and replaces this page with their settings.', 'deskovi' ) }
			</p>
		</div>
	);
}
