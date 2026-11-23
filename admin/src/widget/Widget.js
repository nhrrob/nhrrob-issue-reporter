/**
 * The feedback widget: a button, then the screenshot with markup tools and a
 * short form. What is captured is shown as chips, each of which the person
 * can switch off before sending; that is their consent.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { browser, errors, page, screenSize, screenshot } from './capture';
import Markup, { paint } from './Markup';

const THEME_KEY = 'nhrrob-issue-reporter-theme';

const TOOLS = [
	[ 'box', __( 'Box', 'nhrrob-issue-reporter' ), 'M4 6h16v12H4z' ],
	[ 'arrow', __( 'Arrow', 'nhrrob-issue-reporter' ), 'M5 19L19 5M9 5h10v10' ],
	[
		'pen',
		__( 'Draw', 'nhrrob-issue-reporter' ),
		'M3 21c3-1 5-3 7-6l9-9a2 2 0 0 0-3-3l-9 9c-3 2-5 4-6 7z',
	],
	[
		'text',
		__( 'Text', 'nhrrob-issue-reporter' ),
		'M4 7V5h16v2M12 5v14M9 19h6',
	],
	[
		'blur',
		__( 'Blur private details', 'nhrrob-issue-reporter' ),
		'M3 3l18 18M10.6 6.1A9.7 9.7 0 0 1 12 6c5 0 9 6 9 6a17 17 0 0 1-2.4 3M6.6 6.6C4.3 8.1 3 12 3 12s4 6 9 6a9 9 0 0 0 4.4-1.1',
	],
];

const KINDS = [
	[ 'bug', __( 'Bug', 'nhrrob-issue-reporter' ) ],
	[ 'change', __( 'Change', 'nhrrob-issue-reporter' ) ],
	[ 'question', __( 'Question', 'nhrrob-issue-reporter' ) ],
];

function Icon( { d, size = 18 } ) {
	return (
		<svg
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
		>
			<path d={ d } />
		</svg>
	);
}

function storedTheme() {
	try {
		const saved = window.localStorage.getItem( THEME_KEY );
		return saved === 'dark' || saved === 'light' ? saved : '';
	} catch ( e ) {
		return '';
	}
}

/**
 * @param {Object}  root0      Props.
 * @param {Object}  root0.boot Data from PHP: restRoot, nonce, button, name.
 * @param {Element} root0.host The host element, left out of the screenshot.
 * @return {Object} Widget element.
 */
export default function Widget( { boot, host } ) {
	// idle → capturing → open → sending → idle
	const [ phase, setPhase ] = useState( 'idle' );
	const [ base, setBase ] = useState( null );
	const [ shapes, setShapes ] = useState( [] );
	const [ tool, setTool ] = useState( 'box' );
	const [ text, setText ] = useState( '' );
	const [ kind, setKind ] = useState( 'bug' );
	const [ details, setDetails ] = useState( null );
	const [ off, setOff ] = useState( [] );
	const [ notice, setNotice ] = useState( null );
	const [ theme, setTheme ] = useState( storedTheme );
	const textRef = useRef();

	const open = () => {
		if ( phase !== 'idle' ) {
			return;
		}
		setPhase( 'capturing' );
		setNotice( null );
		const found = {
			page: page(),
			browser: browser(),
			screen: screenSize(),
			errors: errors(),
		};
		screenshot( host )
			.then( ( canvas ) => setBase( canvas ) )
			.catch( () => setBase( null ) )
			.finally( () => {
				setDetails( found );
				setShapes( [] );
				setOff( [] );
				setPhase( 'open' );
			} );
	};

	// The admin-bar item opens the widget through this event.
	useEffect( () => {
		const listener = () => open();
		window.addEventListener( 'nhrrob-issue-reporter-open', listener );
		return () =>
			window.removeEventListener(
				'nhrrob-issue-reporter-open',
				listener
			);
	} );

	useEffect( () => {
		if ( phase === 'open' && textRef.current ) {
			textRef.current.focus( { preventScroll: true } );
		}
	}, [ phase ] );

	// Escape closes the widget, unless it is sending.
	useEffect( () => {
		if ( phase !== 'open' ) {
			return;
		}
		const listener = ( e ) => {
			if ( e.key === 'Escape' ) {
				setPhase( 'idle' );
				setBase( null );
			}
		};
		window.addEventListener( 'keydown', listener );
		return () => window.removeEventListener( 'keydown', listener );
	}, [ phase ] );

	const close = () => {
		setPhase( 'idle' );
		setBase( null );
		setText( '' );
		setKind( 'bug' );
	};

	const toggle = ( key ) =>
		setOff(
			off.includes( key )
				? off.filter( ( k ) => k !== key )
				: off.concat( [ key ] )
		);

	const send = ( event ) => {
		event.preventDefault();
		if ( ! text.trim() || phase !== 'open' ) {
			return;
		}
		setPhase( 'sending' );

		const form = new window.FormData();
		form.append( 'text', text );
		form.append( 'kind', kind );
		form.append( 'page', off.includes( 'page' ) ? '' : details.page );
		form.append(
			'browser',
			off.includes( 'browser' ) ? '' : details.browser
		);
		form.append( 'screen', off.includes( 'screen' ) ? '' : details.screen );
		form.append(
			'errors',
			JSON.stringify( off.includes( 'errors' ) ? [] : details.errors )
		);

		const withShot =
			base && ! off.includes( 'shot' )
				? new Promise( ( resolve ) => {
						const flat = document.createElement( 'canvas' );
						paint( flat, base, shapes );
						flat.toBlob( resolve, 'image/png' );
				  } )
				: Promise.resolve( null );

		withShot
			.then( ( blob ) => {
				if ( blob ) {
					form.append( 'file', blob, 'feedback.png' );
				}
				return window.fetch(
					boot.restRoot + 'nhrrob-issue-reporter/v1/feedback',
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'X-WP-Nonce': boot.nonce },
						body: form,
					}
				);
			} )
			.then( ( response ) =>
				response.json().then( ( json ) => {
					if ( ! response.ok ) {
						throw new Error(
							json && json.message
								? json.message
								: __(
										'The feedback was not sent. Please try again.',
										'nhrrob-issue-reporter'
								  )
						);
					}
				} )
			)
			.then( () => {
				close();
				setNotice( {
					ok: true,
					text: __(
						'Thanks! Your feedback was sent.',
						'nhrrob-issue-reporter'
					),
				} );
				window.setTimeout( () => setNotice( null ), 5000 );
			} )
			.catch( ( e ) => {
				setPhase( 'open' );
				setNotice( { ok: false, text: e.message || String( e ) } );
			} );
	};

	const dark = theme
		? theme === 'dark'
		: window.matchMedia &&
		  window.matchMedia( '(prefers-color-scheme: dark)' ).matches;

	const switchTheme = () => {
		const next = dark ? 'light' : 'dark';
		try {
			window.localStorage.setItem( THEME_KEY, next );
		} catch ( e ) {
			/* storage unavailable */
		}
		setTheme( next );
	};

	let chips = [];
	if ( details ) {
		chips = [
			base && [ 'shot', __( 'Screenshot', 'nhrrob-issue-reporter' ) ],
			details.page && [ 'page', details.page ],
			details.browser && [ 'browser', details.browser ],
			[ 'screen', details.screen ],
			details.errors.length > 0 && [
				'errors',
				sprintf(
					/* translators: %d: number of console errors. */
					_n(
						'%d console error',
						'%d console errors',
						details.errors.length,
						'nhrrob-issue-reporter'
					),
					details.errors.length
				),
			],
		].filter( Boolean );
	}

	const busy = phase === 'sending';
	const initial = ( boot.name || '?' ).trim().charAt( 0 ).toUpperCase();

	return (
		<div className="w" data-theme={ dark ? 'dark' : 'light' }>
			{ phase === 'idle' && boot.button && (
				<button type="button" className="w-fab" onClick={ open }>
					<Icon d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
					{ __( 'Feedback', 'nhrrob-issue-reporter' ) }
				</button>
			) }

			{ phase === 'capturing' && (
				<div className="w-pill" role="status">
					{ __( 'Taking a screenshot…', 'nhrrob-issue-reporter' ) }
				</div>
			) }

			{ notice && phase !== 'capturing' && (
				<div
					className={
						'w-pill' + ( notice.ok ? ' is-ok' : ' is-bad' )
					}
					role={ notice.ok ? 'status' : 'alert' }
				>
					{ notice.text }
				</div>
			) }

			{ ( phase === 'open' || phase === 'sending' ) && (
				<div
					className="w-overlay"
					role="dialog"
					aria-modal="true"
					aria-label={ __(
						'Send feedback',
						'nhrrob-issue-reporter'
					) }
				>
					<div className="w-board">
						{ base && ! off.includes( 'shot' ) ? (
							<>
								<div
									className="w-tools"
									role="toolbar"
									aria-label={ __(
										'Mark the screenshot',
										'nhrrob-issue-reporter'
									) }
								>
									{ TOOLS.map( ( [ id, label, d ] ) => (
										<button
											key={ id }
											type="button"
											className={
												'w-tool' +
												( tool === id ? ' is-on' : '' )
											}
											aria-label={ label }
											title={ label }
											aria-pressed={ tool === id }
											onClick={ () => setTool( id ) }
										>
											<Icon d={ d } />
										</button>
									) ) }
									<span className="w-tools__sep" />
									<button
										type="button"
										className="w-tool is-text"
										disabled={ ! shapes.length }
										onClick={ () =>
											setShapes( shapes.slice( 0, -1 ) )
										}
									>
										{ __(
											'Undo',
											'nhrrob-issue-reporter'
										) }
									</button>
								</div>
								<Markup
									base={ base }
									shapes={ shapes }
									setShapes={ setShapes }
									tool={ tool }
								/>
							</>
						) : (
							<p className="w-noshot">
								{ base
									? __(
											'The screenshot is left out.',
											'nhrrob-issue-reporter'
									  )
									: __(
											'A screenshot could not be taken here. You can still send your feedback.',
											'nhrrob-issue-reporter'
									  ) }
							</p>
						) }
					</div>

					<form className="w-panel" onSubmit={ send }>
						<div className="w-panel__head">
							<strong>
								{ __(
									'Send feedback',
									'nhrrob-issue-reporter'
								) }
							</strong>
							<button
								type="button"
								className="w-icon"
								onClick={ switchTheme }
								aria-label={
									dark
										? __(
												'Switch to light theme',
												'nhrrob-issue-reporter'
										  )
										: __(
												'Switch to dark theme',
												'nhrrob-issue-reporter'
										  )
								}
							>
								<Icon
									size={ 16 }
									d={
										dark
											? 'M12 8a4 4 0 100 8 4 4 0 000-8zM12 2v2M12 20v2M2 12h2M20 12h2'
											: 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z'
									}
								/>
							</button>
							<button
								type="button"
								className="w-icon"
								onClick={ close }
								disabled={ busy }
								aria-label={ __(
									'Close',
									'nhrrob-issue-reporter'
								) }
							>
								<Icon size={ 16 } d="M18 6L6 18M6 6l12 12" />
							</button>
						</div>

						<div className="w-panel__body">
							{ shapes.length > 0 && (
								<span className="w-count">
									{ sprintf(
										/* translators: %d: number of marks on the screenshot. */
										_n(
											'%d mark',
											'%d marks',
											shapes.length,
											'nhrrob-issue-reporter'
										),
										shapes.length
									) }
								</span>
							) }
							<label className="w-label" htmlFor="w-what">
								{ __(
									'What’s wrong?',
									'nhrrob-issue-reporter'
								) }
							</label>
							<textarea
								id="w-what"
								ref={ textRef }
								className="w-field"
								rows="5"
								maxLength="5000"
								value={ text }
								onChange={ ( e ) => setText( e.target.value ) }
								onKeyDown={ ( e ) => {
									if (
										e.key === 'Enter' &&
										( e.metaKey || e.ctrlKey )
									) {
										send( e );
									}
								} }
							/>

							<span className="w-label" id="w-kind">
								{ __( 'Kind', 'nhrrob-issue-reporter' ) }
							</span>
							<div
								className="w-seg"
								role="radiogroup"
								aria-labelledby="w-kind"
							>
								{ KINDS.map( ( [ id, label ] ) => (
									<button
										key={ id }
										type="button"
										role="radio"
										aria-checked={ kind === id }
										className={
											'w-seg__btn' +
											( kind === id ? ' is-on' : '' )
										}
										onClick={ () => setKind( id ) }
									>
										{ label }
									</button>
								) ) }
							</div>

							<span className="w-label">
								{ __(
									'Added for the developer',
									'nhrrob-issue-reporter'
								) }
							</span>
							<p className="w-help">
								{ __(
									'Switch off anything you would rather not send.',
									'nhrrob-issue-reporter'
								) }
							</p>
							<div className="w-chips">
								{ chips.map( ( [ key, label ] ) => (
									<button
										key={ key }
										type="button"
										className={
											'w-chip' +
											( off.includes( key )
												? ' is-off'
												: '' ) +
											( key === 'errors'
												? ' is-warn'
												: '' )
										}
										aria-pressed={ ! off.includes( key ) }
										onClick={ () => toggle( key ) }
									>
										{ label }
									</button>
								) ) }
							</div>
						</div>

						<div className="w-panel__foot">
							<span className="w-avatar" aria-hidden="true">
								{ initial }
							</span>
							<span className="w-who">{ boot.name }</span>
							<button
								type="submit"
								className="w-send"
								disabled={ busy || ! text.trim() }
							>
								{ busy
									? __( 'Sending…', 'nhrrob-issue-reporter' )
									: __( 'Send', 'nhrrob-issue-reporter' ) }
							</button>
						</div>
					</form>
				</div>
			) }
		</div>
	);
}
