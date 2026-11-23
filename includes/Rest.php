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

		register_rest_route(
			self::REST_NAMESPACE,
			'/screenshots',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'screenshot' ],
				'permission_callback' => $gate,
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/comments',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'comment' ],
				'permission_callback' => $gate,
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

		$state = [
			'plugins'       => $list,
			'publicSite'    => Screenshots::site_is_public(),
			'hasToken'      => '' !== Settings::token(),
			'tokenInConfig' => Settings::token_is_constant(),
			'environment'   => Environment::text( $plugins ),
			'reports'       => Reports::all(),
			'widget'        => Settings::get()['widget'],
			'roles'         => self::roles(),
			'inbox'         => Feedback\Store::waiting(),
		];

		/**
		 * What the app gets when it opens. Another plugin may add its own keys.
		 *
		 * @param array $state State.
		 */
		return (array) apply_filters( 'nhrrob_issue_reporter_state', $state );
	}

	/**
	 * The site's roles, for choosing who sees the feedback widget.
	 *
	 * @return array Rows of `slug` and `name`.
	 */
	public static function roles() {
		$roles = [];

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$roles[] = [
				'slug' => (string) $slug,
				'name' => translate_user_role( $name ),
			];
		}

		return $roles;
	}

	/**
	 * Save repositories and, when given, the token.
	 *
	 * A repository that is new is checked with GitHub when a token is set, so
	 * a mistyped one is refused right away.
	 *
	 * @param \WP_REST_Request $request Request: `repos` (target key => repository; the whole map is replaced),
	 *                                  `extra` (rows of `label` and `repo`; the whole list is replaced), `token`, `removeToken`.
	 * @return array|\WP_Error The new state.
	 */
	public function update_settings( $request ) {
		if ( $request->get_param( 'removeToken' ) ) {
			Settings::update_token( '' );
		} elseif ( is_string( $request->get_param( 'token' ) ) && '' !== trim( $request->get_param( 'token' ) ) ) {
			Settings::update_token( sanitize_text_field( $request->get_param( 'token' ) ) );
		}

		$settings = Settings::get();
		$targets  = Repos::all();

		if ( is_array( $request->get_param( 'widget' ) ) ) {
			$settings['widget'] = Settings::widget( $request->get_param( 'widget' ) );
		}

		$known = array_unique( array_column( $targets, 'repo' ) );
		$new   = [];
		$repos = $request->get_param( 'repos' );
		$extra = $request->get_param( 'extra' );

		if ( is_array( $repos ) ) {
			$settings['repos'] = [];

			foreach ( $repos as $key => $repo ) {
				if ( ! isset( $targets[ $key ] ) || 'repo' === $targets[ $key ]['type'] ) {
					continue;
				}

				$clean = $this->repo( $repo, $targets[ $key ]['name'] );

				if ( is_wp_error( $clean ) ) {
					return $clean;
				}

				// Saved only when it differs from what the target's own header says.
				if ( '' !== $clean && $clean !== $targets[ $key ]['detected'] ) {
					$settings['repos'][ $key ] = $clean;
				}

				$new[] = $clean;
			}
		}

		if ( is_array( $extra ) ) {
			$settings['extra'] = [];

			foreach ( $extra as $row ) {
				$label = is_array( $row ) && isset( $row['label'] ) && is_scalar( $row['label'] ) ? (string) $row['label'] : '';
				$clean = $this->repo( is_array( $row ) && isset( $row['repo'] ) ? $row['repo'] : '', $label );

				if ( is_wp_error( $clean ) ) {
					return $clean;
				}

				if ( '' !== $clean ) {
					$settings['extra'][] = [
						'label' => $label,
						'repo'  => $clean,
					];
					$new[]               = $clean;
				}
			}
		}

		foreach ( '' !== Settings::token() ? array_diff( array_unique( array_filter( $new ) ), $known ) : [] as $repo ) {
			$checked = GitHub::check( $repo );

			if ( is_wp_error( $checked ) ) {
				return $this->refused( $checked );
			}
		}

		Settings::update( $settings );

		return $this->state();
	}

	/**
	 * A typed repository as "owner/name".
	 *
	 * @param mixed  $typed What was typed.
	 * @param string $name  What it is for, for the error message.
	 * @return string|\WP_Error Empty when nothing was typed.
	 */
	private function repo( $typed, $name ) {
		$typed = trim( is_scalar( $typed ) ? (string) $typed : '' );
		$clean = Repos::normalize( $typed );

		if ( '' !== $typed && '' === $clean ) {
			/* translators: %s: plugin, theme or label. */
			return new \WP_Error( 'nhrrob_issue_reporter_bad_repo', sprintf( __( 'The repository of %s has to be owner/name or a github.com address.', 'nhrrob-issue-reporter' ), '' !== $name ? $name : $typed ), [ 'status' => 400 ] );
		}

		return $clean;
	}

	/**
	 * Open issues that resemble a title.
	 *
	 * @param \WP_REST_Request $request Request: `plugin` (file) and `title`.
	 * @return array|\WP_Error `issues`: rows of `number`, `title` and `url`.
	 */
	public function similar( $request ) {
		$plugin = Repos::resolve( $request->get_param( 'plugin' ), $request->get_param( 'repo' ) );

		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		if ( '' !== $plugin['route'] ) {
			return [ 'issues' => [] ]; // Its tracker is not searched.
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
	 * @param \WP_REST_Request $request Request: `plugin` (key), `title`, `what`, `steps`, `environment`,
	 *                                  and optionally `screenshots` (attachment ids) with `allowSiteAddress`.
	 * @return array|\WP_Error `report` (the new row) and `reports`.
	 */
	public function send( $request ) {
		$plugin = Repos::resolve( $request->get_param( 'plugin' ), $request->get_param( 'repo' ) );

		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$title = self::line( $request->get_param( 'title' ) );
		$what  = self::text( $request->get_param( 'what' ) );

		if ( '' === $title || '' === $what ) {
			return new \WP_Error( 'nhrrob_issue_reporter_incomplete', __( 'A title and a description of what happened are needed.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		$images = $this->screenshots( $request );

		if ( is_wp_error( $images ) ) {
			return $images;
		}

		$labels = self::labels( $request->get_param( 'labels' ) );
		$body   = GitHub::body( $what, self::text( $request->get_param( 'steps' ) ), self::text( $request->get_param( 'environment' ) ), $images );

		/**
		 * Send a report somewhere else than GitHub. Return the new issue's
		 * `number` and `url` (and `tracker` and `ref` to look its state up
		 * later), or a WP_Error; null lets GitHub have it, unless the target
		 * has a route.
		 *
		 * @param array|\WP_Error|null $issue  Null.
		 * @param array                $target The target, as Repos::all() returns it.
		 * @param string               $title  Title.
		 * @param string               $body   Body, in Markdown.
		 * @param string[]             $labels Labels.
		 */
		$issue = apply_filters( 'nhrrob_issue_reporter_pre_send', null, $plugin, $title, $body, $labels );

		if ( null === $issue && '' === $plugin['route'] ) {
			$issue = GitHub::create( $plugin['repo'], $title, $body, $labels );
		}

		if ( is_wp_error( $issue ) ) {
			return $this->refused( $issue );
		}

		if ( ! is_array( $issue ) || ! isset( $issue['number'], $issue['url'] ) ) {
			return new \WP_Error( 'nhrrob_issue_reporter_not_sent', __( 'The report was not sent. Please try again.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		$report = Reports::add( $plugin['name'], '' !== $plugin['route'] ? $plugin['route'] : $plugin['repo'], $issue, $title );

		/**
		 * After a report was opened. The Inbox marks its feedback as sent here.
		 *
		 * @param array            $report  The new row of the Sent list.
		 * @param \WP_REST_Request $request The request.
		 */
		do_action( 'nhrrob_issue_reporter_sent', $report, $request );

		return [
			'report'  => $report,
			'reports' => Reports::all(),
		];
	}

	/**
	 * Labels for a new issue: at most ten, each one line of at most 50 characters.
	 * A label the repository does not have is created by GitHub, or left out
	 * when the token cannot create labels there.
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	public static function labels( $value ) {
		$labels = [];

		foreach ( is_array( $value ) ? $value : [] as $label ) {
			$label = mb_substr( sanitize_text_field( is_scalar( $label ) ? (string) $label : '' ), 0, 50 );

			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}

		return array_slice( array_values( array_unique( $labels ) ), 0, 10 );
	}

	/**
	 * Upload one screenshot to the media library, before a report is sent.
	 *
	 * @param \WP_REST_Request $request Request with the image as the file `file`.
	 * @return array|\WP_Error `id` and `url`.
	 */
	public function screenshot( $request ) {
		$files = $request->get_file_params();

		return Screenshots::upload( isset( $files['file'] ) ? $files['file'] : null );
	}

	/**
	 * The screenshots a report or comment links to, once the person agreed
	 * that the links show the site's address.
	 *
	 * @param \WP_REST_Request $request Request: `screenshots` (attachment ids) and `allowSiteAddress`.
	 * @return string|\WP_Error Markdown, empty without screenshots.
	 */
	private function screenshots( $request ) {
		$ids = $request->get_param( 'screenshots' );

		if ( empty( $ids ) ) {
			return '';
		}

		if ( true !== $request->get_param( 'allowSiteAddress' ) ) {
			return new \WP_Error( 'nhrrob_issue_reporter_address', __( 'The links to screenshots show this site\'s address. Agree to that before sending.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		return Screenshots::markdown( $ids );
	}

	/**
	 * Comment on an open issue in the repository of a plugin, theme or other
	 * repository. The address is built from that repository, so the comment
	 * cannot land anywhere else.
	 *
	 * @param \WP_REST_Request $request Request: `plugin` (key), `number`, and `body`, or `what`,
	 *                                  `steps` and `environment` as in a report; `screenshots` as in a report.
	 * @return array|\WP_Error `url` of the comment and `reports`.
	 */
	public function comment( $request ) {
		$plugin = Repos::resolve( $request->get_param( 'plugin' ), $request->get_param( 'repo' ) );

		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		if ( '' !== $plugin['route'] ) {
			/* translators: %s: where the plugin's reports go, such as "Jira · ACME". */
			return new \WP_Error( 'nhrrob_issue_reporter_routed', sprintf( __( 'Reports about this go to %s; add a comment there.', 'nhrrob-issue-reporter' ), $plugin['route'] ), [ 'status' => 400 ] );
		}

		$images = $this->screenshots( $request );

		if ( is_wp_error( $images ) ) {
			return $images;
		}

		$number = filter_var( $request->get_param( 'number' ), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
		$body   = self::text( $request->get_param( 'body' ) );

		if ( '' === $body ) {
			$body = GitHub::body( self::text( $request->get_param( 'what' ) ), self::text( $request->get_param( 'steps' ) ), self::text( $request->get_param( 'environment' ) ), $images );
		} elseif ( '' !== $images ) {
			$body .= "\n\n" . $images;
		}

		if ( false === $number || '' === $body ) {
			return new \WP_Error( 'nhrrob_issue_reporter_incomplete', __( 'An issue number and the text of the comment are needed.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		$comment = GitHub::comment( $plugin['repo'], $number, $body );

		if ( is_wp_error( $comment ) ) {
			return $this->refused( $comment );
		}

		Reports::commented( $plugin['repo'], $number );

		return [
			'url'     => $comment['url'],
			'reports' => Reports::all(),
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
	 * A GitHub error as an answer the app shows to the user.
	 *
	 * @param \WP_Error $error Error from GitHub::request().
	 * @return \WP_Error
	 */
	private function refused( $error ) {
		return new \WP_Error( $error->get_error_code(), $error->get_error_message(), [ 'status' => 400 ] );
	}
}
