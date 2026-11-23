/**
 * One piece of feedback in the Inbox: what was captured, and the report it
 * becomes. Everything that is sent is shown before sending and the text can
 * be edited. The page, the screenshot and the console errors can show the
 * site's address, so they go only with the tick.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import api from '../api';
import TargetPicker from '../components/TargetPicker';
import { useToast } from '../components/ToastProvider';
import { Button, External, Field, Icon, Segmented } from '../components/ui';

const P = 'nhrrob-issue-reporter-';

export const KIND_LABEL = {
	bug: __( 'Bug', 'nhrrob-issue-reporter' ),
	change: __( 'Change', 'nhrrob-issue-reporter' ),
	question: __( 'Question', 'nhrrob-issue-reporter' ),
};

// GitHub's default labels for each kind; severity as "severity: …". Sent as
// is, so not translated.
const KIND_GITHUB = { bug: 'bug', change: 'enhancement', question: 'question' };

// The line about what is reported, at the top of the Environment, as in New report.
const LINE = {
	plugin: 'Plugin',
	'mu-plugin': 'Must-use plugin',
	theme: 'Theme',
};

/**
 * How long ago, in words.
 *
 * @param {number} time Unix time.
 * @return {string} Such as "3 hours ago".
 */
export function ago( time ) {
	const s = Math.max( 0, Date.now() / 1000 - time );
	if ( s < 3600 ) {
		const m = Math.max( 1, Math.round( s / 60 ) );
		return sprintf(
			/* translators: %d: minutes. */
			_n( '%d minute ago', '%d minutes ago', m, 'nhrrob-issue-reporter' ),
			m
		);
	}
	if ( s < 86400 ) {
		const h = Math.round( s / 3600 );
		return sprintf(
			/* translators: %d: hours. */
			_n( '%d hour ago', '%d hours ago', h, 'nhrrob-issue-reporter' ),
			h
		);
	}
	const d = Math.round( s / 86400 );
	return sprintf(
		/* translators: %d: days. */
		_n( '%d day ago', '%d days ago', d, 'nhrrob-issue-reporter' ),
		d
	);
}

/**
 * A title from feedback: its first sentence, at most 80 characters, cut at a word.
 *
 * @param {string} text Feedback.
 * @return {string} Title.
 */
export function firstSentence( text ) {
	const first = text
		.trim()
		.split( '\n' )[ 0 ]
		.split( /(?<=[.!?])\s/ )[ 0 ];
	if ( first.length <= 80 ) {
		return first;
	}
	const cut = first.slice( 0, 80 );
	return (
		cut.slice(
			0,
			cut.lastIndexOf( ' ' ) > 40 ? cut.lastIndexOf( ' ' ) : 80
		) + '…'
	);
}

export default function InboxItem( {
	item,
	data,
	setData,
	onChange,
	onDelete,
	onSent,
	navigate,
} ) {
	const toast = useToast();
	// A target with a route goes to another tracker and needs no GitHub token.
	const targets = data.plugins.filter(
		( p ) => p.route || ( p.repo && data.hasToken )
	);
	const [ file, setFile ] = useState( '' );
	const [ title, setTitle ] = useState( firstSentence( item.text ) );
	const [ text, setText ] = useState( item.text );
	const [ withSite, setWithSite ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ sure, setSure ] = useState( false );

	const target = targets.find( ( p ) => p.file === file );
	const siteBits = !! ( item.shot || item.page || item.errors.length );

	// What happened: the text, then the page and errors when allowed.
	const what = [
		text.trim(),
		withSite && item.page ? 'Page: `' + item.page + '`' : '',
		withSite && item.errors.length
			? 'Console errors:\n\n```\n' + item.errors.join( '\n' ) + '\n```'
			: '',
	]
		.filter( Boolean )
		.join( '\n\n' );

	const environment = [
		target && LINE[ target.type ]
			? '- ' +
			  LINE[ target.type ] +
			  ': ' +
			  target.name +
			  ' ' +
			  target.version
			: '',
		data.environment,
		item.browser ? '- Browser: ' + item.browser : '',
		item.screen ? '- Screen: ' + item.screen : '',
	]
		.filter( Boolean )
		.join( '\n' );

	const labels = [
		KIND_GITHUB[ item.kind ],
		item.severity ? 'severity: ' + item.severity : '',
	].filter( Boolean );

	const change = ( fields ) =>
		api( '/feedback/' + item.id, 'POST', fields )
			.then( ( answer ) => onChange( answer.item ) )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) );

	const send = () => {
		if ( ! file || ! title.trim() || ! text.trim() || busy ) {
			return;
		}
		setBusy( true );
		const fields = {
			plugin: file,
			title,
			what,
			environment,
			labels,
			feedback: item.id,
		};
		if ( withSite && item.shot ) {
			fields.screenshots = [ item.shot.id ];
			fields.allowSiteAddress = true;
		}
		api( '/reports', 'POST', fields )
			.then( ( answer ) => {
				setData( ( d ) => ( { ...d, reports: answer.reports } ) );
				toast(
					sprintf(
						/* translators: 1: issue number, 2: repository such as owner/name. */
						__(
							'Report sent: issue #%1$d in %2$s',
							'nhrrob-issue-reporter'
						),
						answer.report.number,
						answer.report.repo
					)
				);
				onSent();
			} )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) )
			.finally( () => setBusy( false ) );
	};

	const remove = () =>
		api( '/feedback/' + item.id, 'DELETE' )
			.then( () => {
				toast( __( 'Feedback deleted', 'nhrrob-issue-reporter' ) );
				onDelete();
			} )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) );

	const facts = [
		[ 'user', __( 'From', 'nhrrob-issue-reporter' ), item.who ],
		[ 'globe', __( 'Page', 'nhrrob-issue-reporter' ), item.page ],
		[ 'code', __( 'Browser', 'nhrrob-issue-reporter' ), item.browser ],
		[ 'screen', __( 'Screen', 'nhrrob-issue-reporter' ), item.screen ],
		[ 'clock', __( 'Sent', 'nhrrob-issue-reporter' ), ago( item.time ) ],
	].filter( ( f ) => f[ 2 ] );

	return (
		<section className={ P + 'panel ' + P + 'inbox__item' }>
			<header className={ P + 'inbox__head' }>
				<span className={ P + 'inbox__avatar' } aria-hidden="true">
					{ ( item.who || '?' ).charAt( 0 ).toUpperCase() }
				</span>
				<strong className={ P + 'inbox__title' }>
					{ firstSentence( item.text ) }
				</strong>
				{ item.issue && (
					<External href={ item.issue.url }>
						{ item.issue.repo + '#' + item.issue.number }
					</External>
				) }
			</header>

			<div className={ P + 'inbox__body' }>
				{ item.shot && (
					<a
						className={ P + 'inbox__shot' }
						href={ item.shot.url }
						target="_blank"
						rel="noopener noreferrer"
					>
						<img
							src={ item.shot.url }
							alt={ __(
								'Screenshot sent with the feedback',
								'nhrrob-issue-reporter'
							) }
						/>
						<span>
							{ __( 'Open full size', 'nhrrob-issue-reporter' ) }
						</span>
					</a>
				) }

				<dl className={ P + 'inbox__facts' }>
					{ facts.map( ( [ icon, label, value ] ) => (
						<div key={ label }>
							<dt>
								<Icon name={ icon } size={ 15 } />
								{ label }
							</dt>
							<dd>{ value }</dd>
						</div>
					) ) }
				</dl>

				{ item.errors.length > 0 && (
					<div className={ P + 'inbox__console' }>
						<span>
							<Icon name="alert" size={ 15 } />
							{ sprintf(
								/* translators: %d: number of console errors. */
								_n(
									'Console · %d error',
									'Console · %d errors',
									item.errors.length,
									'nhrrob-issue-reporter'
								),
								item.errors.length
							) }
						</span>
						<pre>{ item.errors.join( '\n' ) }</pre>
					</div>
				) }

				<div className={ P + 'inbox__labels' }>
					<Segmented
						label={ __( 'Kind', 'nhrrob-issue-reporter' ) }
						value={ item.kind }
						onChange={ ( kind ) => change( { kind } ) }
						options={ Object.keys( KIND_LABEL ).map( ( k ) => ( {
							value: k,
							label: KIND_LABEL[ k ],
						} ) ) }
					/>
					<Segmented
						label={ __( 'Severity', 'nhrrob-issue-reporter' ) }
						value={ item.severity }
						onChange={ ( severity ) => change( { severity } ) }
						options={ [
							{
								value: '',
								label: __(
									'No severity',
									'nhrrob-issue-reporter'
								),
							},
							{
								value: 'minor',
								label: __( 'Minor', 'nhrrob-issue-reporter' ),
							},
							{
								value: 'major',
								label: __( 'Major', 'nhrrob-issue-reporter' ),
							},
							{
								value: 'critical',
								label: __(
									'Critical',
									'nhrrob-issue-reporter'
								),
							},
						] }
					/>
				</div>

				{ item.status !== 'sent' && (
					<div className={ P + 'inbox__send' }>
						<h3>
							{ __(
								'Send as an issue',
								'nhrrob-issue-reporter'
							) }
						</h3>
						{ targets.length === 0 ? (
							<p className={ P + 'muted' }>
								{ __(
									'Connect GitHub and set a repository in Settings to send feedback as an issue.',
									'nhrrob-issue-reporter'
								) }{ ' ' }
								<Button
									variant="link"
									onClick={ () => navigate( 'settings' ) }
								>
									{ __(
										'Open Settings',
										'nhrrob-issue-reporter'
									) }
								</Button>
							</p>
						) : (
							<>
								<Field
									id={ P + 'inbox-to' }
									label={ __(
										'Send to',
										'nhrrob-issue-reporter'
									) }
								>
									<TargetPicker
										targets={ targets }
										value={ file }
										onChange={ setFile }
									/>
								</Field>
								<Field
									id={ P + 'inbox-title' }
									label={ __(
										'Title',
										'nhrrob-issue-reporter'
									) }
								>
									<input
										id={ P + 'inbox-title' }
										className={ P + 'input' }
										value={ title }
										maxLength="200"
										onChange={ ( e ) =>
											setTitle( e.target.value )
										}
									/>
								</Field>
								<Field
									id={ P + 'inbox-text' }
									label={ __(
										'What happened',
										'nhrrob-issue-reporter'
									) }
								>
									<textarea
										id={ P + 'inbox-text' }
										className={ P + 'input' }
										rows="4"
										value={ text }
										onChange={ ( e ) =>
											setText( e.target.value )
										}
									/>
								</Field>
								{ siteBits && (
									<label
										className={ P + 'check' }
										htmlFor={ P + 'inbox-site' }
									>
										<input
											id={ P + 'inbox-site' }
											type="checkbox"
											checked={ withSite }
											onChange={ ( e ) =>
												setWithSite( e.target.checked )
											}
										/>
										<span>
											{ __(
												'Include the page, the screenshot and the console errors. They can show this site’s address, and the repository may be public.',
												'nhrrob-issue-reporter'
											) }
										</span>
									</label>
								) }
								<details className={ P + 'inbox__preview' }>
									<summary>
										{ __(
											'What will be sent',
											'nhrrob-issue-reporter'
										) }
									</summary>
									<pre>
										{ [
											'### ' + title,
											what,
											withSite && item.shot
												? __(
														'(the screenshot, linked)',
														'nhrrob-issue-reporter'
												  )
												: '',
											environment,
											labels.length
												? 'Labels: ' +
												  labels.join( ', ' )
												: '',
										]
											.filter( Boolean )
											.join( '\n\n' ) }
									</pre>
								</details>
							</>
						) }
					</div>
				) }
			</div>

			<footer className={ P + 'inbox__foot' }>
				{ sure ? (
					<>
						<span className={ P + 'muted' }>
							{ __(
								'Delete it and its screenshot?',
								'nhrrob-issue-reporter'
							) }
						</span>
						<Button variant="danger" small onClick={ remove }>
							{ __( 'Delete', 'nhrrob-issue-reporter' ) }
						</Button>
						<Button
							variant="ghost"
							small
							onClick={ () => setSure( false ) }
						>
							{ __( 'Keep', 'nhrrob-issue-reporter' ) }
						</Button>
					</>
				) : (
					<Button
						variant="ghost"
						small
						onClick={ () => setSure( true ) }
					>
						{ __( 'Delete', 'nhrrob-issue-reporter' ) }
					</Button>
				) }
				<span className={ P + 'top__spacer' } />
				{ item.status === 'done' ? (
					<Button onClick={ () => change( { status: 'new' } ) }>
						{ __( 'Move back to New', 'nhrrob-issue-reporter' ) }
					</Button>
				) : (
					<Button onClick={ () => change( { status: 'done' } ) }>
						{ __( 'Mark done', 'nhrrob-issue-reporter' ) }
					</Button>
				) }
				{ item.status !== 'sent' && targets.length > 0 && (
					<Button
						variant="primary"
						onClick={ send }
						disabled={
							busy || ! file || ! title.trim() || ! text.trim()
						}
					>
						{ busy
							? __( 'Sending…', 'nhrrob-issue-reporter' )
							: __(
									'Send as an issue',
									'nhrrob-issue-reporter'
							  ) }
					</Button>
				) }
			</footer>
		</section>
	);
}
