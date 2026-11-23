<?php
/**
 * Plugin Name: NHR Issue Reporter – Client Feedback & Bug Reports
 * Description: Client feedback and bug reports for WordPress: a Report button with screenshot markup, an Inbox, and issues sent to GitHub.
 * Author: Nazmul Hasan Robin
 * Author URI: https://profiles.wordpress.org/nhrrob/
 * Version: 0.6.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Text Domain: nhrrob-issue-reporter
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package NHRRob\IssueReporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class NHRRob_Issue_Reporter {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	const VERSION = '0.6.0';

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'NHRROB_ISSUE_REPORTER_VERSION', self::VERSION );
		define( 'NHRROB_ISSUE_REPORTER_FILE', __FILE__ );
		define( 'NHRROB_ISSUE_REPORTER_PATH', __DIR__ );

		add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
	}

	/**
	 * Initialize a singleton instance
	 *
	 * @return self
	 */
	public static function init(): self {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Initialize the plugin. Everything it does happens in the dashboard, in
	 * a REST request the dashboard app or the feedback widget makes, or when
	 * an ability is run. On the site's pages only the widget loads, and only
	 * for the people it is switched on for.
	 *
	 * @return void
	 */
	public function init_plugin(): void {
		add_filter( 'extra_plugin_headers', [ \NHRRob\IssueReporter\Repos::class, 'headers' ] );
		add_filter( 'extra_theme_headers', [ \NHRRob\IssueReporter\Repos::class, 'theme_headers' ] );
		( new \NHRRob\IssueReporter\Rest() )->init();
		( new \NHRRob\IssueReporter\Connect() )->init();
		( new \NHRRob\IssueReporter\Abilities() )->init();
		( new \NHRRob\IssueReporter\Feedback\Store() )->init();
		( new \NHRRob\IssueReporter\Feedback\Routes() )->init();
		( new \NHRRob\IssueReporter\Feedback\Widget() )->init();
		( new \NHRRob\IssueReporter\Hub() )->init();

		if ( is_admin() ) {
			( new \NHRRob\IssueReporter\Admin() )->init();
		}
	}
}

/**
 * Initializes the main plugin
 *
 * @return NHRRob_Issue_Reporter
 */
function nhrrob_issue_reporter(): NHRRob_Issue_Reporter {
	return NHRRob_Issue_Reporter::init();
}

// Call the plugin.
nhrrob_issue_reporter();
