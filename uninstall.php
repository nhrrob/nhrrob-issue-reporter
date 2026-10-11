<?php
/**
 * Fired when the plugin is deleted: removes everything it stored.
 *
 * @package NHRRob\IssueReporter
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove this plugin's options from the current site.
 *
 * @return void
 */
function nhrrob_issue_reporter_uninstall_site() {
	foreach ( [ 'nhrrob_issue_reporter_settings', 'nhrrob_issue_reporter_token', 'nhrrob_issue_reporter_reports' ] as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) as $nhrrob_issue_reporter_site_id ) {
		switch_to_blog( $nhrrob_issue_reporter_site_id );
		nhrrob_issue_reporter_uninstall_site();
		restore_current_blog();
	}
} else {
	nhrrob_issue_reporter_uninstall_site();
}
