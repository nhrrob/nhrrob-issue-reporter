/**
 * Screenshots: thumbnails and a zone to drop, paste or browse images.
 * Pasting works anywhere in the form (Report.js listens for it).
 */
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { Icon } from './ui';

const P = 'nhrrob-issue-reporter-';
export const TYPES = [ 'image/png', 'image/jpeg', 'image/webp', 'image/gif' ];
export const MAX_FILES = 5;

/**
 * @param {Object}   root0          Props.
 * @param {Array}    root0.images   Rows of { file, preview }.
 * @param {Function} root0.onAdd    Called with a FileList.
 * @param {Function} root0.onRemove Called with an image row.
 * @param {boolean}  root0.disabled While sending.
 * @return {Object} Element.
 */
export default function Shots( { images, onAdd, onRemove, disabled } ) {
	const input = useRef();
	const [ over, setOver ] = useState( false );
	const full = images.length >= MAX_FILES;

	return (
		<div className={ P + 'shots' }>
			{ images.map( ( image ) => (
				<figure key={ image.preview } className={ P + 'shot' }>
					<img src={ image.preview } alt={ image.file.name } />
					<button
						type="button"
						className={ P + 'shot__x' }
						aria-label={ sprintf(
							/* translators: %s: file name. */
							__( 'Remove %s', 'nhrrob-issue-reporter' ),
							image.file.name
						) }
						disabled={ disabled }
						onClick={ () => onRemove( image ) }
					>
						<Icon name="x" size={ 12 } />
					</button>
				</figure>
			) ) }
			{ ! full && (
				<button
					type="button"
					className={ P + 'drop' + ( over ? ' is-over' : '' ) }
					disabled={ disabled }
					onClick={ () => input.current && input.current.click() }
					onDragOver={ ( e ) => {
						e.preventDefault();
						setOver( true );
					} }
					onDragLeave={ () => setOver( false ) }
					onDrop={ ( e ) => {
						e.preventDefault();
						setOver( false );
						onAdd( e.dataTransfer.files );
					} }
				>
					<span className={ P + 'tile ' + P + 'tile--accent' }>
						<Icon name="upload" size={ 18 } />
					</span>
					<span className={ P + 'drop__main' }>
						{ __(
							'Drop images, paste, or browse',
							'nhrrob-issue-reporter'
						) }
					</span>
					<span className={ P + 'drop__help' }>
						{ __(
							'PNG, JPEG, WebP or GIF · up to 5 MB each, 5 in all',
							'nhrrob-issue-reporter'
						) }
					</span>
				</button>
			) }
			<input
				ref={ input }
				className="screen-reader-text"
				type="file"
				tabIndex={ -1 }
				aria-hidden="true"
				accept={ TYPES.join( ',' ) }
				multiple
				onChange={ ( e ) => {
					onAdd( e.target.files );
					e.target.value = '';
				} }
			/>
		</div>
	);
}
