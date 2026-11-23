/**
 * The environment as one card per line, each with a switch, or as text.
 * Either way, what is shown is exactly what is sent.
 */
import { __, sprintf } from '@wordpress/i18n';

import { Icon, Switch } from './ui';

const P = 'nhrrob-issue-reporter-';

const ICONS = {
	WordPress: 'globe',
	PHP: 'code',
	'Active theme': 'theme',
	'Active plugins': 'list',
	Plugin: 'plugin',
	'Must-use plugin': 'plugin',
	Theme: 'theme',
};

/**
 * Splits "- Key: value" lines into rows. A line without a key keeps its text
 * as the value.
 *
 * @param {string} text Environment text.
 * @return {Array} Rows of { key, value, line }.
 */
export function rows( text ) {
	return text
		.split( '\n' )
		.filter( ( line ) => line.trim() )
		.map( ( line ) => {
			const m = line.match( /^-\s*([^:]+):\s*(.*)$/ );
			return m
				? { key: m[ 1 ].trim(), value: m[ 2 ], line }
				: { key: line.trim(), value: '', line };
		} );
}

/**
 * @param {Object}   root0          Props.
 * @param {string}   root0.text     The environment text.
 * @param {Array}    root0.off      Keys switched off.
 * @param {Function} root0.onToggle Called with a key.
 * @return {Object} Element.
 */
export default function EnvCards( { text, off, onToggle } ) {
	return (
		<div className={ P + 'env' }>
			{ rows( text ).map( ( r ) => {
				const on = ! off.includes( r.key );
				return (
					<div
						key={ r.key }
						className={ P + 'env__card' + ( on ? '' : ' is-off' ) }
					>
						<span className={ P + 'tile ' + P + 'tile--soft' }>
							<Icon
								name={ ICONS[ r.key ] || 'file' }
								size={ 16 }
							/>
						</span>
						<span className={ P + 'env__text' }>
							<span className={ P + 'env__key' }>{ r.key }</span>
							<span
								className={ P + 'env__value' }
								title={ r.value }
							>
								{ r.value || '—' }
							</span>
						</span>
						<Switch
							checked={ on }
							onChange={ () => onToggle( r.key ) }
							label={ sprintf(
								/* translators: %s: a line of the environment, such as PHP. */
								__( 'Send %s', 'nhrrob-issue-reporter' ),
								r.key
							) }
						/>
					</div>
				);
			} ) }
		</div>
	);
}
