/**
 * Report Issue: the feedback widget, on the site's pages and in wp-admin, for
 * the people it is switched on for. It draws inside a shadow root, so neither
 * the theme's styles nor wp-admin's reach it, and its own styles leak nowhere.
 */
import { createRoot } from '@wordpress/element';

import Widget from './widget/Widget';
import './widget/style.scss';

const boot = window.nhrrobIssueReporterWidget || {};

function mount() {
	const host = document.createElement( 'div' );
	host.id = 'nhrrob-issue-reporter-widget';
	document.body.appendChild( host );

	const shadow = host.attachShadow( { mode: 'open' } );
	const style = document.createElement( 'link' );
	style.rel = 'stylesheet';
	style.href = boot.css;
	shadow.appendChild( style );

	const root = document.createElement( 'div' );
	shadow.appendChild( root );
	createRoot( root ).render( <Widget boot={ boot } host={ host } /> );

	// The admin-bar item, on the site and in wp-admin.
	document.addEventListener( 'click', ( event ) => {
		const item =
			event.target.closest &&
			event.target.closest(
				'#wp-admin-bar-' + boot.node + ' > .ab-item'
			);
		if ( item ) {
			event.preventDefault();
			window.dispatchEvent(
				new window.CustomEvent( 'nhrrob-issue-reporter-open' )
			);
		}
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
