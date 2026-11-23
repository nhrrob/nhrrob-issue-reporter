/**
 * What the widget captures: a picture of what the person sees, and the page,
 * browser and screen size. All of it stays in the browser until sent.
 */
/**
 * A picture of the visible part of the page, without the widget itself.
 *
 * @param {Element} host The widget's host element, left out of the picture.
 * @return {Promise<HTMLCanvasElement>} The picture.
 */
export function screenshot( host ) {
	// Loaded on the first click only: most page views never need it.
	return import(
		/* webpackChunkName: "widget-capture" */ 'html2canvas-pro'
	).then( ( { default: html2canvas } ) =>
		html2canvas( document.body, {
			x: window.scrollX,
			y: window.scrollY,
			width: window.innerWidth,
			height: window.innerHeight,
			windowWidth: document.documentElement.clientWidth,
			windowHeight: window.innerHeight,
			scale: 1,
			useCORS: true,
			logging: false,
			ignoreElements: ( el ) => el === host,
		} )
	);
}

/**
 * The browser and system, in a few words, such as "Chrome 142 · macOS".
 *
 * @return {string} Browser.
 */
export function browser() {
	const ua = window.navigator.userAgent;
	const found = [
		[ 'Edge', /Edg\/(\d+)/ ],
		[ 'Opera', /OPR\/(\d+)/ ],
		[ 'Firefox', /Firefox\/(\d+)/ ],
		[ 'Chrome', /(?:Chrome|CriOS)\/(\d+)/ ],
		[ 'Safari', /Version\/(\d+)[\d.]* .*Safari/ ],
	].find( ( [ , re ] ) => re.test( ua ) );
	const name = found ? found[ 0 ] + ' ' + ua.match( found[ 1 ] )[ 1 ] : '';

	let system = '';
	if ( /iPhone|iPad|iPod/.test( ua ) ) {
		system = /iPad/.test( ua ) ? 'iPad' : 'iPhone';
	} else if ( /Android/.test( ua ) ) {
		system = 'Android';
	} else if ( /Mac OS X/.test( ua ) ) {
		system = 'macOS';
	} else if ( /Windows/.test( ua ) ) {
		system = 'Windows';
	} else if ( /Linux/.test( ua ) ) {
		system = 'Linux';
	}

	return [ name, system ].filter( Boolean ).join( ' · ' );
}

/**
 * The size of the browser window.
 *
 * @return {string} Such as "1440 × 900".
 */
export function screenSize() {
	return window.innerWidth + ' × ' + window.innerHeight;
}

// Query values that say which screen it is, kept; any other value (a nonce,
// a token, a search) is left out.
const KEEP = [ 'page', 'post', 'post_type', 'taxonomy', 'action', 'tab', 'p' ];

/**
 * The page: its path, with only the query values that name a screen.
 *
 * @return {string} Path.
 */
export function page() {
	const url = new window.URL( window.location.href );
	const kept = new window.URLSearchParams();
	KEEP.forEach( ( key ) => {
		if ( url.searchParams.has( key ) ) {
			kept.set( key, url.searchParams.get( key ) );
		}
	} );
	const query = kept.toString();
	return url.pathname + ( query ? '?' + query : '' );
}

/**
 * Console errors kept by assets/errors.js.
 *
 * @return {string[]} Errors, oldest first.
 */
export function errors() {
	return Array.isArray( window.nhrrobIssueReporterErrors )
		? window.nhrrobIssueReporterErrors.slice()
		: [];
}
