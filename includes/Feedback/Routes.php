<?php
/**
 * REST routes for the widget and the Inbox.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter\Feedback;

use NHRRob\IssueReporter\Rest;
use NHRRob\IssueReporter\Screenshots;
use NHRRob\IssueReporter\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes Class
 *
 * The widget may only add feedback (Widget::can_send()). Reading, changing
 * and deleting it is for the people who may report (Settings::can_manage()).
 * Nothing here talks to GitHub: the Inbox sends through POST /reports.
 */
class Routes {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			Rest::REST_NAMESPACE,
			'/feedback',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'items' ],
					'permission_callback' => [ Settings::class, 'can_manage' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ Widget::class, 'can_send' ],
				],
			]
		);

		register_rest_route(
			Rest::REST_NAMESPACE,
			'/feedback/(?P<id>\d+)',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'update' ],
					'permission_callback' => [ Settings::class, 'can_manage' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete' ],
					'permission_callback' => [ Settings::class, 'can_manage' ],
				],
			]
		);
	}

	/**
	 * Every piece of feedback, for the Inbox.
	 *
	 * @return array `feedback`.
	 */
	public function items() {
		return [ 'feedback' => Store::all() ];
	}

	/**
	 * Save feedback from the widget, with its screenshot.
	 *
	 * @param \WP_REST_Request $request Request: `text`, `kind`, `page`, `browser`, `screen`, `errors` (JSON list), file `file`.
	 * @return array|\WP_Error `id`.
	 */
	public function create( $request ) {
		if ( '' === Rest::text( $request->get_param( 'text' ) ) ) {
			return new \WP_Error( 'nhrrob_issue_reporter_incomplete', __( 'Say what is wrong before sending.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		$shot  = null;
		$files = $request->get_file_params();

		if ( isset( $files['file'] ) && is_array( $files['file'] ) ) {
			// A random name, so the image's address cannot be guessed.
			$files['file']['name'] = 'feedback-' . strtolower( wp_generate_password( 20, false ) ) . '.png';
			$upload                = Screenshots::upload( $files['file'] );

			if ( is_wp_error( $upload ) ) {
				return $upload;
			}

			$shot = $upload['id'];
		}

		$errors = json_decode( (string) $request->get_param( 'errors' ), true );
		$id     = Store::create(
			[
				'text'    => $request->get_param( 'text' ),
				'kind'    => $request->get_param( 'kind' ),
				'page'    => $request->get_param( 'page' ),
				'browser' => $request->get_param( 'browser' ),
				'screen'  => $request->get_param( 'screen' ),
				'errors'  => is_array( $errors ) ? $errors : [],
			],
			$shot
		);

		if ( is_wp_error( $id ) ) {
			if ( $shot ) {
				wp_delete_attachment( $shot, true );
			}

			return new \WP_Error( 'nhrrob_issue_reporter_feedback', __( 'The feedback could not be saved. Please try again.', 'nhrrob-issue-reporter' ), [ 'status' => 500 ] );
		}

		return [ 'id' => $id ];
	}

	/**
	 * Change the status, kind or severity.
	 *
	 * @param \WP_REST_Request $request Request: `id`, and `status`, `kind` or `severity`.
	 * @return array|\WP_Error `item`.
	 */
	public function update( $request ) {
		$row = Store::update(
			$request->get_param( 'id' ),
			[
				'status'   => $request->get_param( 'status' ),
				'kind'     => $request->get_param( 'kind' ),
				'severity' => $request->get_param( 'severity' ),
			]
		);

		return is_wp_error( $row ) ? $row : [ 'item' => $row ];
	}

	/**
	 * Delete feedback and its screenshot.
	 *
	 * @param \WP_REST_Request $request Request: `id`.
	 * @return array|\WP_Error `deleted`.
	 */
	public function delete( $request ) {
		$done = Store::delete( $request->get_param( 'id' ) );

		return is_wp_error( $done ) ? $done : [ 'deleted' => true ];
	}
}
