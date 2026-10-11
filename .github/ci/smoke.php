<?php
/**
 * Runtime smoke test (dev/CI only — never shipped: .github is in .distignore).
 *
 * Lint and unit tests don't execute the plugin inside WordPress, so a PHP
 * version can pass both and still raise deprecations or fatals at runtime.
 * This prints every tab of the plugin's screen as an administrator, with all
 * outgoing requests blocked, and fails on any PHP notice, warning or
 * deprecation raised from the plugin's own files, or on a tab that prints
 * nothing.
 *
 *   wp eval-file .github/ci/smoke.php
 *
 * Nothing is sent or saved.
 *
 * @package NhrrobCiSmoke
 */

// phpcs:ignoreFile -- dev tooling, not part of the plugin.

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Only ever runs inside WordPress via `wp eval-file`.
}

$nhrsmoke_dir    = realpath( getenv( 'SMOKE_PLUGIN_DIR' ) ?: dirname( __DIR__, 2 ) );
$nhrsmoke_errors = [];

set_error_handler(
	function ( $no, $message, $file, $line ) use ( &$nhrsmoke_errors, $nhrsmoke_dir ) {
		if ( 0 === strpos( (string) realpath( $file ), $nhrsmoke_dir . DIRECTORY_SEPARATOR ) ) {
			$nhrsmoke_errors[ $message . ' @ ' . substr( $file, strlen( $nhrsmoke_dir ) + 1 ) . ':' . $line ] = true;
		}
		return true;
	},
	E_ALL
);

if ( ! class_exists( 'NHRRob\IssueReporter\Admin' ) ) {
	WP_CLI::error( 'The plugin is not active.' );
}

$nhrsmoke_admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
wp_set_current_user( (int) $nhrsmoke_admins[0] );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

add_filter( 'extra_plugin_headers', [ 'NHRRob\IssueReporter\Repos', 'headers' ] );
add_filter(
	'pre_http_request',
	function () {
		return new WP_Error( 'nhrsmoke_blocked', 'Requests are blocked in the smoke test.' );
	}
);

// One sent report, so the Sent tab prints its table and asks for the state.
add_filter(
	'pre_option_nhrrob_issue_reporter_reports',
	function () {
		return [
			[
				'plugin'  => 'Example',
				'repo'    => 'example/example',
				'number'  => 1,
				'url'     => 'https://github.com/example/example/issues/1',
				'title'   => 'Example',
				'time'    => time(),
				'state'   => 'open',
				'checked' => 0,
			],
		];
	}
);
add_filter( 'pre_update_option_nhrrob_issue_reporter_reports', function ( $value, $old ) { return $old; }, 10, 2 );
add_filter( 'pre_option_nhrrob_issue_reporter_token', function () { return 'smoke'; } );

$nhrsmoke_admin  = new NHRRob\IssueReporter\Admin();
$nhrsmoke_failed = [];

foreach ( [ 'report', 'sent', 'settings' ] as $nhrsmoke_tab ) {
	$_GET['tab'] = $nhrsmoke_tab;

	ob_start();
	$nhrsmoke_admin->render();
	$nhrsmoke_html = ob_get_clean();

	if ( false === strpos( $nhrsmoke_html, 'nav-tab-active' ) || strlen( $nhrsmoke_html ) < 600 ) {
		$nhrsmoke_failed[] = $nhrsmoke_tab;
	}
}

restore_error_handler();

foreach ( array_keys( $nhrsmoke_errors ) as $nhrsmoke_error ) {
	WP_CLI::warning( $nhrsmoke_error );
}

if ( $nhrsmoke_errors || $nhrsmoke_failed ) {
	WP_CLI::error( sprintf( '%d PHP notice(s); tabs that did not print: %s', count( $nhrsmoke_errors ), $nhrsmoke_failed ? implode( ', ', $nhrsmoke_failed ) : 'none' ) );
}

WP_CLI::success( sprintf( 'PHP %s: three tabs printed, no notices.', PHP_VERSION ) );
