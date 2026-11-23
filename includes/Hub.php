<?php
/**
 * The client side of an agency hub: this site sends its feedback to the
 * agency's own WordPress site, which runs the paid add-on.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

use NHRRob\IssueReporter\Feedback\Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The agency gives a key: its hub's REST address and a one-time token,
 * as "<address>#<token>". Connecting trades the token for this site's own
 * secret. Each piece of feedback is then sent as JSON, signed with an HMAC
 * of the time and the body, from the Inbox or as it arrives. Nothing is sent
 * before the site's owner connects, and only feedback, never a token.
 */
class Hub {

	const OPTION  = 'nhrrob_issue_reporter_hub';
	const TIMEOUT = 15;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'routes' ] );
		add_filter( 'nhrrob_issue_reporter_state', [ $this, 'state' ] );
		add_action( 'nhrrob_issue_reporter_feedback_created', [ $this, 'arrived' ] );
	}

	/**
	 * Routes, for whoever may manage the plugin.
	 *
	 * - POST   /hub              { key }: connect
	 * - DELETE /hub              forget the hub
	 * - POST   /hub/auto         { on }: send feedback as it arrives
	 * - POST   /hub/send         { id }: send one piece of feedback
	 *
	 * @return void
	 */
	public function routes() {
		$gate = [ Settings::class, 'can_manage' ];

		register_rest_route(
			Rest::REST_NAMESPACE,
			'/hub',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'connect' ],
					'permission_callback' => $gate,
					'args'                => [
						'key' => [
							'type'     => 'string',
							'required' => true,
						],
					],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'forget' ],
					'permission_callback' => $gate,
				],
			]
		);

		register_rest_route(
			Rest::REST_NAMESPACE,
			'/hub/auto',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'auto' ],
				'permission_callback' => $gate,
				'args'                => [ 'on' => [ 'type' => 'boolean' ] ],
			]
		);

		register_rest_route(
			Rest::REST_NAMESPACE,
			'/hub/send',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'send_route' ],
				'permission_callback' => $gate,
				'args'                => [
					'id' => [
						'type'     => 'integer',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * What is saved.
	 *
	 * @return array `base`, `site`, `secret`, `name`, `auto`, `since`.
	 */
	public static function saved() {
		$saved = get_option( self::OPTION, [] );

		return wp_parse_args(
			is_array( $saved ) ? $saved : [],
			[
				'base'   => '',
				'site'   => 0,
				'secret' => '',
				'name'   => '',
				'auto'   => false,
				'since'  => 0,
			]
		);
	}

	/**
	 * Whether a hub is connected.
	 *
	 * @return bool
	 */
	public static function connected() {
		$saved = self::saved();
		return '' !== $saved['base'] && '' !== $saved['secret'] && $saved['site'] > 0;
	}

	/**
	 * The app's view: never the secret.
	 *
	 * @param array $state App state.
	 * @return array
	 */
	public function state( $state ) {
		$saved        = self::saved();
		$state['hub'] = [
			'connected' => self::connected(),
			'name'      => $saved['name'],
			'address'   => $saved['base'] ? (string) wp_parse_url( $saved['base'], PHP_URL_HOST ) : '',
			'auto'      => (bool) $saved['auto'],
			'since'     => (int) $saved['since'],
		];

		return $state;
	}

	/**
	 * Split a key into the hub's address and the token.
	 *
	 * @param string $key "<https address>#<token>".
	 * @return array|null [ base, token ].
	 */
	public static function parse( $key ) {
		$key = trim( (string) $key );

		if ( ! preg_match( '/^(https?:\/\/[^#\s]+)#([A-Za-z0-9]{24,64})$/', $key, $m ) ) {
			return null;
		}

		$base = esc_url_raw( $m[1], [ 'http', 'https' ] );

		return '' !== $base ? [ untrailingslashit( $base ), $m[2] ] : null;
	}

	/**
	 * Connect with a key.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error The app's state.
	 */
	public function connect( $request ) {
		$parts = self::parse( $request->get_param( 'key' ) );

		if ( ! $parts ) {
			return new \WP_Error( 'nhrrob_issue_reporter_hub_key', __( 'That does not look like a hub key. Copy it again from the agency’s Client sites screen.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		$answer = self::post(
			$parts[0] . '/connect',
			[
				'key'     => $parts[1],
				'url'     => home_url( '/' ),
				'name'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'version' => NHRROB_ISSUE_REPORTER_VERSION,
			]
		);

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		if ( empty( $answer['site'] ) || empty( $answer['secret'] ) || ! is_string( $answer['secret'] ) ) {
			return new \WP_Error( 'nhrrob_issue_reporter_hub_answer', __( 'The hub did not accept the key. It works once, for 24 hours: ask for a new one.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		update_option(
			self::OPTION,
			[
				'base'   => $parts[0],
				'site'   => (int) $answer['site'],
				'secret' => preg_replace( '/[^A-Za-z0-9]/', '', $answer['secret'] ),
				'name'   => isset( $answer['name'] ) ? mb_substr( sanitize_text_field( (string) $answer['name'] ), 0, 100 ) : '',
				'auto'   => false,
				'since'  => time(),
			],
			false
		);

		return ( new Rest() )->state();
	}

	/**
	 * Forget the hub.
	 *
	 * @return array The app's state.
	 */
	public function forget() {
		delete_option( self::OPTION );
		return ( new Rest() )->state();
	}

	/**
	 * Send feedback as it arrives, or not.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array The app's state.
	 */
	public function auto( $request ) {
		$saved         = self::saved();
		$saved['auto'] = (bool) $request->get_param( 'on' );
		update_option( self::OPTION, $saved, false );

		return ( new Rest() )->state();
	}

	/**
	 * Send one piece of feedback from the Inbox.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error `item`.
	 */
	public function send_route( $request ) {
		$sent = self::send( (int) $request->get_param( 'id' ) );

		return is_wp_error( $sent ) ? $sent : [ 'item' => Store::row( get_post( (int) $request->get_param( 'id' ) ) ) ];
	}

	/**
	 * New feedback goes straight to the hub when that is switched on.
	 *
	 * @param int $id Feedback.
	 * @return void
	 */
	public function arrived( $id ) {
		if ( self::connected() && self::saved()['auto'] ) {
			self::send( (int) $id );
		}
	}

	/**
	 * Send a piece of feedback, with its screenshot.
	 *
	 * @param int $id Feedback.
	 * @return true|\WP_Error
	 */
	public static function send( $id ) {
		$post = Store::find( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! self::connected() ) {
			return new \WP_Error( 'nhrrob_issue_reporter_hub_off', __( 'Connect the agency hub under Settings first.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		$row  = Store::row( $post );
		$shot = '';

		if ( $row['shot'] ) {
			$file = get_attached_file( (int) $row['shot']['id'] );

			if ( $file && is_readable( $file ) && filesize( $file ) <= Screenshots::MAX_BYTES ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a local upload, sent as JSON.
				$shot = base64_encode( (string) file_get_contents( $file ) );
			}
		}

		$answer = self::post(
			self::saved()['base'] . '/reports',
			[
				'text'     => $row['text'],
				'kind'     => $row['kind'],
				'severity' => $row['severity'],
				'page'     => $row['page'],
				'browser'  => $row['browser'],
				'screen'   => $row['screen'],
				'errors'   => $row['errors'],
				'who'      => $row['who'],
				'time'     => $row['time'],
				'version'  => NHRROB_ISSUE_REPORTER_VERSION,
				'shot'     => $shot,
			],
			true
		);

		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		$meta        = Store::meta( $post->ID );
		$meta['hub'] = time();
		update_post_meta( $post->ID, Store::META, wp_slash( $meta ) );
		Store::update( $post->ID, [ 'status' => 'sent' ] );

		return true;
	}

	/**
	 * POST JSON to the hub, signed once connected.
	 *
	 * @param string $url    Address.
	 * @param array  $data   Body.
	 * @param bool   $signed Sign with this site's secret.
	 * @return array|\WP_Error Decoded answer.
	 */
	private static function post( $url, array $data, $signed = false ) {
		$body    = (string) wp_json_encode( $data );
		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];

		if ( $signed ) {
			$saved                          = self::saved();
			$time                           = (string) time();
			$headers['X-NHR-Hub-Site']      = (string) $saved['site'];
			$headers['X-NHR-Hub-Time']      = $time;
			$headers['X-NHR-Hub-Signature'] = self::sign( $time, $body, $saved['secret'] );
		}

		$response = wp_remote_post(
			$url,
			[
				'timeout' => self::TIMEOUT,
				'headers' => $headers,
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'nhrrob_issue_reporter_hub_unreachable', __( 'The agency hub could not be reached. Try again later.', 'nhrrob-issue-reporter' ), [ 'status' => 502 ] );
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$answer = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$answer = is_array( $answer ) ? $answer : [];

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $answer['message'] ) && is_string( $answer['message'] ) ? sanitize_text_field( $answer['message'] ) : '';
			/* translators: 1: HTTP status code, 2: the hub's message. */
			return new \WP_Error( 'nhrrob_issue_reporter_hub_refused', trim( sprintf( __( 'The agency hub refused it (%1$d). %2$s', 'nhrrob-issue-reporter' ), $code, $message ) ), [ 'status' => 400 ] );
		}

		return $answer;
	}

	/**
	 * The signature of a body: HMAC-SHA256 of "<time>.<body>".
	 *
	 * @param string $time   Unix time, as sent.
	 * @param string $body   Raw body.
	 * @param string $secret Shared secret.
	 * @return string Hex.
	 */
	public static function sign( $time, $body, $secret ) {
		return hash_hmac( 'sha256', $time . '.' . $body, (string) $secret );
	}
}
