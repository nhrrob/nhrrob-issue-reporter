/**
 * Sent: the issues opened from this site, with their state on GitHub.
 */
import { Fragment, useState, useEffect, useCallback } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

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
} from '../components/ui';

const P = 'nhrrob-issue-reporter-';
const KEY = 'nhrrob-issue-reporter-sent-repo';

// The chosen filter survives a reload. Storage can be blocked; then it does not.
function remembered() {
	try {
		return window.localStorage.getItem( KEY ) || '';
	} catch ( e ) {
		return '';
	}
}

function remember( repo ) {
	try {
		window.localStorage.setItem( KEY, repo );
	} catch ( e ) {}
}

export default function Sent( { data, setData, navigate } ) {
	const toast = useToast();
	const [ loading, setLoading ] = useState( false );
	const [ repo, setRepo ] = useState( remembered );
	const [ open, setOpen ] = useState( '' );

	// Grouped by repository, so a renamed plugin keeps its reports together.
	const groups = {};
	data.reports.forEach( ( r ) => {
		groups[ r.repo ] = groups[ r.repo ] || { name: r.plugin, count: 0 };
		groups[ r.repo ].count++;
	} );
	const chosen = groups[ repo ] ? repo : '';
	const reports = data.reports.filter(
		( r ) => ! chosen || r.repo === chosen
	);
	const openCount = reports.filter( ( r ) => r.state !== 'closed' ).length;
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
					'Every issue opened from this site. The text of a report is on GitHub; only its link is kept here.',
					'nhrrob-issue-reporter'
				) }
			/>
			<Panel
				title={ __( 'Issues', 'nhrrob-issue-reporter' ) }
				icon="sent"
				flush
				meta={
					data.reports.length > 0 &&
					sprintf(
						/* translators: 1: number of open issues, 2: number of closed issues. */
						__( '%1$d open, %2$d closed', 'nhrrob-issue-reporter' ),
						openCount,
						reports.length - openCount
					)
				}
				actions={
					<Button small disabled={ loading } onClick={ refresh }>
						<Icon name="refresh" size={ 14 } />
						{ loading
							? __( 'Checking…', 'nhrrob-issue-reporter' )
							: __( 'Check states', 'nhrrob-issue-reporter' ) }
					</Button>
				}
			>
				{ ! data.reports.length ? (
					<div className={ P + 'empty' }>
						<p>
							{ __(
								'No report has been sent from this site yet.',
								'nhrrob-issue-reporter'
							) }
						</p>
						<Button
							variant="soft"
							onClick={ () => navigate( 'report' ) }
						>
							{ __( 'Report an issue', 'nhrrob-issue-reporter' ) }
						</Button>
					</div>
				) : (
					<div className={ P + 'scroll' }>
						<div className={ P + 'filter' }>
							<label htmlFor={ P + 'sent-repo' }>
								{ __( 'Show', 'nhrrob-issue-reporter' ) }
							</label>
							<select
								id={ P + 'sent-repo' }
								className={ P + 'input' }
								value={ chosen }
								onChange={ ( e ) => {
									setRepo( e.target.value );
									remember( e.target.value );
								} }
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
						<table className={ P + 'grid' }>
							<thead>
								<tr>
									<th scope="col">
										{ __(
											'Issue',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										{ __(
											'Plugin',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										{ __(
											'Repository',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										{ __(
											'State',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										{ __(
											'Reported',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										<span className="screen-reader-text">
											{ __(
												'Comment',
												'nhrrob-issue-reporter'
											) }
										</span>
									</th>
								</tr>
							</thead>
							<tbody>
								{ reports.map( ( r ) => (
									<Fragment key={ r.repo + r.number }>
										<tr>
											<td className="is-wrap">
												<External href={ r.url }>
													<span
														className={
															P +
															'similar__number'
														}
													>
														#{ r.number }
													</span>
													{ r.title }
												</External>
												{ r.comments > 0 && (
													<span
														className={ P + 'sub' }
													>
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
											</td>
											<td className="is-wrap">
												{ r.plugin }
											</td>
											<td className="is-mono">
												{ r.repo }
											</td>
											<td>
												{ r.state === 'closed' ? (
													<Pill tone="off">
														{ __(
															'Closed',
															'nhrrob-issue-reporter'
														) }
													</Pill>
												) : (
													<Pill tone="ok">
														{ __(
															'Open',
															'nhrrob-issue-reporter'
														) }
													</Pill>
												) }
											</td>
											<td>
												{ dateI18n(
													'M j, Y',
													r.time * 1000
												) }
											</td>
											<td>
												{ target( r ) && (
													<Button
														small
														onClick={ () =>
															setOpen(
																open ===
																	r.repo +
																		r.number
																	? ''
																	: r.repo +
																			r.number
															)
														}
													>
														{ __(
															'Add comment',
															'nhrrob-issue-reporter'
														) }
													</Button>
												) }
											</td>
										</tr>
										{ open === r.repo + r.number && (
											<tr className={ P + 'grid__extra' }>
												<td colSpan={ 6 }>
													<CommentBox
														plugin={ target( r ) }
														report={ r }
														onDone={ ( next ) => {
															setOpen( '' );
															setData(
																(
																	current
																) => ( {
																	...current,
																	reports:
																		next,
																} )
															);
														} }
													/>
												</td>
											</tr>
										) }
									</Fragment>
								) ) }
							</tbody>
						</table>
					</div>
				) }
			</Panel>
		</>
	);
}

function CommentBox( { plugin, report, onDone } ) {
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
		<form className={ P + 'form' } onSubmit={ send }>
			<Field
				id={ id }
				label={ sprintf(
					/* translators: %d: issue number. */
					__( 'Comment on #%d', 'nhrrob-issue-reporter' ),
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
					rows={ 4 }
					required
					value={ body }
					onChange={ ( e ) => setBody( e.target.value ) }
				/>
			</Field>
			<div className={ P + 'form__foot' }>
				<Button
					variant="primary"
					type="submit"
					disabled={ sending || ! body.trim() }
				>
					{ sending
						? __( 'Sending…', 'nhrrob-issue-reporter' )
						: __( 'Add comment', 'nhrrob-issue-reporter' ) }
				</Button>
			</div>
		</form>
	);
}
