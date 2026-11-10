<?php
/**
 * The REST routes the dashboard app talks to.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rest Class
 *
 * Every route is behind Settings::can_manage(). Nothing here answers with the
 * token; the app only learns whether one is set and where.
 */
class Rest {

	const REST_NAMESPACE = 'nhrrob-issue-reporter/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$gate = [ Settings::class, 'can_manage' ];

		register_rest_route(
			self::REST_NAMESPACE,
			'/state',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'state' ],
				'permission_callback' => $gate,
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => $gate,
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/similar',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'similar' ],
				'permission_callback' => $gate,
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/reports',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'reports' ],
					'permission_callback' => $gate,
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'send' ],
					'permission_callback' => $gate,
				],
			]
		);
	}

	/**
	 * Everything the app shows when it opens. Makes no outside request.
	 *
	 * @return array
	 */
	public function state() {
		$plugins = Repos::all();
		$list    = [];

		foreach ( $plugins as $file => $plugin ) {
			$list[] = [ 'file' => $file ] + $plugin;
		}

		return [
			'plugins'       => $list,
			'hasToken'      => '' !== Settings::token(),
			'tokenInConfig' => Settings::token_is_constant(),
			'environment'   => Environment::text( $plugins ),
			'reports'       => Reports::all(),
		];
	}

	/**
	 * Save repositories and, when given, the token.
	 *
	 * @param \WP_REST_Request $request Request: `repos` (plugin file => repository), `token`, `removeToken`.
	 * @return array|\WP_Error The new state.
	 */
	public function update_settings( $request ) {
		$repos = $request->get_param( 'repos' );

		if ( is_array( $repos ) ) {
			$plugins = Repos::all();
			$keep    = [];

			foreach ( $repos as $file => $repo ) {
				$typed = trim( is_scalar( $repo ) ? (string) $repo : '' );
				$clean = Repos::normalize( $typed );

				if ( ! isset( $plugins[ $file ] ) ) {
					continue;
				}

				if ( '' !== $typed && '' === $clean ) {
					/* translators: %s: plugin name. */
					return new \WP_Error( 'nhrrob_issue_reporter_bad_repo', sprintf( __( 'The repository of %s has to be owner/name or a github.com address.', 'nhrrob-issue-reporter' ), $plugins[ $file ]['name'] ), [ 'status' => 400 ] );
				}

				// Saved only when it differs from what the plugin's own header says.
				if ( '' !== $clean && $clean !== $plugins[ $file ]['detected'] ) {
					$keep[ $file ] = $clean;
				}
			}

			Settings::update( [ 'repos' => $keep ] );
		}

		if ( $request->get_param( 'removeToken' ) ) {
			Settings::update_token( '' );
		} elseif ( is_string( $request->get_param( 'token' ) ) && '' !== trim( $request->get_param( 'token' ) ) ) {
			Settings::update_token( sanitize_text_field( $request->get_param( 'token' ) ) );
		}

		return $this->state();
	}

	/**
	 * Open issues that resemble a title.
	 *
	 * @param \WP_REST_Request $request Request: `plugin` (file) and `title`.
	 * @return array|\WP_Error `issues`: rows of `number`, `title` and `url`.
	 */
	public function similar( $request ) {
		$plugin = $this->plugin( $request->get_param( 'plugin' ) );

		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$issues = GitHub::similar( $plugin['repo'], self::line( $request->get_param( 'title' ) ) );

		return is_wp_error( $issues ) ? $this->refused( $issues ) : [ 'issues' => $issues ];
	}

	/**
	 * The sent reports, after asking GitHub about the open ones.
	 *
	 * @return array `reports`.
	 */
	public function reports() {
		Reports::refresh();

		return [ 'reports' => Reports::all() ];
	}

	/**
	 * Send a report.
	 *
	 * @param \WP_REST_Request $request Request: `plugin` (file), `title`, `what`, `steps`, `environment`.
	 * @return array|\WP_Error `report` (the new row) and `reports`.
	 */
	public function send( $request ) {
		$plugin = $this->plugin( $request->get_param( 'plugin' ) );

		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$title = self::line( $request->get_param( 'title' ) );
		$what  = self::text( $request->get_param( 'what' ) );

		if ( '' === $title || '' === $what ) {
			return new \WP_Error( 'nhrrob_issue_reporter_incomplete', __( 'A title and a description of what happened are needed.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		$issue = GitHub::create(
			$plugin['repo'],
			$title,
			GitHub::body( $what, self::text( $request->get_param( 'steps' ) ), self::text( $request->get_param( 'environment' ) ) )
		);

		if ( is_wp_error( $issue ) ) {
			return $this->refused( $issue );
		}

		Reports::add( $plugin['name'], $plugin['repo'], $issue, $title );

		$reports = Reports::all();

		return [
			'report'  => $reports[0],
			'reports' => $reports,
		];
	}

	/**
	 * Text as typed, for an issue on GitHub. Tags are not stripped: a report
	 * often quotes code such as `Array<string>`, and GitHub sanitizes the HTML
	 * of an issue itself. The text is never printed by this plugin.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Most characters kept; GitHub takes 65536 in a body.
	 * @return string Valid UTF-8 without control characters other than tabs and newlines.
	 */
	public static function text( $value, $max = 20000 ) {
		$text = wp_check_invalid_utf8( is_scalar( $value ) ? (string) $value : '' );
		$text = (string) preg_replace( '/[^\P{Cc}\t\n]/u', '', str_replace( "\r\n", "\n", $text ) );

		return trim( mb_substr( $text, 0, $max ) );
	}

	/**
	 * One line of text as typed, such as a title.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function line( $value ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', self::text( $value, 200 ) ) );
	}

	/**
	 * The installed plugin a request names, when its repository is known.
	 *
	 * @param mixed $file Plugin file.
	 * @return array|\WP_Error One plugin, as Repos::all() returns it.
	 */
	private function plugin( $file ) {
		$plugins = Repos::all();

		if ( ! is_string( $file ) || ! isset( $plugins[ $file ] ) || '' === $plugins[ $file ]['repo'] ) {
			return new \WP_Error( 'nhrrob_issue_reporter_no_repo', __( 'Choose a plugin that has a repository.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		return $plugins[ $file ];
	}

	/**
	 * A GitHub error as an answer the app shows to the user.
	 *
	 * @param \WP_Error $error Error from GitHub::request().
	 * @return \WP_Error
	 */
	private function refused( $error ) {
		return new \WP_Error( $error->get_error_code(), $error->get_error_message(), [ 'status' => 400 ] );
	}
}
