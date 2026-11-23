/**
 * Settings → Agency hub: connect this site to the agency that looks after
 * it, so feedback from here lands in the agency's Inbox.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';

import api from '../api';
import { useToast } from '../components/ToastProvider';
import { Button, Field, Switch } from '../components/ui';

const P = 'nhrrob-issue-reporter-';

export default function HubSettings( { data, setData } ) {
	const toast = useToast();
	const [ key, setKey ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const hub = data.hub || {};

	const run = ( path, method, body, message ) => {
		setBusy( true );
		return api( path, method, body )
			.then( ( next ) => {
				setData( next );
				setKey( '' );
				if ( message ) {
					toast( message );
				}
			} )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) )
			.finally( () => setBusy( false ) );
	};

	if ( ! hub.connected ) {
		return (
			<section className={ P + 'panel' }>
				<div className={ P + 'panel__body ' + P + 'wset' }>
					<span className={ P + 'wset__text' }>
						<strong>
							{ __(
								'Send feedback to your agency',
								'nhrrob-issue-reporter'
							) }
						</strong>
						<span className={ P + 'muted' }>
							{ __(
								'If an agency looks after this site, it can give you a key from its own WordPress site. Feedback from here then lands in its Inbox, with the screenshot and details. Only feedback is sent, and only to that address.',
								'nhrrob-issue-reporter'
							) }
						</span>
					</span>
					<Field
						id={ P + 'hub-key' }
						label={ __( 'Hub key', 'nhrrob-issue-reporter' ) }
						help={ __(
							'It works once, for 24 hours.',
							'nhrrob-issue-reporter'
						) }
					>
						<input
							id={ P + 'hub-key' }
							className={ P + 'input' }
							type="text"
							autoComplete="off"
							spellCheck="false"
							value={ key }
							onChange={ ( e ) => setKey( e.target.value ) }
						/>
					</Field>
					<div>
						<Button
							variant="primary"
							disabled={ busy || ! key.trim() }
							onClick={ () =>
								run(
									'/hub',
									'POST',
									{ key: key.trim() },
									__(
										'Connected to the agency hub',
										'nhrrob-issue-reporter'
									)
								)
							}
						>
							{ __( 'Connect', 'nhrrob-issue-reporter' ) }
						</Button>
					</div>
				</div>
			</section>
		);
	}

	return (
		<section className={ P + 'panel' }>
			<div className={ P + 'panel__body ' + P + 'wset' }>
				<span className={ P + 'wset__text' }>
					<strong>
						{ sprintf(
							/* translators: 1: agency's site name, 2: its address. */
							__(
								'Connected to %1$s (%2$s)',
								'nhrrob-issue-reporter'
							),
							hub.name || hub.address,
							hub.address
						) }
					</strong>
					<span className={ P + 'muted' }>
						{ sprintf(
							/* translators: %s: a date. */
							__( 'Since %s.', 'nhrrob-issue-reporter' ),
							dateI18n(
								getSettings().formats.date,
								hub.since * 1000
							)
						) }
					</span>
				</span>
				<div className={ P + 'wset__row' }>
					<span className={ P + 'wset__text' }>
						<strong>
							{ __(
								'Send new feedback straight away',
								'nhrrob-issue-reporter'
							) }
						</strong>
						<span className={ P + 'muted' }>
							{ __(
								'Off: open it in the Inbox and click “Send to agency”.',
								'nhrrob-issue-reporter'
							) }
						</span>
					</span>
					<Switch
						checked={ !! hub.auto }
						label={ __(
							'Send new feedback straight away',
							'nhrrob-issue-reporter'
						) }
						onChange={ ( on ) =>
							run( '/hub/auto', 'POST', { on } )
						}
					/>
				</div>
				<div>
					<Button
						variant="ghost"
						disabled={ busy }
						onClick={ () =>
							run(
								'/hub',
								'DELETE',
								{},
								__( 'Disconnected', 'nhrrob-issue-reporter' )
							)
						}
					>
						{ __( 'Disconnect', 'nhrrob-issue-reporter' ) }
					</Button>
				</div>
			</div>
		</section>
	);
}
