<?php
/**
 * The reports that were sent from this site.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports Class
 *
 * One option, newest first, capped at MAX rows. A row holds what is needed to
 * link to the issue and show its state, not the text of the report.
 */
class Reports {

	const OPTION  = 'nhrrob_issue_reporter_reports';
	const MAX     = 100;
	const RECHECK = 900;

	/**
	 * All rows, newest first.
	 *
	 * @return array Rows of `plugin`, `repo`, `number`, `url`, `title`, `time`, `state`, `checked`
	 *               and `comments` (added from this site, once there is one).
	 */
	public static function all() {
		$rows = get_option( self::OPTION, [] );

		return is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : [];
	}

	/**
	 * Remember an issue that was just opened.
	 *
	 * @param string $plugin Plugin name.
	 * @param string $repo   Repository, "owner/name".
	 * @param array  $issue  The issue's `number` and `url`.
	 * @param string $title  Title.
	 * @return void
	 */
	public static function add( $plugin, $repo, array $issue, $title ) {
		$rows = self::all();

		array_unshift(
			$rows,
			[
				'plugin'  => (string) $plugin,
				'repo'    => (string) $repo,
				'number'  => (int) $issue['number'],
				'url'     => (string) $issue['url'],
				'title'   => (string) $title,
				'time'    => time(),
				'state'   => 'open',
				'checked' => time(),
			]
		);

		update_option( self::OPTION, array_slice( $rows, 0, self::MAX ), false );
	}

	/**
	 * Count a comment added to a sent report, when the issue is one.
	 *
	 * @param string $repo   Repository, "owner/name".
	 * @param int    $number Issue number.
	 * @return void
	 */
	public static function commented( $repo, $number ) {
		$rows = self::all();

		foreach ( $rows as $index => $row ) {
			if ( $row['repo'] === $repo && (int) $row['number'] === (int) $number ) {
				$rows[ $index ]['comments'] = ( isset( $row['comments'] ) ? (int) $row['comments'] : 0 ) + 1;
				update_option( self::OPTION, $rows, false );
				return;
			}
		}
	}

	/**
	 * Ask GitHub whether the open issues are still open. At most $limit issues
	 * per call, and none that was asked about in the last RECHECK seconds.
	 *
	 * @param int $limit Most issues to ask about.
	 * @return void
	 */
	public static function refresh( $limit = 10 ) {
		$rows    = self::all();
		$changed = false;

		foreach ( $rows as $index => $row ) {
			if ( $limit < 1 ) {
				break;
			}

			if ( 'open' !== $row['state'] || time() - (int) $row['checked'] < self::RECHECK ) {
				continue;
			}

			$state = GitHub::state( $row['repo'], $row['number'] );

			$rows[ $index ]['checked'] = time();
			$rows[ $index ]['state']   = '' !== $state ? $state : $row['state'];
			$changed                   = true;
			--$limit;
		}

		if ( $changed ) {
			update_option( self::OPTION, $rows, false );
		}
	}
}
