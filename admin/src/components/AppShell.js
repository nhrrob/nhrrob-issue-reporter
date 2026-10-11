/**
 * App shell: gradient app bar, collapsible left nav and the content slot.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { Icon } from './ui';

const THEME_KEY = 'nhrrob-issue-reporter-theme';
const NAV_KEY = 'nhrrob-issue-reporter-nav';

function remember( key, value ) {
	try {
		window.localStorage.setItem( key, value );
	} catch ( e ) {
		/* storage unavailable — the choice lasts for this page only */
	}
}

export default function AppShell( {
	modules,
	active,
	onNavigate,
	title,
	children,
} ) {
	const [ collapsed, setCollapsed ] = useState( false );
	const [ theme, setTheme ] = useState( '' ); // '' follows the system setting

	useEffect( () => {
		try {
			const saved = window.localStorage.getItem( THEME_KEY );
			if ( saved === 'dark' || saved === 'light' ) {
				setTheme( saved );
			}
			setCollapsed(
				window.localStorage.getItem( NAV_KEY ) === 'collapsed'
			);
		} catch ( e ) {
			/* storage unavailable */
		}
	}, [] );

	const prefersDark =
		window.matchMedia &&
		window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
	const isDark = theme ? theme === 'dark' : prefersDark;

	return (
		<div
			className={
				'nhrrob-issue-reporter-app' +
				( collapsed ? ' is-collapsed' : '' )
			}
			data-theme={ theme || undefined }
		>
			<header className="nhrrob-issue-reporter-bar">
				<button
					type="button"
					className="nhrrob-issue-reporter-bar__btn"
					aria-label={
						collapsed
							? __( 'Expand menu', 'nhrrob-issue-reporter' )
							: __( 'Collapse menu', 'nhrrob-issue-reporter' )
					}
					onClick={ () => {
						remember(
							NAV_KEY,
							collapsed ? 'expanded' : 'collapsed'
						);
						setCollapsed( ! collapsed );
					} }
				>
					<Icon name="menu" />
				</button>
				<span className="nhrrob-issue-reporter-bar__mark">
					<Icon name="bug" />
				</span>
				<span className="nhrrob-issue-reporter-bar__title">
					{ title }
				</span>
				<span className="nhrrob-issue-reporter-bar__spacer" />
				<button
					type="button"
					className="nhrrob-issue-reporter-bar__btn"
					aria-label={
						isDark
							? __(
									'Switch to light theme',
									'nhrrob-issue-reporter'
							  )
							: __(
									'Switch to dark theme',
									'nhrrob-issue-reporter'
							  )
					}
					onClick={ () => {
						const next = isDark ? 'light' : 'dark';
						remember( THEME_KEY, next );
						setTheme( next );
					} }
				>
					<Icon name={ isDark ? 'sun' : 'moon' } />
				</button>
			</header>

			<div className="nhrrob-issue-reporter-body">
				<nav className="nhrrob-issue-reporter-nav" aria-label={ title }>
					{ modules.map( ( m ) => (
						<button
							key={ m.id }
							type="button"
							className={
								'nhrrob-issue-reporter-nav__item' +
								( m.id === active ? ' is-active' : '' ) +
								( m.id === 'settings' ? ' is-last' : '' )
							}
							aria-current={
								m.id === active ? 'page' : undefined
							}
							title={ m.label }
							onClick={ () => onNavigate( m.id ) }
						>
							<Icon name={ m.id } />
							<span className="nhrrob-issue-reporter-nav__label">
								{ m.label }
							</span>
							{ m.count > 0 && (
								<span className="nhrrob-issue-reporter-nav__count">
									{ m.count }
								</span>
							) }
						</button>
					) ) }
				</nav>
				<main className="nhrrob-issue-reporter-content">
					<div className="nhrrob-issue-reporter-screen">
						{ children }
					</div>
				</main>
			</div>
		</div>
	);
}
