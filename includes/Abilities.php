<?php
/**
 * Registers the plugin's abilities with the WordPress Abilities API.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What AI agents and MCP clients may ask the plugin (WordPress 6.9+).
 *
 * Each ability runs the REST route the app calls, so it checks, cleans and
 * sends exactly what the form would, behind the same capability. An agent
 * acts for a person who is logged in as themselves, for example with an
 * application password; nothing runs on its own. The token is never part of
 * an answer, as for the REST routes.
 *
 * The two hooks below only fire when something asks for the abilities
 * registry, so a visitor request pays nothing for this class.
 */
class Abilities {

	const CATEGORY = 'nhrrob-issue-reporter';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register' ] );
	}

	/**
	 * Register the category the abilities belong to.
	 *
	 * @return void
	 */
	public function register_category() {
		// The hook only exists on 6.9+; the check is for Plugin Check ("Requires at least" is 6.6).
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				self::CATEGORY,
				[
					'label'       => __( 'Report Issue', 'nhrrob-issue-reporter' ),
					'description' => __( 'Report an issue with an installed plugin to its GitHub repository.', 'nhrrob-issue-reporter' ),
				]
			);
		}
	}

	/**
	 * Register every ability.
	 *
	 * @return void
	 */
	public function register() {
		if ( function_exists( 'wp_register_ability' ) ) {
			foreach ( $this->definitions() as $name => $args ) {
				wp_register_ability( $name, $args );
			}
		}
	}

	/**
	 * Every ability: name => wp_register_ability() arguments.
	 *
	 * @return array
	 */
	public function definitions() {
		$gate   = [ Settings::class, 'can_manage' ];
		$meta   = function ( $read_only, $idempotent ) {
			return [
				'annotations'  => [
					'readonly'    => $read_only,
					'destructive' => false,
					'idempotent'  => $idempotent,
				],
				'show_in_rest' => true,
				// The MCP Adapter reads `mcp.public`.
				'mcp'          => [ 'public' => true ],
			];
		};
		$plugin = [
			'type'        => 'string',
			'description' => __( 'The `file` of a plugin, must-use plugin, theme or other repository from get-state, such as "nhrrob-secure/nhrrob-secure.php", "mu:site.php", "theme:twentytwentyfive" or "repo:owner/name". A "repo:" key works only for a repository saved under Settings, as get-state lists it.', 'nhrrob-issue-reporter' ),
		];
		$repo   = [
			'type'        => 'string',
			'description' => __( 'Optional: the GitHub repository ("owner/name") of a plugin, must-use plugin or theme whose `repo` is empty in get-state. It is checked with GitHub and saved in the settings, as the Repositories tab would.', 'nhrrob-issue-reporter' ),
		];
		$title  = [
			'type'        => 'string',
			'description' => __( 'A short title of the issue.', 'nhrrob-issue-reporter' ),
		];
		$images = [
			'type'        => 'array',
			'items'       => [
				'type'    => 'integer',
				'minimum' => 1,
			],
			'maxItems'    => Screenshots::MAX_FILES,
			'description' => __( 'Optional: ids of images in the media library, linked from the issue. The links show the site\'s address, so they need allowSiteAddress.', 'nhrrob-issue-reporter' ),
		];
		$allow  = [
			'type'        => 'boolean',
			'description' => __( 'True when the person agreed that the screenshot links show the site\'s address. Ask them first.', 'nhrrob-issue-reporter' ),
		];

		return [
			'nhrrob-issue-reporter/get-state'           => [
				'label'               => __( 'Get plugins and sent reports', 'nhrrob-issue-reporter' ),
				'description'         => __( 'Returns the installed plugins, must-use plugins and themes, and the other repositories set in Settings, each with its `type` and the GitHub repository it reports to (empty when none is known), whether a GitHub token is set, the environment text a report should include, and the reports sent so far. Makes no request to GitHub. Start here.', 'nhrrob-issue-reporter' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'repo'   => [
							'type'        => 'string',
							'description' => __( 'Only the sent reports to this repository, "owner/name". Leave it out for all.', 'nhrrob-issue-reporter' ),
						],
						'state'  => [
							'type'        => 'string',
							'enum'        => [ 'open', 'closed' ],
							'description' => __( 'Only the sent reports in this state, as last checked. Leave it out for all.', 'nhrrob-issue-reporter' ),
						],
						'search' => [
							'type'        => 'string',
							'description' => __( 'Only the sent reports whose title contains this text, or whose issue number it is ("12" or "#12").', 'nhrrob-issue-reporter' ),
						],
					],
					'additionalProperties' => false,
					// Run without input, as before the filter existed.
					'default'              => [],
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'get_state' ],
				'permission_callback' => $gate,
				'meta'                => $meta( true, true ),
			],
			'nhrrob-issue-reporter/find-similar-issues' => [
				'label'               => __( 'Find similar open issues', 'nhrrob-issue-reporter' ),
				'description'         => __( 'Searches the plugin\'s repository on GitHub for open issues whose title resembles the given one. Run it before report-issue, so a known issue is not reported twice.', 'nhrrob-issue-reporter' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'plugin' => $plugin,
						'title'  => $title,
						'repo'   => $repo,
					],
					'required'             => [ 'plugin', 'title' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'find_similar_issues' ],
				'permission_callback' => $gate,
				'meta'                => $meta( true, true ),
			],
			'nhrrob-issue-reporter/report-issue'        => [
				'label'               => __( 'Report an issue', 'nhrrob-issue-reporter' ),
				'description'         => __( 'Opens an issue in the plugin\'s GitHub repository, which may be public. Show the person the full text first. The environment should be the text from get-state: versions and names only, never the site\'s address, a path or anything about users.', 'nhrrob-issue-reporter' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'plugin'           => $plugin,
						'title'            => $title,
						'what'             => [
							'type'        => 'string',
							'description' => __( 'What happened, and what was expected instead.', 'nhrrob-issue-reporter' ),
						],
						'steps'            => [
							'type'        => 'string',
							'description' => __( 'Steps to see it happen.', 'nhrrob-issue-reporter' ),
						],
						'environment'      => [
							'type'        => 'string',
							'description' => __( 'The environment text from get-state.', 'nhrrob-issue-reporter' ),
						],
						'labels'           => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'maxItems'    => 10,
							'description' => __( 'Optional: labels for the issue, such as "bug". GitHub leaves them out when the token cannot set labels in that repository.', 'nhrrob-issue-reporter' ),
						],
						'screenshots'      => $images,
						'allowSiteAddress' => $allow,
						'repo'             => $repo,
					],
					'required'             => [ 'plugin', 'title', 'what' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'report_issue' ],
				'permission_callback' => $gate,
				'meta'                => $meta( false, false ),
			],
			'nhrrob-issue-reporter/comment-on-issue'    => [
				'label'               => __( 'Comment on an issue', 'nhrrob-issue-reporter' ),
				'description'         => __( 'Adds a comment to an issue in the plugin\'s GitHub repository, such as a similar issue found with find-similar-issues or a report sent before. The repository may be public: show the person the full text first.', 'nhrrob-issue-reporter' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'plugin'           => $plugin,
						'number'           => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'The issue number in that repository.', 'nhrrob-issue-reporter' ),
						],
						'body'             => [
							'type'        => 'string',
							'description' => __( 'The comment, in Markdown.', 'nhrrob-issue-reporter' ),
						],
						'screenshots'      => $images,
						'allowSiteAddress' => $allow,
						'repo'             => $repo,
					],
					'required'             => [ 'plugin', 'number', 'body' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'comment_on_issue' ],
				'permission_callback' => $gate,
				'meta'                => $meta( false, false ),
			],
		];
	}

	/**
	 * Plugins, token presence, environment and sent reports.
	 *
	 * @param array|null $input Optional `repo`, `state` and `search`, to narrow the sent reports.
	 * @return array
	 */
	public function get_state( $input = null ) {
		$state            = ( new Rest() )->state();
		$state['reports'] = self::filter_reports( $state['reports'], is_array( $input ) ? $input : [] );

		return $state;
	}

	/**
	 * Sent reports narrowed by repository, state and a search, as the Sent tab does.
	 *
	 * @param array $reports Rows from Reports::all().
	 * @param array $input   `repo`, `state` (open or closed), `search` (title text or issue number).
	 * @return array
	 */
	public static function filter_reports( array $reports, array $input ) {
		$repo   = isset( $input['repo'] ) ? Repos::normalize( $input['repo'] ) : '';
		$state  = isset( $input['state'] ) && in_array( $input['state'], [ 'open', 'closed' ], true ) ? $input['state'] : '';
		$search = isset( $input['search'] ) && is_scalar( $input['search'] ) ? trim( (string) $input['search'] ) : '';
		$number = preg_match( '/^#?(\d+)$/', $search, $match ) ? (int) $match[1] : 0;

		if ( '' !== $repo ) {
			$reports = wp_list_filter( $reports, [ 'repo' => $repo ] );
		}

		if ( '' !== $state ) {
			$reports = wp_list_filter( $reports, [ 'state' => $state ] );
		}

		if ( '' !== $search ) {
			$kept = [];

			foreach ( $reports as $report ) {
				if ( ( $number && (int) $report['number'] === $number ) || false !== mb_stripos( (string) $report['title'], $search ) ) {
					$kept[] = $report;
				}
			}

			$reports = $kept;
		}

		return array_values( $reports );
	}

	/**
	 * Open issues that resemble a title.
	 *
	 * @param array $input `plugin` and `title`.
	 * @return array|\WP_Error
	 */
	public function find_similar_issues( $input ) {
		return ( new Rest() )->similar( $this->request( $input ) );
	}

	/**
	 * Send a report.
	 *
	 * @param array $input `plugin`, `title`, `what`, `steps`, `environment`.
	 * @return array|\WP_Error
	 */
	public function report_issue( $input ) {
		return ( new Rest() )->send( $this->request( $input ) );
	}

	/**
	 * Comment on an issue.
	 *
	 * @param array $input `plugin`, `number`, `body`.
	 * @return array|\WP_Error
	 */
	public function comment_on_issue( $input ) {
		return ( new Rest() )->comment( $this->request( $input ) );
	}

	/**
	 * An ability's input as the request the REST route reads.
	 *
	 * @param array $input Input.
	 * @return \WP_REST_Request
	 */
	private function request( $input ) {
		$request = new \WP_REST_Request();

		foreach ( (array) $input as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}
}
