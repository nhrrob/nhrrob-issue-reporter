/**
 * Settings: the GitHub token and the repository of each plugin.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import api from '../api';
import { GROUPS } from './Report';
import { useToast } from '../components/ToastProvider';
import {
	Button,
	Field,
	Note,
	Panel,
	Pill,
	ScreenHeader,
} from '../components/ui';

const P = 'nhrrob-issue-reporter-';

export default function Settings( { data, setData } ) {
	const toast = useToast();
	const [ token, setToken ] = useState( '' );
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

	const targets = data.plugins.filter( ( p ) => p.type !== 'repo' );
	const extra = data.plugins
		.filter( ( p ) => p.type === 'repo' )
		.map( ( p ) => ( { label: p.name, repo: p.repo } ) );

	// The server replaces the whole map, so every row is sent with the change.
	const saveRepo = ( file, value ) => {
		const repos = {};
		targets.forEach( ( p ) => {
			repos[ p.file ] = p.file === file ? value : p.repo;
		} );
		return save( { repos } );
	};

	// The same for the other repositories: the whole list is sent.
	const saveExtra = ( rows, message ) => save( { extra: rows }, message );

	let tokenPill = (
		<Pill tone="warn">{ __( 'Not set', 'nhrrob-issue-reporter' ) }</Pill>
	);
	if ( data.tokenInConfig ) {
		tokenPill = (
			<Pill tone="ok">
				{ __( 'Set in wp-config.php', 'nhrrob-issue-reporter' ) }
			</Pill>
		);
	} else if ( data.hasToken ) {
		tokenPill = (
			<Pill tone="info">
				{ __( 'Saved in the database', 'nhrrob-issue-reporter' ) }
			</Pill>
		);
	}

	return (
		<>
			<ScreenHeader
				title={ __( 'Settings', 'nhrrob-issue-reporter' ) }
				lede={ __(
					'Reports are sent with a GitHub token, to the repository set for each plugin.',
					'nhrrob-issue-reporter'
				) }
			/>

			<Panel
				title={ __( 'GitHub token', 'nhrrob-issue-reporter' ) }
				icon="key"
				actions={ tokenPill }
			>
				<p className={ P + 'muted' }>
					{ __(
						'Create a fine-grained personal access token that covers only the repositories below, with one permission: “Issues: Read and write”.',
						'nhrrob-issue-reporter'
					) }
				</p>
				{ data.tokenInConfig ? (
					<Note>
						{ __(
							'The token is set in wp-config.php with NHRROB_ISSUE_REPORTER_TOKEN, so it is not stored in the database.',
							'nhrrob-issue-reporter'
						) }
					</Note>
				) : (
					<form
						className={ P + 'inline' }
						autoComplete="off"
						onSubmit={ ( event ) => {
							event.preventDefault();
							save(
								{ token },
								__( 'Token saved', 'nhrrob-issue-reporter' )
							).then( ( ok ) => ok && setToken( '' ) );
						} }
					>
						<Field
							id={ P + 'token' }
							label={ __( 'Token', 'nhrrob-issue-reporter' ) }
							help={ __(
								'To keep it out of the database, define NHRROB_ISSUE_REPORTER_TOKEN in wp-config.php and leave this empty.',
								'nhrrob-issue-reporter'
							) }
						>
							<div className={ P + 'inline__row' }>
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
											'Remove',
											'nhrrob-issue-reporter'
										) }
									</Button>
								) }
							</div>
						</Field>
					</form>
				) }
			</Panel>

			<Panel
				title={ __( 'Repositories', 'nhrrob-issue-reporter' ) }
				icon="repo"
				meta={ __(
					'Saved when you leave a field',
					'nhrrob-issue-reporter'
				) }
				flush
			>
				<div className={ P + 'scroll' }>
					<table className={ P + 'grid' }>
						<thead>
							<tr>
								<th scope="col">
									{ __(
										'Plugin or theme',
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
									{ __( 'Source', 'nhrrob-issue-reporter' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ targets.map( ( p ) => (
								<RepoRow
									key={ p.file + '|' + p.repo }
									plugin={ p }
									onSave={ saveRepo }
								/>
							) ) }
						</tbody>
					</table>
				</div>
			</Panel>

			<Panel
				title={ __( 'Other repositories', 'nhrrob-issue-reporter' ) }
				icon="repo"
			>
				<p className={ P + 'muted' }>
					{ __(
						'Repositories that are not a plugin or theme on this site, such as an app it talks to. They are listed in the form under their label.',
						'nhrrob-issue-reporter'
					) }
				</p>
				{ extra.length > 0 && (
					<ul className={ P + 'extra' }>
						{ extra.map( ( row ) => (
							<li key={ row.repo }>
								<span>{ row.label }</span>
								<span className="is-mono">{ row.repo }</span>
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
			<div className={ P + 'inline__row' }>
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
					{ __( 'Add', 'nhrrob-issue-reporter' ) }
				</Button>
			</div>
		</form>
	);
}

function RepoRow( { plugin, onSave } ) {
	const [ value, setValue ] = useState( plugin.repo );
	const id = P + 'repo-' + plugin.file.replace( /[^a-z0-9]+/gi, '-' );

	let source = (
		<Pill tone="off">{ __( 'Not set', 'nhrrob-issue-reporter' ) }</Pill>
	);
	if ( plugin.repo && plugin.repo === plugin.detected ) {
		source = (
			<Pill tone="info">
				{ __( 'From the plugin', 'nhrrob-issue-reporter' ) }
			</Pill>
		);
	} else if ( plugin.repo ) {
		source = (
			<Pill tone="ok">{ __( 'Set here', 'nhrrob-issue-reporter' ) }</Pill>
		);
	}

	return (
		<tr>
			<td className="is-wrap">
				<label htmlFor={ id }>{ plugin.name }</label>
				<div className={ P + 'sub' }>
					{ GROUPS.find( ( g ) => g[ 0 ] === plugin.type )[ 1 ] }
					{ ' · ' + plugin.version }
				</div>
			</td>
			<td>
				<input
					id={ id }
					className={ P + 'input is-mono' }
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
			</td>
			<td>{ source }</td>
		</tr>
	);
}
