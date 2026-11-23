<?php
/**
 * "Connect GitHub": GitHub's device flow, run from this server.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connect Class
 *
 * The device flow needs only the OAuth App's client ID, which is public, so
 * there is no client secret and no server of ours. The person approves a short
 * code on github.com; this server asks GitHub for the token and keeps it like
 * a pasted one. The device code and the token never reach the browser.
 */
class Connect {

	/**
	 * The "NHR Issue Reporter" OAuth App, owned by nhrrob, with device flow on.
	 */
	const CLIENT_ID = 'Ov23liJiAf3ChO15S5pG';

	const LOGIN = 'https://github.com/login';

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
		foreach ( [ 'start', 'poll' ] as $step ) {
			register_rest_route(
				Rest::REST_NAMESPACE,
				'/connect/' . $step,
				[
					'methods'             => 'POST',
					'callback'            => [ $this, $step ],
					'permission_callback' => [ Settings::class, 'can_manage' ],
				]
			);
		}
	}

	/**
	 * The client ID. A site may use its own OAuth App.
	 *
	 * @return string
	 */
	public static function client_id() {
		/**
		 * The client ID of the GitHub OAuth App used by "Connect GitHub".
		 *
		 * @param string $client_id Client ID.
		 */
		return (string) apply_filters( 'nhrrob_issue_reporter_client_id', self::CLIENT_ID );
	}

	/**
	 * Ask GitHub for a code the person enters on github.com/login/device.
	 *
	 * @param \WP_REST_Request $request Request: `private` (true for the `repo` scope).
	 * @return array|\WP_Error `code`, `url`, `expiresIn`, `interval`.
	 */
	public function start( $request ) {
		if ( Settings::token_is_constant() ) {
			return new \WP_Error( 'nhrrob_issue_reporter_config_token', __( 'The token is set in wp-config.php. Change it there.', 'nhrrob-issue-reporter' ), [ 'status' => 400 ] );
		}

		$data = self::post(
			'/device/code',
			[
				'client_id' => self::client_id(),
				'scope'     => true === $request->get_param( 'private' ) ? 'repo' : 'public_repo',
			]
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! isset( $data['device_code'], $data['user_code'], $data['verification_uri'] ) ) {
			return self::error( isset( $data['error'] ) ? (string) $data['error'] : '' );
		}

		$interval = max( 5, isset( $data['interval'] ) ? (int) $data['interval'] : 5 );
		$expires  = min( 1800, max( 60, isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 900 ) );

		set_transient(
			self::key(),
			[
				'code'     => (string) $data['device_code'],
				'interval' => $interval,
				'next'     => time() + $interval,
			],
			$expires
		);

		return [
			'code'      => (string) $data['user_code'],
			'url'       => esc_url_raw( (string) $data['verification_uri'] ),
			'expiresIn' => $expires,
			'interval'  => $interval,
		];
	}

	/**
	 * Ask GitHub once whether the code was approved. Called by the app every
	 * `interval` seconds; a call that comes too early does not reach GitHub.
	 *
	 * @return array|\WP_Error `status`: pending, done, expired or denied; `interval`; with done, `state`.
	 */
	public function poll() {
		$flow = get_transient( self::key() );

		if ( ! is_array( $flow ) || empty( $flow['code'] ) ) {
			return [ 'status' => 'expired' ];
		}

		if ( time() < (int) $flow['next'] ) {
			return [
				'status'   => 'pending',
				'interval' => (int) $flow['interval'],
			];
		}

		$data = self::post(
			'/oauth/access_token',
			[
				'client_id'   => self::client_id(),
				'device_code' => $flow['code'],
				'grant_type'  => 'urn:ietf:params:oauth:grant-type:device_code',
			]
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$status = self::status( $data );

		if ( 'done' === $status ) {
			Settings::update_token( (string) $data['access_token'] );
			delete_transient( self::key() );

			return [
				'status' => 'done',
				'state'  => ( new Rest() )->state(),
			];
		}

		if ( 'pending' !== $status && 'slow_down' !== $status ) {
			delete_transient( self::key() );

			return 'error' === $status ? self::error( isset( $data['error'] ) ? (string) $data['error'] : '' ) : [ 'status' => $status ];
		}

		// GitHub says how long to wait after slow_down; otherwise add five seconds.
		$flow['interval'] = 'slow_down' === $status ? max( (int) $flow['interval'] + 5, isset( $data['interval'] ) ? (int) $data['interval'] : 0 ) : (int) $flow['interval'];
		$flow['next']     = time() + $flow['interval'];
		set_transient( self::key(), $flow, 900 );

		return [
			'status'   => 'pending',
			'interval' => $flow['interval'],
		];
	}

	/**
	 * What GitHub's answer to a poll means.
	 *
	 * @param array $data Decoded answer.
	 * @return string done, pending, slow_down, expired, denied or error.
	 */
	public static function status( array $data ) {
		if ( ! empty( $data['access_token'] ) && is_string( $data['access_token'] ) ) {
			return 'done';
		}

		$map = [
			'authorization_pending' => 'pending',
			'slow_down'             => 'slow_down',
			'expired_token'         => 'expired',
			'access_denied'         => 'denied',
		];

		return isset( $data['error'], $map[ $data['error'] ] ) ? $map[ $data['error'] ] : 'error';
	}

	/**
	 * The transient that holds this person's flow.
	 *
	 * @return string
	 */
	private static function key() {
		return 'nhrrob_issue_reporter_device_' . get_current_user_id();
	}

	/**
	 * A form POST to github.com/login. No token is sent here: the token only
	 * ever goes to api.github.com, in GitHub::request().
	 *
	 * @param string $path Path under /login.
	 * @param array  $body Fields.
	 * @return array|\WP_Error The decoded answer.
	 */
	private static function post( $path, array $body ) {
		$response = wp_remote_post(
			self::LOGIN . $path,
			[
				'timeout' => 15,
				'headers' => [ 'Accept' => 'application/json' ],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return self::error( 'unreachable' );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : self::error( 'unreachable' );
	}

	/**
	 * An answer the app shows in a toast.
	 *
	 * @param string $code GitHub's error code.
	 * @return \WP_Error
	 */
	private static function error( $code ) {
		if ( 'device_flow_disabled' === $code || 'incorrect_client_credentials' === $code ) {
			$message = __( 'GitHub refused "Connect GitHub" for this app. Use a token instead.', 'nhrrob-issue-reporter' );
		} elseif ( 'unreachable' === $code ) {
			$message = __( 'GitHub could not be reached. Please try again.', 'nhrrob-issue-reporter' );
		} else {
			$message = __( 'GitHub did not connect. Please start again.', 'nhrrob-issue-reporter' );
		}

		return new \WP_Error( 'nhrrob_issue_reporter_connect', $message, [ 'status' => 400 ] );
	}
}
