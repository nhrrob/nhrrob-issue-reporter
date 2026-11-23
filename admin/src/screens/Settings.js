/**
 * Settings: the GitHub connection, and the repository of each plugin, theme
 * and other repository, with search and filters.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import api from '../api';
import ConnectGitHub, { tokenLink } from '../components/ConnectGitHub';
import { GROUPS, TYPE_ICON } from '../components/TargetPicker';
import { useToast } from '../components/ToastProvider';
import {
	Button,
	Field,
	Icon,
	Panel,
	Pill,
	ScreenHeader,
	SearchBox,
	Segmented,
} from '../components/ui';
import WidgetSettings from './WidgetSettings';

const P = 'nhrrob-issue-reporter-';

export default function Settings( { boot, data, setData } ) {
	const toast = useToast();
	const [ tab, setTab ] = useState( data.hasToken ? 'repos' : 'github' );
	const [ busy, setBusy ] = useState( false );

	const save = ( patch, message ) => {
		setBusy( true );
		return api( '/settings', 'POST', patch )
			.then( ( next ) => {
				setData( next );
				toast( message || __( 'Saved', 'nhrrob-issue-reporter' ) );
				return true;
			} )
			.catch( ( e ) => {
				toast( e.message || String( e ), 'error' );
				return false;
			} )
			.finally( () => setBusy( false ) );
	};

	const set = data.plugins.filter( ( p ) => p.repo ).length;

	return (
		<>
			<ScreenHeader
				title={ __( 'Settings', 'nhrrob-issue-reporter' ) }
				lede={ __(
					'Reports are sent with a GitHub token, to the repository set for each plugin and theme.',
					'nhrrob-issue-reporter'
				) }
			/>

			<div
				className={ P + 'tabs' }
				role="tablist"
				aria-label={ __( 'Settings', 'nhrrob-issue-reporter' ) }
			>
				{ [
					[ 'github', __( 'GitHub', 'nhrrob-issue-reporter' ), '' ],
					[
						'repos',
						__( 'Repositories', 'nhrrob-issue-reporter' ),
						String( set ),
					],
					[
						'widget',
						__( 'Client widget', 'nhrrob-issue-reporter' ),
						data.widget.enabled
							? __( 'On', 'nhrrob-issue-reporter' )
							: '',
					],
				].map( ( [ id, label, badge ] ) => (
					<button
						key={ id }
						type="button"
						role="tab"
						aria-selected={ tab === id }
						className={
							P + 'tabs__tab' + ( tab === id ? ' is-on' : '' )
						}
						onClick={ () => setTab( id ) }
					>
						{ label }
						{ badge && (
							<span className={ P + 'tabs__badge' }>
								{ badge }
							</span>
						) }
					</button>
				) ) }
			</div>

			{ tab === 'github' && (
				<Connection
					boot={ boot }
					data={ data }
					setData={ setData }
					busy={ busy }
					save={ save }
				/>
			) }
			{ tab === 'repos' && (
				<Repositories data={ data } busy={ busy } save={ save } />
			) }
			{ tab === 'widget' && (
				<WidgetSettings data={ data } busy={ busy } save={ save } />
			) }
		</>
	);
}

function Connection( { boot, data, setData, busy, save } ) {
	const [ token, setToken ] = useState( '' );
	const someRepo = data.plugins.some( ( p ) => p.repo );

	let pill = (
		<Pill tone="warn">
			{ __( 'Not connected', 'nhrrob-issue-reporter' ) }
		</Pill>
	);
	if ( data.hasToken ) {
		pill = (
			<Pill tone="ok">
				{ __( 'Connected', 'nhrrob-issue-reporter' ) }
			</Pill>
		);
	}

	const steps = [
		{
			done: data.hasToken,
			title: __(
				'Connect GitHub, or make a token',
				'nhrrob-issue-reporter'
			),
			note: __(
				'“Connect GitHub” is one click and a code. A token can be limited to the repositories you choose, with one permission: “Issues: Read and write”.',
				'nhrrob-issue-reporter'
			),
		},
		{
			done: data.hasToken,
			title: __(
				'With a token: paste it here, or add it to wp-config.php',
				'nhrrob-issue-reporter'
			),
			note: __(
				'define( ‘NHRROB_ISSUE_REPORTER_TOKEN’, ‘…’ ); in wp-config.php keeps it out of the database.',
				'nhrrob-issue-reporter'
			),
		},
		{
			done: data.hasToken && someRepo,
			title: __( 'Check the repositories', 'nhrrob-issue-reporter' ),
			note: __(
				'Most plugins name theirs. A new one is checked with GitHub when you save it.',
				'nhrrob-issue-reporter'
			),
		},
	];

	return (
		<section className={ P + 'panel' }>
			<header className={ P + 'conncard' }>
				<span className={ P + 'brand__mark is-lg' }>
					<Icon name="link" size={ 20 } />
				</span>
				<span className={ P + 'conncard__text' }>
					<strong>
						{ __( 'GitHub connection', 'nhrrob-issue-reporter' ) }
					</strong>
					<span>
						{ data.tokenInConfig &&
							__(
								'Token set in wp-config.php',
								'nhrrob-issue-reporter'
							) }
						{ ! data.tokenInConfig &&
							data.hasToken &&
							__(
								'Token saved in the database',
								'nhrrob-issue-reporter'
							) }
						{ ! data.hasToken &&
							__(
								'No token yet. Reports cannot be sent.',
								'nhrrob-issue-reporter'
							) }
					</span>
				</span>
				{ pill }
			</header>
			<div className={ P + 'conncard__body' }>
				<ol className={ P + 'checklist' }>
					{ steps.map( ( s ) => (
						<li
							key={ s.title }
							className={ s.done ? 'is-done' : '' }
						>
							<span className={ P + 'checklist__mark' }>
								{ s.done ? (
									<Icon name="check" size={ 13 } />
								) : (
									steps.indexOf( s ) + 1
								) }
							</span>
							<span className={ P + 'checklist__text' }>
								<strong>{ s.title }</strong>
								<span>{ s.note }</span>
							</span>
						</li>
					) ) }
				</ol>

				<div className={ P + 'tokenbox' }>
					{ data.tokenInConfig ? (
						<>
							<strong>
								{ __(
									'The token is set in wp-config.php',
									'nhrrob-issue-reporter'
								) }
							</strong>
							<p className={ P + 'muted' }>
								{ __(
									'It is not stored in the database, and it is changed there.',
									'nhrrob-issue-reporter'
								) }
							</p>
						</>
					) : (
						<>
							{ ! data.hasToken && (
								<ConnectGitHub setData={ setData } />
							) }
							<form
								autoComplete="off"
								onSubmit={ ( event ) => {
									event.preventDefault();
									save(
										{ token },
										__(
											'Token saved',
											'nhrrob-issue-reporter'
										)
									).then( ( ok ) => ok && setToken( '' ) );
								} }
							>
								<div className={ P + 'tokenhow' }>
									<strong>
										{ data.hasToken
											? __(
													'Replace the token',
													'nhrrob-issue-reporter'
											  )
											: __(
													'Or use a token',
													'nhrrob-issue-reporter'
											  ) }
									</strong>
									<ol>
										<li>
											{ __(
												'Open GitHub’s token form: the name, a year and “Issues: Read and write” are filled in.',
												'nhrrob-issue-reporter'
											) }
										</li>
										<li>
											{ __(
												'Under “Repository access”, choose the repositories you report to, then “Generate token”.',
												'nhrrob-issue-reporter'
											) }
										</li>
										<li>
											{ __(
												'Copy the token and paste it below.',
												'nhrrob-issue-reporter'
											) }
										</li>
									</ol>
									<a
										className={
											P + 'btn ' + P + 'btn--secondary'
										}
										href={ tokenLink( boot.siteName ) }
										target="_blank"
										rel="noopener noreferrer"
									>
										{ __(
											'Create a token on GitHub',
											'nhrrob-issue-reporter'
										) }
										<Icon name="external" size={ 14 } />
									</a>
								</div>
								<Field
									id={ P + 'token' }
									label={ __(
										'Token',
										'nhrrob-issue-reporter'
									) }
									help={ __(
										'Kept on this server and sent to api.github.com only. It never appears on a page again.',
										'nhrrob-issue-reporter'
									) }
								>
									<input
										id={ P + 'token' }
										className={ P + 'input is-mono' }
										type="password"
										autoComplete="new-password"
										value={ token }
										placeholder={
											data.hasToken
												? __(
														'A token is saved. Type a new one to replace it.',
														'nhrrob-issue-reporter'
												  )
												: 'github_pat_…'
										}
										onChange={ ( e ) =>
											setToken( e.target.value )
										}
									/>
								</Field>
								<div className={ P + 'tokenbox__actions' }>
									<Button
										variant="primary"
										type="submit"
										disabled={ busy || ! token.trim() }
									>
										{ __(
											'Save token',
											'nhrrob-issue-reporter'
										) }
									</Button>
									{ data.hasToken && (
										<Button
											variant="danger"
											disabled={ busy }
											onClick={ () =>
												save(
													{ removeToken: true },
													__(
														'Token removed',
														'nhrrob-issue-reporter'
													)
												)
											}
										>
											{ __(
												'Remove token',
												'nhrrob-issue-reporter'
											) }
										</Button>
									) }
								</div>
							</form>
						</>
					) }
				</div>
			</div>
		</section>
	);
}

function Repositories( { data, busy, save } ) {
	const [ filter, setFilter ] = useState( 'set' );
	const [ search, setSearch ] = useState( '' );

	const targets = data.plugins.filter( ( p ) => p.type !== 'repo' );
	const extra = data.plugins
		.filter( ( p ) => p.type === 'repo' )
		.map( ( p ) => ( { label: p.name, repo: p.repo } ) );

	const source = ( p ) => {
		if ( ! p.repo ) {
			return 'unset';
		}
		return p.repo === p.detected ? 'header' : 'here';
	};

	// The server replaces the whole map, so every row is sent with the change.
	const saveRepo = ( file, value ) => {
		const repos = {};
		targets.forEach( ( p ) => {
			repos[ p.file ] = p.file === file ? value : p.repo;
		} );
		return save( { repos } );
	};

	// The same for the other repositories: the whole list is sent.
	const saveExtra = ( list, message ) => save( { extra: list }, message );

	const count = ( id ) =>
		targets.filter( ( p ) => {
			const s = source( p );
			return (
				id === 'all' || ( id === 'set' && s !== 'unset' ) || id === s
			);
		} ).length;

	const q = search.trim().toLowerCase();
	const shown = targets.filter( ( p ) => {
		const s = source( p );
		const byFilter =
			filter === 'all' ||
			( filter === 'set' && s !== 'unset' ) ||
			filter === s;
		return (
			byFilter &&
			( ! q ||
				p.name.toLowerCase().includes( q ) ||
				( p.repo || '' ).toLowerCase().includes( q ) )
		);
	} );

	return (
		<>
			<Panel flush>
				<div className={ P + 'toolbar' }>
					<Segmented
						label={ __( 'Show', 'nhrrob-issue-reporter' ) }
						value={ filter }
						onChange={ setFilter }
						options={ [
							{
								value: 'set',
								label: __(
									'Has a repository',
									'nhrrob-issue-reporter'
								),
								count: count( 'set' ),
							},
							{
								value: 'unset',
								label: __( 'Not set', 'nhrrob-issue-reporter' ),
								count: count( 'unset' ),
							},
							{
								value: 'here',
								label: __(
									'Set here',
									'nhrrob-issue-reporter'
								),
								count: count( 'here' ),
							},
							{
								value: 'all',
								label: __( 'All', 'nhrrob-issue-reporter' ),
								count: count( 'all' ),
							},
						] }
					/>
					<SearchBox
						label={ __(
							'Search plugins and themes',
							'nhrrob-issue-reporter'
						) }
						value={ search }
						onChange={ setSearch }
					/>
				</div>
				{ ! shown.length && (
					<p className={ P + 'empty is-small' }>
						{ __( 'Nothing matches.', 'nhrrob-issue-reporter' ) }
					</p>
				) }
				{ GROUPS.filter( ( [ type ] ) => type !== 'repo' ).map(
					( [ type, label ] ) => {
						const list = shown.filter( ( p ) => p.type === type );
						return (
							list.length > 0 && (
								<div key={ type }>
									<div className={ P + 'group' }>
										{ label + ' · ' + list.length }
									</div>
									<ul className={ P + 'rows' }>
										{ list.map( ( p ) => (
											<RepoRow
												key={ p.file + '|' + p.repo }
												plugin={ p }
												source={ source( p ) }
												onSave={ saveRepo }
											/>
										) ) }
									</ul>
								</div>
							)
						);
					}
				) }
				<div className={ P + 'panel__foot' }>
					<span>
						{ __(
							'A repository saves when you leave its field, once GitHub confirms the token can reach it.',
							'nhrrob-issue-reporter'
						) }
					</span>
				</div>
			</Panel>

			<Panel
				title={ __( 'Other repositories', 'nhrrob-issue-reporter' ) }
				meta={ __(
					'Not a plugin or theme here, such as an app the site talks to',
					'nhrrob-issue-reporter'
				) }
			>
				{ extra.length > 0 && (
					<ul className={ P + 'rows is-tight' }>
						{ extra.map( ( row ) => (
							<li key={ row.repo } className={ P + 'row' }>
								<span
									className={
										P + 'tile ' + P + 'tile--accent'
									}
								>
									<Icon name="repo" size={ 16 } />
								</span>
								<span className={ P + 'row__main' }>
									<strong>{ row.label }</strong>
									<span className={ P + 'row__meta is-mono' }>
										{ row.repo }
									</span>
								</span>
								<Button
									small
									variant="danger"
									disabled={ busy }
									onClick={ () =>
										saveExtra(
											extra.filter(
												( r ) => r.repo !== row.repo
											),
											__(
												'Repository removed',
												'nhrrob-issue-reporter'
											)
										)
									}
								>
									{ __( 'Remove', 'nhrrob-issue-reporter' ) }
								</Button>
							</li>
						) ) }
					</ul>
				) }
				<ExtraForm
					busy={ busy }
					onAdd={ ( row ) =>
						saveExtra(
							[ ...extra, row ],
							__( 'Repository added', 'nhrrob-issue-reporter' )
						)
					}
				/>
			</Panel>
		</>
	);
}

function ExtraForm( { busy, onAdd } ) {
	const [ label, setLabel ] = useState( '' );
	const [ repo, setRepo ] = useState( '' );

	return (
		<form
			className={ P + 'inline' }
			onSubmit={ ( event ) => {
				event.preventDefault();
				onAdd( { label, repo } ).then( ( ok ) => {
					if ( ok ) {
						setLabel( '' );
						setRepo( '' );
					}
				} );
			} }
		>
			<Field
				id={ P + 'extra-label' }
				label={ __( 'Label', 'nhrrob-issue-reporter' ) }
			>
				<input
					id={ P + 'extra-label' }
					className={ P + 'input' }
					type="text"
					maxLength={ 100 }
					value={ label }
					onChange={ ( e ) => setLabel( e.target.value ) }
				/>
			</Field>
			<Field
				id={ P + 'extra-repo' }
				label={ __( 'Repository', 'nhrrob-issue-reporter' ) }
			>
				<input
					id={ P + 'extra-repo' }
					className={ P + 'input is-mono' }
					type="text"
					placeholder="owner/name"
					required
					value={ repo }
					onChange={ ( e ) => setRepo( e.target.value ) }
				/>
			</Field>
			<Button
				variant="primary"
				type="submit"
				disabled={ busy || ! repo.trim() }
			>
				<Icon name="plus" size={ 15 } />
				{ __( 'Add repository', 'nhrrob-issue-reporter' ) }
			</Button>
		</form>
	);
}

function RepoRow( { plugin, source, onSave } ) {
	const [ value, setValue ] = useState( plugin.repo );
	const id = P + 'repo-' + plugin.file.replace( /[^a-z0-9]+/gi, '-' );

	const sources = {
		header: __( 'From the plugin’s header', 'nhrrob-issue-reporter' ),
		here: __( 'Set here', 'nhrrob-issue-reporter' ),
		unset: __( 'No GitHub address in its header', 'nhrrob-issue-reporter' ),
	};

	return (
		<li className={ P + 'row' }>
			<span
				className={
					P +
					'tile ' +
					P +
					'tile--' +
					( source === 'unset' ? 'muted' : 'accent' )
				}
			>
				<Icon name={ TYPE_ICON[ plugin.type ] } size={ 16 } />
			</span>
			<span className={ P + 'row__main' }>
				<label htmlFor={ id } className={ P + 'row__name' }>
					{ plugin.name }
					{ plugin.version && (
						<span className={ P + 'muted' }>
							{ ' ' + plugin.version }
						</span>
					) }
				</label>
				<span className={ P + 'row__meta' }>{ sources[ source ] }</span>
			</span>
			<span className={ P + 'repo-input' }>
				<Icon name="repo" size={ 14 } />
				<input
					id={ id }
					type="text"
					placeholder="owner/name"
					value={ value }
					onChange={ ( e ) => setValue( e.target.value ) }
					onBlur={ () => {
						if ( value.trim() !== plugin.repo ) {
							onSave( plugin.file, value ).then(
								( ok ) => ok || setValue( plugin.repo )
							);
						}
					} }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' ) {
							e.target.blur();
						}
					} }
				/>
			</span>
			<span
				className={
					P + 'state' + ( source === 'unset' ? '' : ' is-ok' )
				}
			>
				{ source === 'unset'
					? __( 'Not set', 'nhrrob-issue-reporter' )
					: __( 'Set', 'nhrrob-issue-reporter' ) }
			</span>
		</li>
	);
}
