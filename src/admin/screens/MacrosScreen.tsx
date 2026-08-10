import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	apiErrorMessage,
	createMacro,
	deleteMacro,
	fetchMacros,
	updateMacro,
} from '../api';
import { SkeletonPanel } from '../components/Skeleton';
import type { CannedReply } from '../types';

type Props = {
	onToast: ( message: string, tone?: 'ok' | 'danger' ) => void;
};

function truncate( value: string, max: number ): string {
	if ( value.length <= max ) {
		return value;
	}
	return value.slice( 0, max ).trimEnd() + '…';
}

export function MacrosScreen( { onToast }: Props ) {
	const [ replies, setReplies ] = useState< CannedReply[] | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ showForm, setShowForm ] = useState( false );
	const [ editingId, setEditingId ] = useState< string | null >( null );
	const [ formTitle, setFormTitle ] = useState( '' );
	const [ formBody, setFormBody ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ deletingId, setDeletingId ] = useState< string | null >( null );

	useEffect( () => {
		fetchMacros()
			.then( ( data ) => {
				setReplies( data );
				setLoading( false );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setLoading( false );
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const openCreateForm = () => {
		setEditingId( null );
		setFormTitle( '' );
		setFormBody( '' );
		setShowForm( true );
	};

	const openEditForm = ( reply: CannedReply ) => {
		setEditingId( reply.id );
		setFormTitle( reply.title );
		setFormBody( reply.body );
		setShowForm( true );
	};

	const closeForm = () => {
		setShowForm( false );
		setEditingId( null );
	};

	const onSave = () => {
		setSaving( true );
		const data = { title: formTitle, body: formBody };
		const request = editingId ? updateMacro( editingId, data ) : createMacro( data );
		request
			.then( ( saved ) => {
				setReplies( ( current ) => {
					const list = current || [];
					if ( editingId ) {
						return list.map( ( r ) => ( r.id === saved.id ? saved : r ) );
					}
					return [ ...list, saved ];
				} );
				setSaving( false );
				setShowForm( false );
				setEditingId( null );
				onToast(
					editingId
						? __( 'Canned reply updated.', 'deskovi' )
						: __( 'Canned reply created.', 'deskovi' )
				);
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setSaving( false );
			} );
	};

	const onDelete = ( reply: CannedReply ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this canned reply?', 'deskovi' ) ) ) {
			return;
		}
		setDeletingId( reply.id );
		deleteMacro( reply.id )
			.then( () => {
				setReplies( ( current ) => ( current || [] ).filter( ( r ) => r.id !== reply.id ) );
				setDeletingId( null );
				onToast( __( 'Canned reply deleted.', 'deskovi' ) );
			} )
			.catch( ( err: unknown ) => {
				onToast( apiErrorMessage( err ), 'danger' );
				setDeletingId( null );
			} );
	};

	if ( loading || ! replies ) {
		return <SkeletonPanel />;
	}

	return (
		<div className="itsdesk-admin__panel-inner">
			<div className="itsdesk-admin__row">
				<div>
					<h2>{ __( 'Canned Replies', 'deskovi' ) }</h2>
					<p className="itsdesk-admin__muted">
						{ __(
							'Reusable reply templates agents can insert into the ticket reply composer with one click.',
							'deskovi'
						) }
					</p>
				</div>
				<div className="itsdesk-admin__spacer" />
				<button
					type="button"
					className="itsdesk-btn itsdesk-btn--primary"
					onClick={ openCreateForm }
				>
					{ __( 'Add canned reply', 'deskovi' ) }
				</button>
			</div>

			{ showForm && (
				<div className="itsdesk-card" style={ { marginTop: 16 } }>
					<div className="itsdesk-card__head">
						<h3>
							{ editingId
								? __( 'Edit canned reply', 'deskovi' )
								: __( 'New canned reply', 'deskovi' ) }
						</h3>
					</div>

					<div className="itsdesk-field">
						<label htmlFor="itsdesk-macro-title">
							{ __( 'Title', 'deskovi' ) }
						</label>
						<input
							id="itsdesk-macro-title"
							className="itsdesk-input"
							maxLength={ 80 }
							value={ formTitle }
							onChange={ ( e ) =>
								setFormTitle( ( e.target as HTMLInputElement ).value )
							}
						/>
					</div>

					<div className="itsdesk-field">
						<label htmlFor="itsdesk-macro-body">
							{ __( 'Body', 'deskovi' ) }
						</label>
						<textarea
							id="itsdesk-macro-body"
							className="itsdesk-textarea"
							rows={ 5 }
							maxLength={ 2000 }
							value={ formBody }
							onChange={ ( e ) =>
								setFormBody( ( e.target as HTMLTextAreaElement ).value )
							}
						/>
						<p className="itsdesk-admin__muted">
							{ __(
								'Available placeholders: {customer_name}, {customer_email}, {order_id}, {agent_name}',
								'deskovi'
							) }
						</p>
					</div>

					<div className="itsdesk-admin__actions">
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--primary"
							disabled={ saving || ! formTitle || ! formBody }
							onClick={ onSave }
						>
							{ saving ? __( 'Saving…', 'deskovi' ) : __( 'Save', 'deskovi' ) }
						</button>
						<button
							type="button"
							className="itsdesk-btn itsdesk-btn--secondary"
							disabled={ saving }
							onClick={ closeForm }
						>
							{ __( 'Cancel', 'deskovi' ) }
						</button>
					</div>
				</div>
			) }

			{ replies.length === 0 ? (
				<p className="itsdesk-admin__muted" style={ { marginTop: 16 } }>
					{ __( 'No canned replies yet.', 'deskovi' ) }
				</p>
			) : (
				<table className="itsdesk-table" style={ { marginTop: 16 } }>
					<thead>
						<tr>
							<th>{ __( 'Title', 'deskovi' ) }</th>
							<th>{ __( 'Preview', 'deskovi' ) }</th>
							<th>{ __( 'Actions', 'deskovi' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ replies.map( ( reply ) => (
							<tr key={ reply.id }>
								<td className="itsdesk-table__area">{ reply.title }</td>
								<td>{ truncate( reply.body, 80 ) }</td>
								<td>
									<div className="itsdesk-admin__actions">
										<button
											type="button"
											className="itsdesk-btn itsdesk-btn--secondary"
											onClick={ () => openEditForm( reply ) }
										>
											{ __( 'Edit', 'deskovi' ) }
										</button>
										<button
											type="button"
											className="itsdesk-btn itsdesk-btn--danger"
											disabled={ deletingId === reply.id }
											onClick={ () => onDelete( reply ) }
										>
											{ deletingId === reply.id
												? __( 'Deleting…', 'deskovi' )
												: __( 'Delete', 'deskovi' ) }
										</button>
									</div>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
