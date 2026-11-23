/**
 * Root component: app shell, section routing and the shared state.
 */
import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

import api from './api';
import AppShell from './components/AppShell';
import { ToastProvider } from './components/ToastProvider';
import { Loading, Note } from './components/ui';
import Inbox from './screens/Inbox';
import Report from './screens/Report';
import Sent from './screens/Sent';
import Settings from './screens/Settings';

const SCREENS = {
	report: Report,
	sent: Sent,
	inbox: Inbox,
	settings: Settings,
};

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
	const base = [
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
			label: __( 'Client feedback', 'nhrrob-issue-reporter' ),
			items: [
				{
					id: 'inbox',
					icon: 'inbox',
					label: __( 'Inbox', 'nhrrob-issue-reporter' ),
					count: data ? data.inbox : 0,
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

	/**
	 * The app's sidebar groups and screens. Another plugin adds its own here,
	 * from a script enqueued on the `nhrrob_issue_reporter_enqueue` action:
	 * a group row is { label, items: [ { id, icon, label, count } ] }, and
	 * screens maps an item's id to a component that gets { boot, data,
	 * setData, navigate }.
	 */
	const { groups, screens } = applyFilters( 'nhrrobIssueReporter.app', {
		groups: base,
		screens: SCREENS,
	} );

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

	const Screen = screens[ active ] || Report;

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
