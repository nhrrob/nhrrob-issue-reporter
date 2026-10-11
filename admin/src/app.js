/**
 * Root component: app shell, section routing and the shared state.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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

	const modules = [
		{ id: 'report', label: __( 'Report', 'nhrrob-issue-reporter' ) },
		{
			id: 'sent',
			label: __( 'Sent', 'nhrrob-issue-reporter' ),
			count: data
				? data.reports.filter( ( r ) => r.state === 'open' ).length
				: 0,
		},
		{ id: 'settings', label: __( 'Settings', 'nhrrob-issue-reporter' ) },
	];
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
				modules={ modules }
				active={ active }
				onNavigate={ setActive }
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
