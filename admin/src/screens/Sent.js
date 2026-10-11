/**
 * Sent: the issues opened from this site, with their state on GitHub.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

import api from '../api';
import { useToast } from '../components/ToastProvider';
import {
	Button,
	External,
	Icon,
	Panel,
	Pill,
	ScreenHeader,
} from '../components/ui';

const P = 'nhrrob-issue-reporter-';

export default function Sent( { data, setData, navigate } ) {
	const toast = useToast();
	const [ loading, setLoading ] = useState( false );

	// Asks GitHub about the open ones; the server limits how often.
	const refresh = useCallback( () => {
		setLoading( true );
		api( '/reports' )
			.then( ( answer ) =>
				setData( ( current ) => ( {
					...current,
					reports: answer.reports,
				} ) )
			)
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) )
			.finally( () => setLoading( false ) );
	}, [ setData, toast ] );

	useEffect( refresh, [ refresh ] );

	return (
		<>
			<ScreenHeader
				title={ __( 'Sent reports', 'nhrrob-issue-reporter' ) }
				lede={ __(
					'Every issue opened from this site. The text of a report is on GitHub; only its link is kept here.',
					'nhrrob-issue-reporter'
				) }
			/>
			<Panel
				title={ __( 'Issues', 'nhrrob-issue-reporter' ) }
				icon="sent"
				flush
				actions={
					<Button small disabled={ loading } onClick={ refresh }>
						<Icon name="refresh" size={ 14 } />
						{ loading
							? __( 'Checking…', 'nhrrob-issue-reporter' )
							: __( 'Check states', 'nhrrob-issue-reporter' ) }
					</Button>
				}
			>
				{ ! data.reports.length ? (
					<div className={ P + 'empty' }>
						<p>
							{ __(
								'No report has been sent from this site yet.',
								'nhrrob-issue-reporter'
							) }
						</p>
						<Button
							variant="soft"
							onClick={ () => navigate( 'report' ) }
						>
							{ __( 'Report an issue', 'nhrrob-issue-reporter' ) }
						</Button>
					</div>
				) : (
					<div className={ P + 'scroll' }>
						<table className={ P + 'grid' }>
							<thead>
								<tr>
									<th scope="col">
										{ __(
											'Issue',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										{ __(
											'Plugin',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										{ __(
											'Repository',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										{ __(
											'State',
											'nhrrob-issue-reporter'
										) }
									</th>
									<th scope="col">
										{ __(
											'Reported',
											'nhrrob-issue-reporter'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ data.reports.map( ( r ) => (
									<tr key={ r.repo + r.number }>
										<td className="is-wrap">
											<External href={ r.url }>
												<span
													className={
														P + 'similar__number'
													}
												>
													#{ r.number }
												</span>
												{ r.title }
											</External>
										</td>
										<td className="is-wrap">
											{ r.plugin }
										</td>
										<td className="is-mono">{ r.repo }</td>
										<td>
											{ r.state === 'closed' ? (
												<Pill tone="off">
													{ __(
														'Closed',
														'nhrrob-issue-reporter'
													) }
												</Pill>
											) : (
												<Pill tone="ok">
													{ __(
														'Open',
														'nhrrob-issue-reporter'
													) }
												</Pill>
											) }
										</td>
										<td>
											{ dateI18n(
												'M j, Y',
												r.time * 1000
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				) }
			</Panel>
		</>
	);
}
