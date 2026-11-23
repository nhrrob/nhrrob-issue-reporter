<?php
/**
 * The feedback widget: who sees it, the admin-bar item, and its scripts.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter\Feedback;

use NHRRob\IssueReporter\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget Class
 *
 * Only for logged-in people whose role is chosen in Settings, and only when
 * the widget is switched on. Everyone else gets nothing: no script, no style,
 * no admin-bar item.
 */
class Widget {

	const HANDLE = 'nhrrob-issue-reporter-widget';
	const NODE   = 'nhrrob-issue-reporter';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 90 );
	}

	/**
	 * Whether the current person may send feedback from the widget.
	 *
	 * @return bool
	 */
	public static function can_send() {
		$widget = Settings::get()['widget'];

		if ( ! $widget['enabled'] || ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		return (bool) array_intersect( (array) $user->roles, $widget['roles'] );
	}

	/**
	 * Add "Report an issue" to the admin bar, on the site and in wp-admin.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public function admin_bar( $bar ) {
		if ( ! self::can_send() ) {
			return;
		}

		$bar->add_node(
			[
				'id'     => self::NODE,
				'title'  => '<span class="ab-icon dashicons dashicons-feedback" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Report an issue', 'nhrrob-issue-reporter' ) . '</span>',
				'href'   => '#',
				'parent' => 'top-secondary',
			]
		);
	}

	/**
	 * Load the error collector early and the widget late, for permitted people only.
	 *
	 * @return void
	 */
	public function enqueue() {
		$dir = NHRROB_ISSUE_REPORTER_PATH . '/admin/build';

		if ( ! self::can_send() || ! file_exists( $dir . '/widget.asset.php' ) || ! file_exists( $dir . '/style-widget.css' ) ) {
			return;
		}

		$assets = plugins_url( 'assets', NHRROB_ISSUE_REPORTER_FILE );
		$build  = plugins_url( 'admin/build', NHRROB_ISSUE_REPORTER_FILE );
		$asset  = require $dir . '/widget.asset.php';

		// In the head, so errors from scripts that run before the widget are caught too.
		wp_enqueue_script( self::HANDLE . '-errors', $assets . '/errors.js', [], NHRROB_ISSUE_REPORTER_VERSION, false );

		wp_enqueue_script( self::HANDLE, $build . '/widget.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'nhrrob-issue-reporter' );
		wp_localize_script(
			self::HANDLE,
			'nhrrobIssueReporterWidget',
			[
				'restRoot' => esc_url_raw( rest_url() ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				// The widget draws inside a shadow root, so the theme's styles cannot reach it; it loads its own.
				'css'      => $build . '/style-widget.css?ver=' . rawurlencode( (string) filemtime( $dir . '/style-widget.css' ) ),
				'button'   => ! is_admin() && Settings::get()['widget']['button'],
				'node'     => self::NODE,
				'name'     => wp_get_current_user()->display_name,
			]
		);
	}
}
