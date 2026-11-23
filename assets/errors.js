/**
 * Report Issue: keeps the last console errors of this page, so the feedback
 * widget can offer them with a report. Loaded in the head, for the people the
 * widget is switched on for only. Nothing leaves the browser unless the person
 * sends feedback with the errors switched on.
 */
( function () {
	'use strict';

	var MAX = 20;
	var list = [];

	window.nhrrobIssueReporterErrors = list;

	function keep( text ) {
		text = String( text ).slice( 0, 500 );
		if ( ! text || list[ list.length - 1 ] === text ) {
			return;
		}
		list.push( text );
		if ( list.length > MAX ) {
			list.shift();
		}
	}

	function describe( value ) {
		if ( value && value.stack ) {
			return String( value.stack ).split( '\n' ).slice( 0, 3 ).join( '\n' );
		}
		if ( value && value.message ) {
			return value.message;
		}
		try {
			return typeof value === 'string' ? value : JSON.stringify( value );
		} catch ( e ) {
			return String( value );
		}
	}

	window.addEventListener( 'error', function ( event ) {
		if ( event.target && event.target !== window && ( event.target.src || event.target.href ) ) {
			// A file that did not load: a script, a style or an image.
			keep( 'Failed to load ' + ( event.target.src || event.target.href ) );
			return;
		}
		keep(
			( event.error ? describe( event.error ) : event.message ) +
				( event.filename ? ' (' + event.filename + ':' + event.lineno + ')' : '' )
		);
	}, true );

	window.addEventListener( 'unhandledrejection', function ( event ) {
		keep( 'Unhandled promise: ' + describe( event.reason ) );
	} );

	if ( window.console && typeof window.console.error === 'function' ) {
		var original = window.console.error;
		window.console.error = function () {
			keep( Array.prototype.map.call( arguments, describe ).join( ' ' ) );
			return original.apply( this, arguments );
		};
	}
}() );
