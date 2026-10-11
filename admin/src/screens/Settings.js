/**
 * Settings: the GitHub token and the repository of each plugin.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import api from '../api';
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

	// The server replaces the whole map, so every row is sent with the change.
	const saveRepo = ( file, value ) => {
		const repos = {};
		data.plugins.forEach( ( p ) => {
			repos[ p.file ] = p.file === file ? value : p.repo;
		} );
		return save( { repos } );
	};

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
									{ __( 'Plugin', 'nhrrob-issue-reporter' ) }
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
							{ data.plugins.map( ( p ) => (
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
		</>
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
				<div className={ P + 'sub' }>{ plugin.version }</div>
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
