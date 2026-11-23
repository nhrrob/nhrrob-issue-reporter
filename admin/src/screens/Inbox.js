/**
 * Inbox: feedback from the widget waits here. Nothing reaches GitHub until
 * someone who may report sends it.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import api from '../api';
import { useToast } from '../components/ToastProvider';
import {
	Icon,
	Loading,
	Note,
	ScreenHeader,
	SearchBox,
	Segmented,
} from '../components/ui';
import InboxItem, { KIND_LABEL, ago } from './InboxItem';

const P = 'nhrrob-issue-reporter-';

export default function Inbox( { data, setData, navigate } ) {
	const toast = useToast();
	const [ items, setItems ] = useState( null );
	const [ tab, setTab ] = useState( 'new' );
	const [ search, setSearch ] = useState( '' );
	const [ pick, setPick ] = useState( 0 );

	const keep = ( next ) => {
		setItems( next );
		setData( ( d ) => ( {
			...d,
			inbox: next.filter( ( i ) => i.status === 'new' ).length,
		} ) );
	};

	const load = () =>
		api( '/feedback' )
			.then( ( answer ) => keep( answer.feedback ) )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) );

	useEffect( () => {
		load();
		// eslint-disable-next-line react-hooks/exhaustive-deps -- once, on open.
	}, [] );

	if ( ! items ) {
		return <Loading />;
	}

	const q = search.trim().toLowerCase();
	const shown = items.filter(
		( i ) =>
			i.status === tab &&
			( ! q ||
				i.text.toLowerCase().includes( q ) ||
				i.who.toLowerCase().includes( q ) ||
				i.page.toLowerCase().includes( q ) )
	);
	const chosen = shown.find( ( i ) => i.id === pick ) || shown[ 0 ];
	const count = ( status ) =>
		items.filter( ( i ) => i.status === status ).length;

	const replace = ( item ) =>
		keep( items.map( ( i ) => ( i.id === item.id ? item : i ) ) );

	let body = (
		<div className={ P + 'inbox__empty' }>
			<Icon name="inbox" size={ 26 } />
			<strong>
				{ tab === 'new'
					? __( 'Nothing waiting', 'nhrrob-issue-reporter' )
					: __( 'Nothing here', 'nhrrob-issue-reporter' ) }
			</strong>
			<span>
				{ data.widget.enabled
					? __(
							'Feedback sent with the widget shows up here.',
							'nhrrob-issue-reporter'
					  )
					: __(
							'Switch on the widget in Settings, under Client widget.',
							'nhrrob-issue-reporter'
					  ) }
			</span>
		</div>
	);
	if ( chosen ) {
		body = (
			<InboxItem
				key={ chosen.id }
				item={ chosen }
				data={ data }
				setData={ setData }
				onChange={ replace }
				onDelete={ () =>
					keep( items.filter( ( i ) => i.id !== chosen.id ) )
				}
				onSent={ load }
				navigate={ navigate }
			/>
		);
	}

	return (
		<>
			<ScreenHeader
				title={ __( 'Inbox', 'nhrrob-issue-reporter' ) }
				lede={ __(
					'Feedback from the widget waits here. Nothing reaches GitHub until you send it.',
					'nhrrob-issue-reporter'
				) }
				actions={
					<Segmented
						label={ __( 'Status', 'nhrrob-issue-reporter' ) }
						value={ tab }
						onChange={ setTab }
						options={ [
							{
								value: 'new',
								label: __( 'New', 'nhrrob-issue-reporter' ),
								count: count( 'new' ),
							},
							{
								value: 'sent',
								label: __( 'Sent', 'nhrrob-issue-reporter' ),
								count: count( 'sent' ),
							},
							{
								value: 'done',
								label: __( 'Done', 'nhrrob-issue-reporter' ),
								count: count( 'done' ),
							},
						] }
					/>
				}
			/>

			{ ! data.widget.enabled && items.length === 0 && (
				<Note>
					{ __(
						'The feedback widget is off. Switch it on in Settings, under Client widget, and choose who sees it.',
						'nhrrob-issue-reporter'
					) }
				</Note>
			) }

			<div className={ P + 'inbox' }>
				<section className={ P + 'panel ' + P + 'inbox__list' }>
					<div className={ P + 'inbox__search' }>
						<SearchBox
							label={ __(
								'Search feedback',
								'nhrrob-issue-reporter'
							) }
							value={ search }
							onChange={ setSearch }
						/>
					</div>
					<ul>
						{ shown.map( ( i ) => (
							<li key={ i.id }>
								<button
									type="button"
									className={
										P +
										'inbox__row' +
										( chosen && chosen.id === i.id
											? ' is-on'
											: '' )
									}
									aria-pressed={
										chosen && chosen.id === i.id
									}
									onClick={ () => setPick( i.id ) }
								>
									<strong>{ i.text }</strong>
									<span className={ P + 'muted' }>
										{ [ i.who, i.page, ago( i.time ) ]
											.filter( Boolean )
											.join( ' · ' ) }
									</span>
									<span className={ P + 'inbox__tags' }>
										<span
											className={
												P +
												'kind ' +
												P +
												'kind--' +
												i.kind
											}
										>
											{ KIND_LABEL[ i.kind ] }
										</span>
										{ i.errors.length > 0 && (
											<span
												className={
													P +
													'kind ' +
													P +
													'kind--bug'
												}
											>
												{ sprintf(
													/* translators: %d: number of console errors. */
													_n(
														'%d error',
														'%d errors',
														i.errors.length,
														'nhrrob-issue-reporter'
													),
													i.errors.length
												) }
											</span>
										) }
									</span>
								</button>
							</li>
						) ) }
					</ul>
				</section>
				{ body }
			</div>
		</>
	);
}
