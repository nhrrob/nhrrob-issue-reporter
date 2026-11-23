/**
 * The "What broke?" picker: a button showing the chosen plugin, theme or
 * repository, opening a searchable list grouped by type.
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { Icon } from './ui';

const P = 'nhrrob-issue-reporter-';

export const GROUPS = [
	[ 'plugin', __( 'Plugins', 'nhrrob-issue-reporter' ) ],
	[ 'mu-plugin', __( 'Must-use plugins', 'nhrrob-issue-reporter' ) ],
	[ 'theme', __( 'Themes', 'nhrrob-issue-reporter' ) ],
	[ 'repo', __( 'Other repositories', 'nhrrob-issue-reporter' ) ],
];

export const TYPE_ICON = {
	plugin: 'plugin',
	'mu-plugin': 'plugin',
	theme: 'theme',
	repo: 'repo',
};

/**
 * @param {Object}   root0          Props.
 * @param {Array}    root0.targets  Rows with file, name, version, repo, type.
 * @param {string}   root0.value    The chosen file.
 * @param {Function} root0.onChange Called with a file.
 * @return {Object} Picker element.
 */
export default function TargetPicker( { targets, value, onChange } ) {
	const [ open, setOpen ] = useState( false );
	const [ query, setQuery ] = useState( '' );
	const [ cursor, setCursor ] = useState( 0 );
	const box = useRef();
	const search = useRef();

	const chosen = targets.find( ( t ) => t.file === value );
	const q = query.trim().toLowerCase();
	const matches = targets.filter(
		( t ) =>
			! q ||
			t.name.toLowerCase().includes( q ) ||
			( t.route || t.repo ).toLowerCase().includes( q )
	);
	// The order the list is drawn in, so the arrow keys follow it.
	const ordered = [];
	GROUPS.forEach( ( [ type ] ) =>
		matches
			.filter( ( t ) => t.type === type )
			.forEach( ( t ) => ordered.push( t ) )
	);

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		if ( search.current ) {
			search.current.focus();
		}
		const away = ( e ) => {
			if ( box.current && ! box.current.contains( e.target ) ) {
				setOpen( false );
			}
		};
		document.addEventListener( 'mousedown', away );
		return () => document.removeEventListener( 'mousedown', away );
	}, [ open ] );

	const pick = ( t ) => {
		onChange( t.file );
		setOpen( false );
		setQuery( '' );
	};

	const onKey = ( e ) => {
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			setOpen( false );
		} else if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			setCursor( Math.min( cursor + 1, ordered.length - 1 ) );
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			setCursor( Math.max( cursor - 1, 0 ) );
		} else if ( e.key === 'Enter' && ordered[ cursor ] ) {
			e.preventDefault();
			pick( ordered[ cursor ] );
		}
	};

	return (
		<div className={ P + 'picker' } ref={ box }>
			<button
				type="button"
				id={ P + 'target' }
				className={ P + 'picker__btn' }
				aria-haspopup="listbox"
				aria-expanded={ open }
				onClick={ () => {
					setCursor( 0 );
					setOpen( ! open );
				} }
			>
				<span className={ P + 'tile ' + P + 'tile--accent' }>
					<Icon
						name={ chosen ? TYPE_ICON[ chosen.type ] : 'search' }
						size={ 19 }
					/>
				</span>
				<span className={ P + 'picker__text' }>
					{ chosen ? (
						<>
							<span className={ P + 'picker__name' }>
								{ chosen.name }
								{ chosen.version && (
									<span className={ P + 'picker__ver' }>
										{ ' v' + chosen.version }
									</span>
								) }
							</span>
							<span className={ P + 'picker__repo' }>
								<Icon name="repo" size={ 13 } />
								{ chosen.route || chosen.repo }
							</span>
						</>
					) : (
						<span className={ P + 'picker__name is-empty' }>
							{ __(
								'Choose a plugin, theme or repository',
								'nhrrob-issue-reporter'
							) }
						</span>
					) }
				</span>
				<Icon name="chevrons" size={ 16 } />
			</button>

			{ open && (
				<div className={ P + 'picker__pop' }>
					<div className={ P + 'search' }>
						<Icon name="search" size={ 15 } />
						<input
							ref={ search }
							type="search"
							aria-label={ __(
								'Search plugins, themes and repositories',
								'nhrrob-issue-reporter'
							) }
							placeholder={ __(
								'Search…',
								'nhrrob-issue-reporter'
							) }
							value={ query }
							onChange={ ( e ) => {
								setQuery( e.target.value );
								setCursor( 0 );
							} }
							onKeyDown={ onKey }
						/>
					</div>
					<div className={ P + 'picker__list' } role="listbox">
						{ ! ordered.length && (
							<p className={ P + 'picker__none' }>
								{ __(
									'Nothing matches.',
									'nhrrob-issue-reporter'
								) }
							</p>
						) }
						{ GROUPS.map( ( [ type, label ] ) => {
							const rows = ordered.filter(
								( t ) => t.type === type
							);
							return (
								rows.length > 0 && (
									<div key={ type }>
										<div className={ P + 'picker__group' }>
											{ label }
										</div>
										{ rows.map( ( t ) => (
											<button
												key={ t.file }
												type="button"
												role="option"
												aria-selected={
													t.file === value
												}
												className={
													P +
													'picker__opt' +
													( ordered[ cursor ] === t
														? ' is-cursor'
														: '' ) +
													( t.file === value
														? ' is-chosen'
														: '' )
												}
												onMouseEnter={ () =>
													setCursor(
														ordered.indexOf( t )
													)
												}
												onClick={ () => pick( t ) }
											>
												<span
													className={
														P + 'picker__optname'
													}
												>
													{ t.name }
												</span>
												<span
													className={
														P + 'picker__optrepo'
													}
												>
													{ t.route || t.repo }
												</span>
											</button>
										) ) }
									</div>
								)
							);
						} ) }
					</div>
				</div>
			) }
		</div>
	);
}
