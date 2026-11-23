/**
 * New report: the form that opens an issue, with a live preview and the
 * similar open issues beside it.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import api from '../api';
import { useToast } from '../components/ToastProvider';
import TargetPicker from '../components/TargetPicker';
import Shots, { TYPES, MAX_FILES } from '../components/Shots';
import EnvCards, { rows } from '../components/EnvCards';
import {
	Button,
	External,
	Icon,
	Note,
	Panel,
	ScreenHeader,
	Step,
} from '../components/ui';

const P = 'nhrrob-issue-reporter-';
const MAX_BYTES = 5 * 1024 * 1024;
const DRAFT_KEY = 'nhrrob-issue-reporter-draft';

// The line about what is reported, at the top of the Environment. Sent as
// is, so it is not translated, like the rest of the environment text.
const LINE = {
	plugin: 'Plugin',
	'mu-plugin': 'Must-use plugin',
	theme: 'Theme',
};

const TOOLS = [
	[ 'bold', __( 'Bold', 'nhrrob-issue-reporter' ), '**', '**' ],
	[ 'italic', __( 'Italic', 'nhrrob-issue-reporter' ), '_', '_' ],
	[ 'code', __( 'Code', 'nhrrob-issue-reporter' ), '`', '`' ],
	[ 'list', __( 'List', 'nhrrob-issue-reporter' ), '\n- ', '' ],
	[ 'link', __( 'Link', 'nhrrob-issue-reporter' ), '[', '](https://)' ],
];

function readDraft() {
	try {
		return JSON.parse( window.localStorage.getItem( DRAFT_KEY ) ) || {};
	} catch ( e ) {
		return {};
	}
}

function writeDraft( draft ) {
	try {
		if ( draft ) {
			window.localStorage.setItem( DRAFT_KEY, JSON.stringify( draft ) );
		} else {
			window.localStorage.removeItem( DRAFT_KEY );
		}
	} catch ( e ) {
		/* storage unavailable: no draft is kept */
	}
}

export default function Report( { boot, data, setData, navigate } ) {
	const toast = useToast();
	const targets = data.plugins.filter( ( p ) => p.repo || p.route );
	const draft = useRef( readDraft() ).current;
	const preset =
		targets.find( ( p ) => p.file === boot.plugin ) ||
		targets.find( ( p ) => p.file === draft.file );

	const [ file, setFile ] = useState( preset ? preset.file : '' );
	const [ title, setTitle ] = useState( draft.title || '' );
	const [ what, setWhat ] = useState( draft.what || '' );
	const [ steps, setSteps ] = useState( draft.steps || '' );
	const [ environment, setEnvironment ] = useState( data.environment );
	const [ asText, setAsText ] = useState( false );
	const [ off, setOff ] = useState( [] );
	const [ similar, setSimilar ] = useState( null );
	const [ searching, setSearching ] = useState( false );
	const [ sending, setSending ] = useState( false );
	const [ commenting, setCommenting ] = useState( 0 );
	// Screenshots: { file, preview, id } where id is set once uploaded.
	const [ images, setImages ] = useState( [] );
	const [ allowAddress, setAllowAddress ] = useState( false );
	const whatRef = useRef();
	const formRef = useRef();

	const target = targets.find( ( p ) => p.file === file );
	// A target with a route goes to another tracker and needs no GitHub token.
	const canSend = ( t ) => !! t && ( !! t.route || data.hasToken );
	const ready = targets.some( canSend ) && ( ! target || canSend( target ) );
	const routed = targets.some( ( t ) => t.route );

	// Until edited as text, the environment follows the chosen target, so
	// its own line is part of what is shown and sent.
	useEffect( () => {
		if ( ! asText ) {
			setEnvironment(
				( target && LINE[ target.type ]
					? '- ' +
					  LINE[ target.type ] +
					  ': ' +
					  target.name +
					  ' ' +
					  target.version +
					  '\n'
					: '' ) + data.environment
			);
		}
	}, [ target, asText, data.environment ] );

	// What is sent: the text as typed, or the lines that are switched on.
	const sentEnvironment = asText
		? environment
		: rows( environment )
				.filter( ( r ) => ! off.includes( r.key ) )
				.map( ( r ) => r.line )
				.join( '\n' );

	// The typed text is kept as a draft in this browser.
	const hasDraft = !! ( title || what || steps );
	useEffect( () => {
		writeDraft( hasDraft ? { file, title, what, steps } : null );
	}, [ file, title, what, steps, hasDraft ] );

	const lookUp = useCallback(
		( forFile, forTitle ) => {
			const to = targets.find( ( t ) => t.file === forFile );
			if (
				! data.hasToken ||
				! to ||
				to.route ||
				forTitle.trim().length < 4
			) {
				setSimilar( null );
				return;
			}
			setSearching( true );
			api( '/similar', 'POST', { plugin: forFile, title: forTitle } )
				.then( ( answer ) => setSimilar( answer.issues ) )
				.catch( () => setSimilar( null ) )
				.finally( () => setSearching( false ) );
		},
		[ data.hasToken, targets ]
	);

	const addImages = ( files ) => {
		const picked = Array.from( files || [] ).filter( ( f ) =>
			TYPES.includes( f.type )
		);
		if ( picked.some( ( f ) => f.size > MAX_BYTES ) ) {
			toast(
				__(
					'A screenshot can be at most 5 MB.',
					'nhrrob-issue-reporter'
				),
				'error'
			);
		}
		setImages( ( current ) =>
			[
				...current,
				...picked
					.filter( ( f ) => f.size <= MAX_BYTES )
					.map( ( f ) => ( {
						file: f,
						preview: window.URL.createObjectURL( f ),
						id: 0,
					} ) ),
			].slice( 0, MAX_FILES )
		);
	};

	const removeImage = ( image ) => {
		window.URL.revokeObjectURL( image.preview );
		setImages( ( current ) => current.filter( ( i ) => i !== image ) );
	};

	// Uploaded one by one when the report is sent, so a draft that is left
	// behind adds nothing to the media library. An image uploaded before a
	// failed send keeps its id and is not uploaded again.
	const upload = () =>
		images.reduce(
			( chain, image ) =>
				chain.then( ( ids ) => {
					if ( image.id ) {
						return [ ...ids, image.id ];
					}
					const body = new window.FormData();
					body.append( 'file', image.file, image.file.name );
					return apiFetch( {
						path: 'nhrrob-issue-reporter/v1/screenshots',
						method: 'POST',
						body,
					} ).then( ( answer ) => {
						image.id = answer.id;
						return [ ...ids, answer.id ];
					} );
				} ),
			Promise.resolve( [] )
		);

	const withImages = ( fields ) =>
		upload().then( ( ids ) =>
			ids.length
				? {
						...fields,
						screenshots: ids,
						allowSiteAddress: allowAddress,
				  }
				: fields
		);

	const blocked = images.length > 0 && ! allowAddress;

	const clear = () => {
		images.forEach( ( i ) => window.URL.revokeObjectURL( i.preview ) );
		setTitle( '' );
		setWhat( '' );
		setSteps( '' );
		setImages( [] );
		setAllowAddress( false );
		setSimilar( null );
	};

	const send = ( event ) => {
		if ( event ) {
			event.preventDefault();
		}
		if ( ! ready || sending || blocked || ! file || ! title || ! what ) {
			return;
		}
		setSending( true );
		withImages( {
			plugin: file,
			title,
			what,
			steps,
			environment: sentEnvironment,
		} )
			.then( ( fields ) => api( '/reports', 'POST', fields ) )
			.then( ( answer ) => {
				setData( { ...data, reports: answer.reports } );
				writeDraft( null );
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
				navigate( 'sent' );
			} )
			.catch( ( e ) => {
				toast( e.message || String( e ), 'error' );
				setSending( false );
			} );
	};

	// The typed text and environment go to an existing issue instead.
	const comment = ( number ) => {
		if ( ! what.trim() ) {
			toast(
				__(
					'Type what happened first: it becomes the comment.',
					'nhrrob-issue-reporter'
				),
				'error'
			);
			return;
		}
		if ( blocked ) {
			toast(
				__(
					'Tick the box under the screenshots first.',
					'nhrrob-issue-reporter'
				),
				'error'
			);
			return;
		}
		setCommenting( number );
		withImages( {
			plugin: file,
			number,
			what,
			steps,
			environment: sentEnvironment,
		} )
			.then( ( fields ) => api( '/comments', 'POST', fields ) )
			.then( ( answer ) => {
				setData( { ...data, reports: answer.reports } );
				toast(
					sprintf(
						/* translators: %d: issue number. */
						__(
							'Comment added to issue #%d',
							'nhrrob-issue-reporter'
						),
						number
					)
				);
			} )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) )
			.finally( () => setCommenting( 0 ) );
	};

	// Wraps the selection in the description with Markdown.
	const format = ( before, after ) => {
		const el = whatRef.current;
		if ( ! el ) {
			return;
		}
		const start = el.selectionStart;
		const end = el.selectionEnd;
		const next =
			what.slice( 0, start ) +
			before +
			what.slice( start, end ) +
			after +
			what.slice( end );
		setWhat( next );
		window.requestAnimationFrame( () => {
			el.focus();
			el.setSelectionRange( start + before.length, end + before.length );
		} );
	};

	// ⌘↵ or Ctrl+↵ anywhere in the form sends it. The latest send() is read
	// through a ref so the listener is added once.
	const sendRef = useRef( send );
	sendRef.current = send;
	useEffect( () => {
		const onKey = ( e ) => {
			if (
				e.key === 'Enter' &&
				( e.metaKey || e.ctrlKey ) &&
				formRef.current &&
				formRef.current.contains( e.target )
			) {
				sendRef.current( e );
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [] );

	const isMac = /Mac|iPhone|iPad/.test( window.navigator.platform || '' );

	return (
		<>
			<ScreenHeader
				title={ __( 'New report', 'nhrrob-issue-reporter' ) }
				lede={ __(
					'Describe what went wrong. The versions a developer asks for are added for you, and you see all of it before it is sent.',
					'nhrrob-issue-reporter'
				) }
			/>

			{ ! data.hasToken && ! routed && (
				<div className={ P + 'banner' }>
					<span className={ P + 'tile ' + P + 'tile--warn' }>
						<Icon name="link" size={ 18 } />
					</span>
					<span className={ P + 'banner__text' }>
						<strong>
							{ __(
								'Connect GitHub to send reports',
								'nhrrob-issue-reporter'
							) }
						</strong>
						<span>
							{ __(
								'One token with “Issues: Read and write”. It takes about a minute.',
								'nhrrob-issue-reporter'
							) }
						</span>
					</span>
					<Button
						variant="primary"
						onClick={ () => navigate( 'settings' ) }
					>
						{ __( 'Connect GitHub', 'nhrrob-issue-reporter' ) }
					</Button>
				</div>
			) }
			{ data.hasToken && ! targets.length && (
				<Note tone="warn">
					{ __(
						'No plugin has a repository yet.',
						'nhrrob-issue-reporter'
					) }{ ' ' }
					<Button small onClick={ () => navigate( 'settings' ) }>
						{ __( 'Set repositories', 'nhrrob-issue-reporter' ) }
					</Button>
				</Note>
			) }

			<div className={ P + 'split' }>
				<form
					ref={ formRef }
					className={ P + 'panel ' + P + 'form' }
					onSubmit={ send }
					onPaste={ ( e ) => {
						if ( e.clipboardData && e.clipboardData.files.length ) {
							addImages( e.clipboardData.files );
						}
					} }
				>
					<Step
						n={ 1 }
						title={ __( 'Where it goes', 'nhrrob-issue-reporter' ) }
					>
						<label
							className="screen-reader-text"
							htmlFor={ P + 'target' }
						>
							{ __(
								'Plugin, theme or repository',
								'nhrrob-issue-reporter'
							) }
						</label>
						<TargetPicker
							targets={ targets }
							value={ file }
							onChange={ ( next ) => {
								setFile( next );
								lookUp( next, title );
							} }
						/>
					</Step>

					<Step
						n={ 2 }
						title={ __( 'What happened', 'nhrrob-issue-reporter' ) }
					>
						<label
							className="screen-reader-text"
							htmlFor={ P + 'title' }
						>
							{ __( 'Title', 'nhrrob-issue-reporter' ) }
						</label>
						<input
							id={ P + 'title' }
							className={ P + 'input ' + P + 'input--title' }
							type="text"
							maxLength={ 200 }
							required
							placeholder={ __(
								'A short title, e.g. “Referrers list shows the same host twice”',
								'nhrrob-issue-reporter'
							) }
							value={ title }
							onChange={ ( e ) => setTitle( e.target.value ) }
							onBlur={ () => lookUp( file, title ) }
						/>
						<div className={ P + 'editor' }>
							<div
								className={ P + 'editor__bar' }
								role="toolbar"
								aria-label={ __(
									'Formatting',
									'nhrrob-issue-reporter'
								) }
							>
								{ TOOLS.map( ( [ id, label, a, b ] ) => (
									<button
										key={ id }
										type="button"
										className={ P + 'editor__tool' }
										onClick={ () => format( a, b ) }
									>
										{ label }
									</button>
								) ) }
								<span className={ P + 'editor__md' }>
									Markdown
								</span>
							</div>
							<label
								className="screen-reader-text"
								htmlFor={ P + 'what' }
							>
								{ __(
									'What happened',
									'nhrrob-issue-reporter'
								) }
							</label>
							<textarea
								id={ P + 'what' }
								ref={ whatRef }
								className={ P + 'editor__area' }
								rows={ 5 }
								required
								placeholder={ __(
									'What you saw, and what you expected.',
									'nhrrob-issue-reporter'
								) }
								value={ what }
								onChange={ ( e ) => setWhat( e.target.value ) }
							/>
						</div>
						<label
							className={ P + 'field__label' }
							htmlFor={ P + 'steps' }
						>
							{ __(
								'Steps to reproduce',
								'nhrrob-issue-reporter'
							) }
						</label>
						<textarea
							id={ P + 'steps' }
							className={ P + 'input' }
							rows={ 4 }
							placeholder={ '1. …\n2. …' }
							value={ steps }
							onChange={ ( e ) => setSteps( e.target.value ) }
						/>
					</Step>

					<Step
						n={ 3 }
						title={ __( 'Screenshots', 'nhrrob-issue-reporter' ) }
						hint={ __( 'Optional', 'nhrrob-issue-reporter' ) }
					>
						<Shots
							images={ images }
							onAdd={ addImages }
							onRemove={ removeImage }
							disabled={ sending }
						/>
						{ images.length > 0 && ! data.publicSite && (
							<Note tone="warn">
								{ __(
									'This site does not look reachable from the internet, so GitHub may not be able to show these images. A password-protected site has the same problem.',
									'nhrrob-issue-reporter'
								) }
							</Note>
						) }
						{ images.length > 0 && (
							<label
								className={ P + 'check' }
								htmlFor={ P + 'allow-address' }
							>
								<input
									id={ P + 'allow-address' }
									type="checkbox"
									checked={ allowAddress }
									onChange={ ( e ) =>
										setAllowAddress( e.target.checked )
									}
								/>
								<span>
									{ __(
										'The screenshots are added to this site’s Media Library and linked from the issue, so the issue shows this site’s address. Send them anyway.',
										'nhrrob-issue-reporter'
									) }
								</span>
							</label>
						) }
					</Step>

					<Step
						n={ 4 }
						title={ __( 'Environment', 'nhrrob-issue-reporter' ) }
						hint={
							asText
								? ''
								: __(
										'Switch off anything you don’t want to send',
										'nhrrob-issue-reporter'
								  )
						}
					>
						{ asText ? (
							<>
								<label
									className="screen-reader-text"
									htmlFor={ P + 'environment' }
								>
									{ __(
										'Environment',
										'nhrrob-issue-reporter'
									) }
								</label>
								<textarea
									id={ P + 'environment' }
									className={ P + 'input is-mono' }
									rows={ 6 }
									value={ environment }
									onChange={ ( e ) =>
										setEnvironment( e.target.value )
									}
								/>
							</>
						) : (
							<EnvCards
								text={ environment }
								off={ off }
								onToggle={ ( key ) =>
									setOff(
										off.includes( key )
											? off.filter( ( k ) => k !== key )
											: [ ...off, key ]
									)
								}
							/>
						) }
						<div className={ P + 'env__foot' }>
							<span>
								{ __(
									'Sent exactly as shown. The site address is never added.',
									'nhrrob-issue-reporter'
								) }
							</span>
							{ ! asText && (
								<Button
									small
									variant="link"
									onClick={ () => {
										setEnvironment( sentEnvironment );
										setOff( [] );
										setAsText( true );
									} }
								>
									{ __(
										'Edit as text',
										'nhrrob-issue-reporter'
									) }
								</Button>
							) }
						</div>
					</Step>

					<div className={ P + 'form__foot' }>
						<span className={ P + 'form__where' }>
							{ target ? (
								<>
									{ __(
										'Opens an issue in',
										'nhrrob-issue-reporter'
									) }{ ' ' }
									<strong>
										{ target.route || target.repo }
									</strong>
								</>
							) : (
								__(
									'Choose where it goes first.',
									'nhrrob-issue-reporter'
								)
							) }
							{ hasDraft && (
								<span className={ P + 'form__draft' }>
									<Icon name="check" size={ 13 } />
									{ __(
										'Draft kept in this browser',
										'nhrrob-issue-reporter'
									) }
								</span>
							) }
						</span>
						{ hasDraft && (
							<Button
								variant="ghost"
								disabled={ sending }
								onClick={ clear }
							>
								{ __( 'Clear', 'nhrrob-issue-reporter' ) }
							</Button>
						) }
						<Button
							variant="primary"
							type="submit"
							disabled={ ! ready || sending || blocked }
						>
							{ sending
								? __( 'Sending…', 'nhrrob-issue-reporter' )
								: __( 'Send report', 'nhrrob-issue-reporter' ) }
							<kbd className={ P + 'kbd' }>
								{ isMac ? '⌘↵' : 'Ctrl ↵' }
							</kbd>
						</Button>
					</div>
				</form>

				<div className={ P + 'aside' }>
					<Panel
						title={ __( 'Live preview', 'nhrrob-issue-reporter' ) }
						meta={ __(
							'as it will look on GitHub',
							'nhrrob-issue-reporter'
						) }
					>
						<div className={ P + 'preview' }>
							<span className={ P + 'preview__repo' }>
								{ target
									? target.route || target.repo
									: __(
											'No repository chosen',
											'nhrrob-issue-reporter'
									  ) }
							</span>
							<strong className={ P + 'preview__title' }>
								{ title ||
									__(
										'Your title appears here',
										'nhrrob-issue-reporter'
									) }
							</strong>
							{ [
								[
									__(
										'What happened',
										'nhrrob-issue-reporter'
									),
									what,
								],
								[
									__(
										'Steps to reproduce',
										'nhrrob-issue-reporter'
									),
									steps,
								],
								[
									__(
										'Screenshots',
										'nhrrob-issue-reporter'
									),
									images.length
										? sprintf(
												/* translators: %d: number of images. */
												_n(
													'%d linked image',
													'%d linked images',
													images.length,
													'nhrrob-issue-reporter'
												),
												images.length
										  )
										: '',
								],
								[
									__(
										'Environment',
										'nhrrob-issue-reporter'
									),
									sentEnvironment,
								],
							]
								.filter( ( [ , text ] ) => text.trim() )
								.map( ( [ heading, text ] ) => (
									<div
										key={ heading }
										className={ P + 'preview__part' }
									>
										<span
											className={ P + 'preview__heading' }
										>
											{ heading }
										</span>
										<span className={ P + 'preview__text' }>
											{ text }
										</span>
									</div>
								) ) }
							<p className={ P + 'muted' }>
								{ __(
									'If the repository is public, anyone can read the issue.',
									'nhrrob-issue-reporter'
								) }
							</p>
						</div>
					</Panel>

					<Panel
						title={ __(
							'Possible duplicates',
							'nhrrob-issue-reporter'
						) }
						meta={
							searching
								? __( 'Searching…', 'nhrrob-issue-reporter' )
								: ''
						}
						actions={
							similar &&
							similar.length > 0 && (
								<span className={ P + 'count' }>
									{ similar.length }
								</span>
							)
						}
					>
						{ similar === null && (
							<p className={ P + 'muted' }>
								{ __(
									'Choose where it goes and type a title. Open issues with a similar title show up here, so you can add to one instead of opening another.',
									'nhrrob-issue-reporter'
								) }
							</p>
						) }
						{ similar && ! similar.length && (
							<p className={ P + 'similar__none' }>
								<Icon name="check" size={ 16 } />
								{ __(
									'No open issue has a similar title.',
									'nhrrob-issue-reporter'
								) }
							</p>
						) }
						{ similar && similar.length > 0 && (
							<ul className={ P + 'similar' }>
								{ similar.map( ( issue ) => (
									<li key={ issue.number }>
										<External href={ issue.url }>
											<span
												className={
													P + 'similar__number'
												}
											>
												#{ issue.number }
											</span>
											{ issue.title }
										</External>
										<Button
											small
											disabled={
												!! commenting ||
												sending ||
												blocked
											}
											onClick={ () =>
												comment( issue.number )
											}
										>
											{ commenting === issue.number
												? __(
														'Sending…',
														'nhrrob-issue-reporter'
												  )
												: __(
														'Comment instead',
														'nhrrob-issue-reporter'
												  ) }
										</Button>
									</li>
								) ) }
							</ul>
						) }
					</Panel>
				</div>
			</div>
		</>
	);
}
