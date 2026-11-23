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
 * Remove this plugin's options, and the feedback with its screenshots, from the current site.
 *
 * @return void
 */
function nhrrob_issue_reporter_uninstall_site() {
	foreach ( [ 'nhrrob_issue_reporter_settings', 'nhrrob_issue_reporter_token', 'nhrrob_issue_reporter_reports', 'nhrrob_issue_reporter_hub' ] as $option ) {
		delete_option( $option );
	}

	// "Connect GitHub" codes still waiting, one per user: nhrrob_issue_reporter_device_<user id>.
	global $wpdb;
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- once, on uninstall; transients have no API to delete by prefix.
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_nhrrob_issue_reporter_device_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_nhrrob_issue_reporter_device_' ) . '%'
		)
	);

	$nhrrob_issue_reporter_shots = get_posts(
		[
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_nhrrob_issue_reporter_feedback', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- once, on uninstall.
		]
	);

	foreach ( $nhrrob_issue_reporter_shots as $nhrrob_issue_reporter_id ) {
		wp_delete_attachment( $nhrrob_issue_reporter_id, true );
	}

	$nhrrob_issue_reporter_feedback = get_posts(
		[
			'post_type'      => 'nhrrob_ir_feedback',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]
	);

	foreach ( $nhrrob_issue_reporter_feedback as $nhrrob_issue_reporter_id ) {
		wp_delete_post( $nhrrob_issue_reporter_id, true );
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
