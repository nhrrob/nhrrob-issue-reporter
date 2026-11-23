/**
 * Settings → Client widget: switch the feedback widget on, and choose who
 * sees it and whether the site's pages get a floating button.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { Button, Switch } from '../components/ui';

const P = 'nhrrob-issue-reporter-';

export default function WidgetSettings( { data, busy, save } ) {
	const [ widget, setWidget ] = useState( data.widget );
	const changed = JSON.stringify( widget ) !== JSON.stringify( data.widget );

	const role = ( slug, on ) =>
		setWidget( {
			...widget,
			roles: on
				? widget.roles.concat( [ slug ] )
				: widget.roles.filter( ( r ) => r !== slug ),
		} );

	return (
		<section className={ P + 'panel' }>
			<div className={ P + 'panel__body ' + P + 'wset' }>
				<div className={ P + 'wset__row' }>
					<span className={ P + 'wset__text' }>
						<strong>
							{ __( 'Feedback widget', 'nhrrob-issue-reporter' ) }
						</strong>
						<span className={ P + 'muted' }>
							{ __(
								'A “Report an issue” item in the admin bar, on the site and in the dashboard, where people mark a spot on a screenshot and say what is wrong.',
								'nhrrob-issue-reporter'
							) }
						</span>
					</span>
					<Switch
						checked={ widget.enabled }
						label={ __(
							'Show the feedback widget',
							'nhrrob-issue-reporter'
						) }
						onChange={ ( enabled ) =>
							setWidget( { ...widget, enabled } )
						}
					/>
				</div>

				<fieldset className={ P + 'wset__group' }>
					<legend>
						{ __( 'Who sees it', 'nhrrob-issue-reporter' ) }
					</legend>
					<p className={ P + 'muted' }>
						{ __(
							'Logged-in people with one of these roles. Visitors never see it.',
							'nhrrob-issue-reporter'
						) }
					</p>
					<div className={ P + 'wset__roles' }>
						{ data.roles.map( ( r ) => (
							<label
								key={ r.slug }
								className={ P + 'check' }
								htmlFor={ P + 'role-' + r.slug }
							>
								<input
									id={ P + 'role-' + r.slug }
									type="checkbox"
									checked={ widget.roles.includes( r.slug ) }
									onChange={ ( e ) =>
										role( r.slug, e.target.checked )
									}
								/>
								<span>{ r.name }</span>
							</label>
						) ) }
					</div>
				</fieldset>

				<div className={ P + 'wset__row' }>
					<span className={ P + 'wset__text' }>
						<strong>
							{ __(
								'Floating button on the site',
								'nhrrob-issue-reporter'
							) }
						</strong>
						<span className={ P + 'muted' }>
							{ __(
								'A “Feedback” button in the corner of the site’s pages, for people who do not look at the admin bar.',
								'nhrrob-issue-reporter'
							) }
						</span>
					</span>
					<Switch
						checked={ widget.button }
						label={ __(
							'Floating button on the site',
							'nhrrob-issue-reporter'
						) }
						onChange={ ( button ) =>
							setWidget( { ...widget, button } )
						}
					/>
				</div>

				<div className={ P + 'wset__group' }>
					<strong>
						{ __( 'Feedback goes to', 'nhrrob-issue-reporter' ) }
					</strong>
					<p className={ P + 'muted' }>
						{ __(
							'The Inbox here. You read it there and choose what goes to GitHub.',
							'nhrrob-issue-reporter'
						) }
					</p>
				</div>

				<div className={ P + 'wset__group' }>
					<strong>
						{ __(
							'Captured with each report',
							'nhrrob-issue-reporter'
						) }
					</strong>
					<div className={ P + 'wset__chips' }>
						{ [
							__(
								'Screenshot with marks',
								'nhrrob-issue-reporter'
							),
							__( 'Page', 'nhrrob-issue-reporter' ),
							__( 'Browser and system', 'nhrrob-issue-reporter' ),
							__( 'Window size', 'nhrrob-issue-reporter' ),
							__( 'Console errors', 'nhrrob-issue-reporter' ),
						].map( ( c ) => (
							<span key={ c }>{ c }</span>
						) ) }
					</div>
					<p className={ P + 'muted' }>
						{ __(
							'The person sees each one before sending and can switch any of them off. The WordPress, theme and plugin versions are added when you send it on to GitHub.',
							'nhrrob-issue-reporter'
						) }
					</p>
				</div>

				<div>
					<Button
						variant="primary"
						disabled={ busy || ! changed }
						onClick={ () =>
							save(
								{ widget },
								__(
									'Widget settings saved',
									'nhrrob-issue-reporter'
								)
							)
						}
					>
						{ __( 'Save', 'nhrrob-issue-reporter' ) }
					</Button>
				</div>
			</div>
		</section>
	);
}
