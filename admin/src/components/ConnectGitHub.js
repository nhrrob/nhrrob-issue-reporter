/**
 * "Connect GitHub": GitHub's device flow. The person gets a short code, enters
 * it on github.com, and the server collects the token. The browser never sees
 * the token or the device code.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import api from '../api';
import { useToast } from './ToastProvider';
import { Button, Icon } from './ui';

const P = 'nhrrob-issue-reporter-';

/**
 * The address of GitHub's form for a fine-grained token, filled in: a name,
 * what it is for, a year, and the one permission. The person still chooses
 * the repositories.
 *
 * @param {string} site The site's name.
 * @return {string} Address.
 */
export function tokenLink( site ) {
	const query = new window.URLSearchParams( {
		name: ( 'Report Issue · ' + ( site || 'WordPress' ) ).slice( 0, 40 ),
		description:
			'Opens issues from the Report Issue plugin on this WordPress site.',
		expires_in: '366',
		issues: 'write',
	} );
	return (
		'https://github.com/settings/personal-access-tokens/new?' +
		query.toString()
	);
}

export default function ConnectGitHub( { setData } ) {
	const toast = useToast();
	const [ flow, setFlow ] = useState( null ); // { code, url, interval }
	const [ wide, setWide ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const timer = useRef();

	useEffect( () => () => window.clearTimeout( timer.current ), [] );

	const poll = ( seconds ) => {
		timer.current = window.setTimeout( () => {
			api( '/connect/poll', 'POST' )
				.then( ( answer ) => {
					if ( answer.status === 'done' ) {
						setFlow( null );
						setData( answer.state );
						toast(
							__( 'GitHub connected', 'nhrrob-issue-reporter' )
						);
					} else if ( answer.status === 'pending' ) {
						poll( answer.interval || seconds );
					} else {
						setFlow( null );
						toast(
							answer.status === 'denied'
								? __(
										'The connection was cancelled on GitHub.',
										'nhrrob-issue-reporter'
								  )
								: __(
										'The code expired. Start again.',
										'nhrrob-issue-reporter'
								  ),
							'error'
						);
					}
				} )
				.catch( ( e ) => {
					setFlow( null );
					toast( e.message || String( e ), 'error' );
				} );
		}, seconds * 1000 );
	};

	const start = () => {
		setBusy( true );
		api( '/connect/start', 'POST', { private: wide } )
			.then( ( answer ) => {
				setFlow( answer );
				poll( answer.interval );
			} )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) )
			.finally( () => setBusy( false ) );
	};

	const cancel = () => {
		window.clearTimeout( timer.current );
		setFlow( null );
	};

	const copy = () => {
		if ( window.navigator.clipboard ) {
			window.navigator.clipboard
				.writeText( flow.code )
				.then( () =>
					toast( __( 'Code copied', 'nhrrob-issue-reporter' ) )
				)
				.catch( () => {} );
		}
	};

	if ( flow ) {
		return (
			<div className={ P + 'connect' }>
				<strong>
					{ __(
						'Enter this code on GitHub',
						'nhrrob-issue-reporter'
					) }
				</strong>
				<div className={ P + 'connect__code' }>
					<code>{ flow.code }</code>
					<Button small onClick={ copy }>
						{ __( 'Copy', 'nhrrob-issue-reporter' ) }
					</Button>
				</div>
				<div className={ P + 'connect__actions' }>
					<a
						className={ P + 'btn ' + P + 'btn--primary' }
						href={ flow.url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Open GitHub', 'nhrrob-issue-reporter' ) }
						<Icon name="external" size={ 14 } />
					</a>
					<Button variant="ghost" onClick={ cancel }>
						{ __( 'Cancel', 'nhrrob-issue-reporter' ) }
					</Button>
				</div>
				<p className={ P + 'muted' } role="status">
					{ __(
						'Waiting for you to approve “NHR Issue Reporter” on GitHub. This page finishes on its own.',
						'nhrrob-issue-reporter'
					) }
				</p>
			</div>
		);
	}

	return (
		<div className={ P + 'connect' }>
			<strong>{ __( 'Connect GitHub', 'nhrrob-issue-reporter' ) }</strong>
			<p className={ P + 'muted' }>
				{ __(
					'Approve this site on github.com with a short code. Reports are then opened as you. GitHub’s page names the app “NHR Issue Reporter” by nhrrob; approve only when you started it here.',
					'nhrrob-issue-reporter'
				) }
			</p>
			<label className={ P + 'check' } htmlFor={ P + 'connect-private' }>
				<input
					id={ P + 'connect-private' }
					type="checkbox"
					checked={ wide }
					onChange={ ( e ) => setWide( e.target.checked ) }
				/>
				<span>
					{ __(
						'Also private repositories. Without it, the connection reaches public repositories only. With it, GitHub grants full access to your private repositories too, so a token limited to chosen repositories (below) is the narrower choice.',
						'nhrrob-issue-reporter'
					) }
				</span>
			</label>
			<div>
				<Button variant="primary" onClick={ start } disabled={ busy }>
					<Icon name="github" size={ 16 } />
					{ __( 'Connect GitHub', 'nhrrob-issue-reporter' ) }
				</Button>
			</div>
		</div>
	);
}
