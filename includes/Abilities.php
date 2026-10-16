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
			'description' => __( 'The plugin file, such as "nhrrob-secure/nhrrob-secure.php", from get-state.', 'nhrrob-issue-reporter' ),
		];
		$title  = [
			'type'        => 'string',
			'description' => __( 'A short title of the issue.', 'nhrrob-issue-reporter' ),
		];

		return [
			'nhrrob-issue-reporter/get-state'           => [
				'label'               => __( 'Get plugins and sent reports', 'nhrrob-issue-reporter' ),
				'description'         => __( 'Returns the installed plugins with the GitHub repository each reports to (empty when none is known), whether a GitHub token is set, the environment text a report should include, and the reports sent so far. Makes no request to GitHub. Start here.', 'nhrrob-issue-reporter' ),
				'category'            => self::CATEGORY,
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
						'plugin'      => $plugin,
						'title'       => $title,
						'what'        => [
							'type'        => 'string',
							'description' => __( 'What happened, and what was expected instead.', 'nhrrob-issue-reporter' ),
						],
						'steps'       => [
							'type'        => 'string',
							'description' => __( 'Steps to see it happen.', 'nhrrob-issue-reporter' ),
						],
						'environment' => [
							'type'        => 'string',
							'description' => __( 'The environment text from get-state.', 'nhrrob-issue-reporter' ),
						],
					],
					'required'             => [ 'plugin', 'title', 'what' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [ 'type' => 'object' ],
				'execute_callback'    => [ $this, 'report_issue' ],
				'permission_callback' => $gate,
				'meta'                => $meta( false, false ),
			],
		];
	}

	/**
	 * Plugins, token presence, environment and sent reports.
	 *
	 * @return array
	 */
	public function get_state() {
		return ( new Rest() )->state();
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
