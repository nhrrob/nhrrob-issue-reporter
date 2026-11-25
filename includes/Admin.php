<?php
/**
 * The dashboard screen: Tools → Report Issue.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Class
 *
 * Prints the mount point of the app in admin/build and adds "Report issue"
 * under each plugin whose repository is known.
 */
class Admin {

	const SLUG = 'nhrrob-issue-reporter';

	/**
	 * Hook suffix of the screen.
	 *
	 * @var string|false
	 */
	private $hook = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'plugin_action_links', [ $this, 'action_link' ], 10, 3 );
	}

	/**
	 * Add the screen under Tools.
	 *
	 * @return void
	 */
	public function menu() {
		/** This filter is documented in includes/Settings.php */
		$capability = (string) apply_filters( 'nhrrob_issue_reporter_capability', 'activate_plugins' );
		/**
		 * Filters the menu this screen sits under: '' keeps it where it is, a
		 * parent menu's slug moves it there (one menu for several plugins).
		 *
		 * @param string $parent Parent menu slug, or ''.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shared by every NHR plugin, so it can't carry this one's prefix.
		$parent = (string) apply_filters( 'nhr_admin_parent_menu', '' );

		$this->hook = '' === $parent
			? add_management_page( __( 'Report Issue', 'nhrrob-issue-reporter' ), __( 'Report Issue', 'nhrrob-issue-reporter' ), $capability, self::SLUG, [ $this, 'render' ] )
			: add_submenu_page( $parent, __( 'Report Issue', 'nhrrob-issue-reporter' ), __( 'Report Issue', 'nhrrob-issue-reporter' ), $capability, self::SLUG, [ $this, 'render' ] );
	}

	/**
	 * Add "Report issue" under a plugin in the Plugins list, when its repository is known.
	 *
	 * @param array  $actions Links under the plugin.
	 * @param string $file    Plugin file.
	 * @param array  $data    Plugin data.
	 * @return array
	 */
	public function action_link( $actions, $file, $data ) {
		if ( ! Settings::can_manage() ) {
			return $actions;
		}

		$settings = Settings::get();
		$repo     = isset( $settings['repos'][ $file ] ) ? $settings['repos'][ $file ] : Repos::detect( (array) $data );

		if ( '' !== $repo ) {
			$url = add_query_arg(
				[
					'page'   => self::SLUG,
					'plugin' => rawurlencode( $file ),
				],
				admin_url( 'tools.php' )
			);

			$actions['nhrrob_issue_reporter'] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Report issue', 'nhrrob-issue-reporter' ) );
		}

		return $actions;
	}

	/**
	 * Print the mount point.
	 *
	 * @return void
	 */
	public function render() {
		// The heading and wp-header-end marker make core move admin notices
		// inside .wrap; the app draws its own title bar.
		?>
		<div class="wrap nhrrob-issue-reporter-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Report Issue', 'nhrrob-issue-reporter' ); ?></h1>
			<hr class="wp-header-end">
			<div id="nhrrob-issue-reporter-app"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue the app on its own screen only.
	 *
	 * @param string $hook Hook suffix of the current screen.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$dir = NHRROB_ISSUE_REPORTER_PATH . '/admin/build';

		if ( ! $this->hook || $hook !== $this->hook || ! file_exists( $dir . '/index.asset.php' ) ) {
			return; // Another screen, or not built yet: run `npm run build`.
		}

		$asset  = require $dir . '/index.asset.php';
		$url    = plugins_url( 'admin/build', NHRROB_ISSUE_REPORTER_FILE );
		$handle = 'nhrrob-issue-reporter-index';

		wp_enqueue_script( $handle, $url . '/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( $handle, 'nhrrob-issue-reporter' );
		wp_localize_script(
			$handle,
			'nhrrobIssueReporterApp',
			[
				'restRoot'   => esc_url_raw( rest_url() ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'pluginName' => __( 'Report Issue', 'nhrrob-issue-reporter' ),
				// Shown under the app's name; React escapes it.
				'siteName'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'version'    => NHRROB_ISSUE_REPORTER_VERSION,
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preselects a plugin in the form; changes nothing.
				'plugin'     => isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '',
			]
		);

		if ( file_exists( $dir . '/style-index.css' ) ) {
			// Own version: the asset hash covers the script only.
			wp_enqueue_style( $handle, $url . '/style-index.css', [], (string) filemtime( $dir . '/style-index.css' ) );
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}

		/**
		 * After the app's script was enqueued. Another plugin enqueues its own
		 * script here, depending on this handle, and adds screens through the
		 * `nhrrobIssueReporter.app` JavaScript filter.
		 *
		 * @param string $handle The app's script handle.
		 */
		do_action( 'nhrrob_issue_reporter_enqueue', $handle );
	}
}
