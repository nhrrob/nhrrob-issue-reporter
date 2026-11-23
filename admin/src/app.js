/**
 * Root component: app shell, section routing and the shared state.
 */
import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import api from './api';
import AppShell from './components/AppShell';
import { ToastProvider } from './components/ToastProvider';
import { Loading, Note } from './components/ui';
import Report from './screens/Report';
import Sent from './screens/Sent';
import Settings from './screens/Settings';

const SCREENS = { report: Report, sent: Sent, settings: Settings };

export default function App( { boot } ) {
	const [ active, setActive ] = useState( 'report' );
	const [ data, setData ] = useState( null );
	const [ failed, setFailed ] = useState( '' );

	useEffect( () => {
		api( '/state' )
			.then( setData )
			.catch( ( e ) => setFailed( e.message || String( e ) ) );
	}, [] );

	const reachable = data ? data.plugins.filter( ( p ) => p.repo ).length : 0;
	const groups = [
		{
			label: __( 'Reports', 'nhrrob-issue-reporter' ),
			items: [
				{
					id: 'report',
					icon: 'plus',
					label: __( 'New report', 'nhrrob-issue-reporter' ),
				},
				{
					id: 'sent',
					icon: 'send',
					label: __( 'Sent', 'nhrrob-issue-reporter' ),
					count: data
						? data.reports.filter( ( r ) => r.state !== 'closed' )
								.length
						: 0,
				},
			],
		},
		{
			label: __( 'Setup', 'nhrrob-issue-reporter' ),
			items: [
				{
					id: 'settings',
					icon: 'settings',
					label: __( 'Settings', 'nhrrob-issue-reporter' ),
				},
			],
		},
	];

	let connection = null;
	if ( data ) {
		connection = {
			ok: data.hasToken,
			line: data.hasToken
				? sprintf(
						/* translators: %d: number of plugins, themes and repositories with a repository set. */
						_n(
							'%d place to report to',
							'%d places to report to',
							reachable,
							'nhrrob-issue-reporter'
						),
						reachable
				  )
				: __( 'Add a token to send reports.', 'nhrrob-issue-reporter' ),
		};
	}

	const Screen = SCREENS[ active ];

	let content = <Loading />;
	if ( failed ) {
		content = <Note tone="warn">{ failed }</Note>;
	} else if ( data ) {
		content = (
			<Screen
				boot={ boot }
				data={ data }
				setData={ setData }
				navigate={ setActive }
			/>
		);
	}

	return (
		<ToastProvider>
			<AppShell
				groups={ groups }
				active={ active }
				onNavigate={ setActive }
				site={ boot.siteName }
				connection={ connection }
				title={
					boot.pluginName ||
					__( 'Report Issue', 'nhrrob-issue-reporter' )
				}
			>
				{ content }
			</AppShell>
		</ToastProvider>
	);
}
