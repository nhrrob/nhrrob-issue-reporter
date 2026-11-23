/**
 * Sent: the issues opened from this site, with their state on GitHub.
 * The filters live in the page address, so a reload or a shared link opens
 * the same view.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n, humanTimeDiff } from '@wordpress/date';

import api from '../api';
import { useToast } from '../components/ToastProvider';
import {
	Button,
	External,
	Field,
	Icon,
	Panel,
	Pill,
	ScreenHeader,
	SearchBox,
	Segmented,
	Stat,
} from '../components/ui';

const P = 'nhrrob-issue-reporter-';
const PAGE = 20;

function readQuery() {
	const q = new window.URLSearchParams( window.location.search );
	return {
		state: [ 'open', 'closed' ].includes( q.get( 'ir_state' ) )
			? q.get( 'ir_state' )
			: 'all',
		repo: q.get( 'ir_repo' ) || '',
		search: q.get( 'ir_q' ) || '',
		sort: q.get( 'ir_sort' ) === 'oldest' ? 'oldest' : 'newest',
	};
}

function writeQuery( view ) {
	const url = new window.URL( window.location.href );
	const set = ( key, value, fallback ) => {
		if ( value && value !== fallback ) {
			url.searchParams.set( key, value );
		} else {
			url.searchParams.delete( key );
		}
	};
	set( 'ir_state', view.state, 'all' );
	set( 'ir_repo', view.repo, '' );
	set( 'ir_q', view.search.trim(), '' );
	set( 'ir_sort', view.sort, 'newest' );
	window.history.replaceState( null, '', url.toString() );
}

export default function Sent( { data, setData, navigate } ) {
	const toast = useToast();
	const [ loading, setLoading ] = useState( false );
	const [ view, setView ] = useState( readQuery );
	const [ open, setOpen ] = useState( '' );
	const [ shown, setShown ] = useState( PAGE );

	const change = ( patch ) => {
		const next = { ...view, ...patch };
		setView( next );
		setShown( PAGE );
		writeQuery( next );
	};

	// Grouped by repository, so a renamed plugin keeps its reports together.
	const groups = {};
	data.reports.forEach( ( r ) => {
		groups[ r.repo ] = groups[ r.repo ] || { name: r.plugin, count: 0 };
		groups[ r.repo ].count++;
	} );
	const repo = groups[ view.repo ] ? view.repo : '';
	const inRepo = data.reports.filter( ( r ) => ! repo || r.repo === repo );
	const isOpen = ( r ) => r.state !== 'closed';
	const openCount = inRepo.filter( isOpen ).length;
	const needle = view.search.trim().toLowerCase().replace( /^#/, '' );

	const reports = inRepo
		.filter(
			( r ) =>
				view.state === 'all' ||
				( view.state === 'open' ? isOpen( r ) : ! isOpen( r ) )
		)
		.filter(
			( r ) =>
				! needle ||
				String( r.number ) === needle ||
				r.title.toLowerCase().includes( needle )
		)
		.sort( ( a, b ) =>
			view.sort === 'oldest' ? a.time - b.time : b.time - a.time
		);

	const comments = data.reports.reduce(
		( sum, r ) => sum + ( r.comments || 0 ),
		0
	);
	const lastChecked = data.reports.reduce(
		( max, r ) => Math.max( max, r.checked || 0 ),
		0
	);
	const monthAgo = Date.now() / 1000 - 30 * 86400;
	const recent = data.reports.filter( ( r ) => r.time > monthAgo ).length;

	// A report names its plugin by name; the comment route needs its key.
	const target = ( r ) =>
		data.plugins.find(
			( p ) => p.repo === r.repo && p.name === r.plugin
		) || data.plugins.find( ( p ) => p.repo === r.repo );

	// Asks GitHub about the open ones; the server limits how often.
	const refresh = useCallback( () => {
		setLoading( true );
		api( '/reports' )
			.then( ( answer ) =>
				setData( ( current ) => ( {
					...current,
					reports: answer.reports,
				} ) )
			)
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) )
			.finally( () => setLoading( false ) );
	}, [ setData, toast ] );

	useEffect( refresh, [ refresh ] );

	return (
		<>
			<ScreenHeader
				title={ __( 'Sent reports', 'nhrrob-issue-reporter' ) }
				lede={ __(
					'Every issue opened from this site. The text lives on GitHub; only the link is kept here.',
					'nhrrob-issue-reporter'
				) }
				actions={
					<>
						{ lastChecked > 0 && (
							<span className={ P + 'muted' }>
								{ sprintf(
									/* translators: %s: how long ago, such as "4 minutes ago". */
									__(
										'States checked %s',
										'nhrrob-issue-reporter'
									),
									humanTimeDiff( lastChecked * 1000 )
								) }
							</span>
						) }
						<Button disabled={ loading } onClick={ refresh }>
							<Icon name="refresh" size={ 15 } />
							{ loading
								? __( 'Checking…', 'nhrrob-issue-reporter' )
								: __( 'Check now', 'nhrrob-issue-reporter' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => navigate( 'report' ) }
						>
							<Icon name="plus" size={ 15 } />
							{ __( 'New report', 'nhrrob-issue-reporter' ) }
						</Button>
					</>
				}
			/>

			{ data.reports.length > 0 && (
				<div className={ P + 'stats' }>
					<Stat
						icon="dot"
						tone="ok"
						label={ __( 'Open', 'nhrrob-issue-reporter' ) }
						value={ data.reports.filter( isOpen ).length }
					/>
					<Stat
						icon="check"
						tone="closed"
						label={ __( 'Closed', 'nhrrob-issue-reporter' ) }
						value={
							data.reports.filter( ( r ) => ! isOpen( r ) ).length
						}
					/>
					<Stat
						icon="send"
						tone="accent"
						label={ __(
							'Sent in 30 days',
							'nhrrob-issue-reporter'
						) }
						value={ recent }
					/>
					<Stat
						icon="comment"
						tone="warn"
						label={ __(
							'Comments added',
							'nhrrob-issue-reporter'
						) }
						value={ comments }
					/>
				</div>
			) }

			<Panel flush>
				{ ! data.reports.length ? (
					<div className={ P + 'empty' }>
						<span className={ P + 'tile ' + P + 'tile--accent' }>
							<Icon name="send" size={ 20 } />
						</span>
						<strong>
							{ __(
								'No report has been sent from this site yet.',
								'nhrrob-issue-reporter'
							) }
						</strong>
						<Button
							variant="primary"
							onClick={ () => navigate( 'report' ) }
						>
							{ __( 'Report an issue', 'nhrrob-issue-reporter' ) }
						</Button>
					</div>
				) : (
					<>
						<div className={ P + 'toolbar' }>
							<Segmented
								label={ __( 'State', 'nhrrob-issue-reporter' ) }
								value={ view.state }
								onChange={ ( state ) => change( { state } ) }
								options={ [
									{
										value: 'all',
										label: __(
											'All',
											'nhrrob-issue-reporter'
										),
										count: inRepo.length,
									},
									{
										value: 'open',
										label: __(
											'Open',
											'nhrrob-issue-reporter'
										),
										count: openCount,
									},
									{
										value: 'closed',
										label: __(
											'Closed',
											'nhrrob-issue-reporter'
										),
										count: inRepo.length - openCount,
									},
								] }
							/>
							<div className={ P + 'select' }>
								<span aria-hidden="true">
									{ __( 'Plugin', 'nhrrob-issue-reporter' ) }
								</span>
								<select
									aria-label={ __(
										'Plugin',
										'nhrrob-issue-reporter'
									) }
									value={ repo }
									onChange={ ( e ) =>
										change( { repo: e.target.value } )
									}
								>
									<option value="">
										{ sprintf(
											/* translators: %d: number of reports. */
											__(
												'All (%d)',
												'nhrrob-issue-reporter'
											),
											data.reports.length
										) }
									</option>
									{ Object.keys( groups ).map( ( key ) => (
										<option key={ key } value={ key }>
											{ groups[ key ].name +
												' (' +
												groups[ key ].count +
												')' }
										</option>
									) ) }
								</select>
							</div>
							<SearchBox
								label={ __(
									'Search title or #number',
									'nhrrob-issue-reporter'
								) }
								value={ view.search }
								onChange={ ( search ) => change( { search } ) }
							/>
							<div className={ P + 'select' }>
								<span aria-hidden="true">
									{ __( 'Sort', 'nhrrob-issue-reporter' ) }
								</span>
								<select
									aria-label={ __(
										'Sort',
										'nhrrob-issue-reporter'
									) }
									value={ view.sort }
									onChange={ ( e ) =>
										change( { sort: e.target.value } )
									}
								>
									<option value="newest">
										{ __(
											'Newest first',
											'nhrrob-issue-reporter'
										) }
									</option>
									<option value="oldest">
										{ __(
											'Oldest first',
											'nhrrob-issue-reporter'
										) }
									</option>
								</select>
							</div>
						</div>

						{ ! reports.length && (
							<p className={ P + 'empty is-small' }>
								{ __(
									'No report matches these filters.',
									'nhrrob-issue-reporter'
								) }
							</p>
						) }
						<ul className={ P + 'rows' }>
							{ reports.slice( 0, shown ).map( ( r ) => {
								const key = r.repo + '#' + r.number;
								const closed = ! isOpen( r );
								return (
									<li key={ key } className={ P + 'row' }>
										<span
											className={
												P +
												'tile ' +
												P +
												'tile--' +
												( closed ? 'closed' : 'ok' )
											}
										>
											<Icon
												name={
													closed ? 'check' : 'dot'
												}
												size={ 17 }
											/>
										</span>
										<div className={ P + 'row__main' }>
											<External
												href={ r.url }
												className={ P + 'row__title' }
											>
												{ r.title }
											</External>
											<span className={ P + 'row__meta' }>
												<span className="is-mono">
													#{ r.number }
												</span>
												<span>{ r.plugin }</span>
												<span>
													{ dateI18n(
														'M j, Y',
														r.time * 1000
													) }
												</span>
												{ r.comments > 0 && (
													<span>
														{ sprintf(
															/* translators: %d: number of comments. */
															_n(
																'%d comment added',
																'%d comments added',
																r.comments,
																'nhrrob-issue-reporter'
															),
															r.comments
														) }
													</span>
												) }
											</span>
										</div>
										<Pill tone={ closed ? 'closed' : 'ok' }>
											{ closed
												? __(
														'Closed',
														'nhrrob-issue-reporter'
												  )
												: __(
														'Open',
														'nhrrob-issue-reporter'
												  ) }
										</Pill>
										{ target( r ) && (
											<Button
												small
												aria-expanded={ open === key }
												onClick={ () =>
													setOpen(
														open === key ? '' : key
													)
												}
											>
												{ __(
													'Comment',
													'nhrrob-issue-reporter'
												) }
											</Button>
										) }
										{ open === key && (
											<CommentBox
												plugin={ target( r ) }
												report={ r }
												onCancel={ () => setOpen( '' ) }
												onDone={ ( next ) => {
													setOpen( '' );
													setData( ( current ) => ( {
														...current,
														reports: next,
													} ) );
												} }
											/>
										) }
									</li>
								);
							} ) }
						</ul>
						{ reports.length > 0 && (
							<div className={ P + 'panel__foot' }>
								<span>
									{ sprintf(
										/* translators: 1: number shown, 2: number that match. */
										__(
											'Showing %1$d of %2$d',
											'nhrrob-issue-reporter'
										),
										Math.min( shown, reports.length ),
										reports.length
									) }
								</span>
								{ shown < reports.length && (
									<Button
										small
										onClick={ () =>
											setShown( shown + PAGE )
										}
									>
										{ __(
											'Show more',
											'nhrrob-issue-reporter'
										) }
									</Button>
								) }
							</div>
						) }
					</>
				) }
			</Panel>
		</>
	);
}

function CommentBox( { plugin, report, onCancel, onDone } ) {
	const toast = useToast();
	const [ body, setBody ] = useState( '' );
	const [ sending, setSending ] = useState( false );
	const id = P + 'comment-' + report.number;

	const send = ( event ) => {
		event.preventDefault();
		setSending( true );
		api( '/comments', 'POST', {
			plugin: plugin.file,
			number: report.number,
			body,
		} )
			.then( ( answer ) => {
				toast(
					sprintf(
						/* translators: %d: issue number. */
						__(
							'Comment added to issue #%d',
							'nhrrob-issue-reporter'
						),
						report.number
					)
				);
				onDone( answer.reports );
			} )
			.catch( ( e ) => {
				toast( e.message || String( e ), 'error' );
				setSending( false );
			} );
	};

	return (
		<form className={ P + 'row__comment' } onSubmit={ send }>
			<Field
				id={ id }
				label={ sprintf(
					/* translators: %d: issue number. */
					__( 'Add to #%d', 'nhrrob-issue-reporter' ),
					report.number
				) }
				help={ __(
					'Sent to GitHub as typed. Markdown works.',
					'nhrrob-issue-reporter'
				) }
			>
				<textarea
					id={ id }
					className={ P + 'input' }
					rows={ 3 }
					required
					value={ body }
					onChange={ ( e ) => setBody( e.target.value ) }
				/>
			</Field>
			<div className={ P + 'row__actions' }>
				<Button variant="ghost" onClick={ onCancel }>
					{ __( 'Cancel', 'nhrrob-issue-reporter' ) }
				</Button>
				<Button
					variant="primary"
					type="submit"
					disabled={ sending || ! body.trim() }
				>
					{ sending
						? __( 'Sending…', 'nhrrob-issue-reporter' )
						: __( 'Post comment', 'nhrrob-issue-reporter' ) }
				</Button>
			</div>
		</form>
	);
}
