/**
 * Report: the form that opens an issue, with similar open issues beside it.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import api from '../api';
import { useToast } from '../components/ToastProvider';
import {
	Button,
	External,
	Field,
	Icon,
	Note,
	Panel,
	ScreenHeader,
} from '../components/ui';

const P = 'nhrrob-issue-reporter-';

// The line about what is reported, at the top of the Environment box. Sent
// as is, so it is not translated, like the rest of the environment text.
const LINE = {
	plugin: 'Plugin',
	'mu-plugin': 'Must-use plugin',
	theme: 'Theme',
};

export const GROUPS = [
	[ 'plugin', __( 'Plugins', 'nhrrob-issue-reporter' ) ],
	[ 'mu-plugin', __( 'Must-use plugins', 'nhrrob-issue-reporter' ) ],
	[ 'theme', __( 'Themes', 'nhrrob-issue-reporter' ) ],
	[ 'repo', __( 'Other repositories', 'nhrrob-issue-reporter' ) ],
];

export default function Report( { boot, data, setData, navigate } ) {
	const toast = useToast();
	const plugins = data.plugins.filter( ( p ) => p.repo );
	const preset = plugins.find( ( p ) => p.file === boot.plugin );

	const [ file, setFile ] = useState( preset ? preset.file : '' );
	const [ title, setTitle ] = useState( '' );
	const [ what, setWhat ] = useState( '' );
	const [ steps, setSteps ] = useState( '' );
	const [ environment, setEnvironment ] = useState( data.environment );
	const [ edited, setEdited ] = useState( false );
	const [ similar, setSimilar ] = useState( null );
	const [ searching, setSearching ] = useState( false );
	const [ sending, setSending ] = useState( false );
	const [ commenting, setCommenting ] = useState( 0 );

	const plugin = plugins.find( ( p ) => p.file === file );
	const ready = data.hasToken && plugins.length > 0;

	// The box shows exactly what is sent, so the plugin's line is part of it.
	useEffect( () => {
		if ( ! edited ) {
			setEnvironment(
				( plugin && LINE[ plugin.type ]
					? '- ' +
					  LINE[ plugin.type ] +
					  ': ' +
					  plugin.name +
					  ' ' +
					  plugin.version +
					  '\n'
					: '' ) + data.environment
			);
		}
	}, [ plugin, edited, data.environment ] );

	const lookUp = useCallback(
		( forFile, forTitle ) => {
			if ( ! data.hasToken || ! forFile || forTitle.trim().length < 4 ) {
				setSimilar( null );
				return;
			}
			setSearching( true );
			api( '/similar', 'POST', { plugin: forFile, title: forTitle } )
				.then( ( answer ) => setSimilar( answer.issues ) )
				.catch( () => setSimilar( null ) )
				.finally( () => setSearching( false ) );
		},
		[ data.hasToken ]
	);

	const send = ( event ) => {
		event.preventDefault();
		setSending( true );
		api( '/reports', 'POST', {
			plugin: file,
			title,
			what,
			steps,
			environment,
		} )
			.then( ( answer ) => {
				setData( { ...data, reports: answer.reports } );
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
		setCommenting( number );
		api( '/comments', 'POST', {
			plugin: file,
			number,
			what,
			steps,
			environment,
		} )
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

	return (
		<>
			<ScreenHeader
				title={ __( 'Report an issue', 'nhrrob-issue-reporter' ) }
				lede={ __(
					'Opens an issue in the plugin’s GitHub repository, with the versions a developer asks for already filled in.',
					'nhrrob-issue-reporter'
				) }
			/>

			{ ! data.hasToken && (
				<Note tone="warn">
					{ __(
						'A GitHub token is needed before a report can be sent.',
						'nhrrob-issue-reporter'
					) }{ ' ' }
					<Button
						variant="soft"
						small
						onClick={ () => navigate( 'settings' ) }
					>
						{ __( 'Add a token', 'nhrrob-issue-reporter' ) }
					</Button>
				</Note>
			) }
			{ data.hasToken && ! plugins.length && (
				<Note tone="warn">
					{ __(
						'No plugin has a repository yet.',
						'nhrrob-issue-reporter'
					) }{ ' ' }
					<Button
						variant="soft"
						small
						onClick={ () => navigate( 'settings' ) }
					>
						{ __( 'Set repositories', 'nhrrob-issue-reporter' ) }
					</Button>
				</Note>
			) }

			<div className={ P + 'split' }>
				<Panel
					title={ __( 'The report', 'nhrrob-issue-reporter' ) }
					icon="report"
				>
					<form className={ P + 'form' } onSubmit={ send }>
						<Field
							id={ P + 'plugin' }
							label={ __(
								'Plugin or theme',
								'nhrrob-issue-reporter'
							) }
						>
							<select
								id={ P + 'plugin' }
								className={ P + 'input' }
								value={ file }
								required
								onChange={ ( e ) => {
									setFile( e.target.value );
									lookUp( e.target.value, title );
								} }
							>
								<option value="">
									{ __(
										'Choose one',
										'nhrrob-issue-reporter'
									) }
								</option>
								{ GROUPS.map( ( [ type, label ] ) => {
									const rows = plugins.filter(
										( p ) => p.type === type
									);
									return (
										rows.length > 0 && (
											<optgroup
												key={ type }
												label={ label }
											>
												{ rows.map( ( p ) => (
													<option
														key={ p.file }
														value={ p.file }
													>
														{ p.name }
													</option>
												) ) }
											</optgroup>
										)
									);
								} ) }
							</select>
						</Field>

						<Field
							id={ P + 'title' }
							label={ __( 'Title', 'nhrrob-issue-reporter' ) }
						>
							<input
								id={ P + 'title' }
								className={ P + 'input' }
								type="text"
								maxLength={ 200 }
								required
								value={ title }
								onChange={ ( e ) => setTitle( e.target.value ) }
								onBlur={ () => lookUp( file, title ) }
							/>
						</Field>

						<Field
							id={ P + 'what' }
							label={ __(
								'What happened',
								'nhrrob-issue-reporter'
							) }
							help={ __(
								'What you saw, and what you expected. Markdown works.',
								'nhrrob-issue-reporter'
							) }
						>
							<textarea
								id={ P + 'what' }
								className={ P + 'input' }
								rows={ 6 }
								required
								value={ what }
								onChange={ ( e ) => setWhat( e.target.value ) }
							/>
						</Field>

						<Field
							id={ P + 'steps' }
							label={ __(
								'Steps to reproduce',
								'nhrrob-issue-reporter'
							) }
						>
							<textarea
								id={ P + 'steps' }
								className={ P + 'input' }
								rows={ 4 }
								value={ steps }
								onChange={ ( e ) => setSteps( e.target.value ) }
							/>
						</Field>

						<Field
							id={ P + 'environment' }
							label={ __(
								'Environment',
								'nhrrob-issue-reporter'
							) }
							help={ __(
								'Sent exactly as shown. Edit or empty it to send less. The site address is never added.',
								'nhrrob-issue-reporter'
							) }
						>
							<textarea
								id={ P + 'environment' }
								className={ P + 'input is-mono' }
								rows={ 6 }
								value={ environment }
								onChange={ ( e ) => {
									setEdited( true );
									setEnvironment( e.target.value );
								} }
							/>
						</Field>

						<div className={ P + 'form__foot' }>
							<Button
								variant="primary"
								type="submit"
								disabled={ ! ready || sending }
							>
								{ sending
									? __( 'Sending…', 'nhrrob-issue-reporter' )
									: __(
											'Send report',
											'nhrrob-issue-reporter'
									  ) }
							</Button>
						</div>
					</form>
				</Panel>

				<div className={ P + 'aside' }>
					<Panel
						title={ __( 'Where it goes', 'nhrrob-issue-reporter' ) }
						icon="repo"
					>
						{ plugin ? (
							<>
								<p>
									<External
										href={
											'https://github.com/' +
											plugin.repo +
											'/issues'
										}
									>
										{ plugin.repo }
									</External>
								</p>
								<p className={ P + 'muted' }>
									{ __(
										'If this repository is public, anyone can read the issue. Check the Environment box before sending.',
										'nhrrob-issue-reporter'
									) }
								</p>
							</>
						) : (
							<p className={ P + 'muted' }>
								{ __(
									'Choose a plugin to see its repository.',
									'nhrrob-issue-reporter'
								) }
							</p>
						) }
					</Panel>

					<Panel
						title={ __(
							'Similar open issues',
							'nhrrob-issue-reporter'
						) }
						icon="search"
						meta={
							searching
								? __( 'Searching…', 'nhrrob-issue-reporter' )
								: ''
						}
						flush={ !! ( similar && similar.length ) }
					>
						{ similar === null && (
							<p className={ P + 'muted' }>
								{ __(
									'Choose a plugin and type a title. Open issues with a similar title show up here, so you can add your text to one as a comment instead of opening another.',
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
											variant="soft"
											disabled={
												!! commenting || sending
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
														'Add as comment',
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
