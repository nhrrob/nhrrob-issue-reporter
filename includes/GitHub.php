<?php
/**
 * Requests to the GitHub API.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub Class
 *
 * The request() method is the only place the plugin makes an HTTP request,
 * and api.github.com is the only host the token is ever sent to.
 */
class GitHub {

	const API = 'https://api.github.com';

	/**
	 * Words too common to tell one issue from another.
	 */
	const STOP_WORDS = [ 'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'can', 'cannot', 'does', 'doesn', 'for', 'from', 'has', 'have', 'how', 'in', 'into', 'is', 'it', 'its', 'not', 'of', 'on', 'or', 'per', 'should', 'so', 'than', 'that', 'the', 'then', 'there', 'this', 'to', 'too', 'was', 'when', 'where', 'which', 'while', 'who', 'why', 'will', 'with', 'without', 'you', 'your' ];

	/**
	 * The words of a title worth searching for: letters and digits only,
	 * since anything else could be read by GitHub as search syntax, without
	 * short and common words, each once, at most six (GitHub allows five ORs).
	 *
	 * @param string $title Title.
	 * @return string[]
	 */
	public static function words( $title ) {
		$words = [];

		foreach ( explode( ' ', (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', (string) $title ) ) as $word ) {
			$key = mb_strtolower( $word );

			if ( mb_strlen( $word ) > 1 && ! in_array( $key, self::STOP_WORDS, true ) && ! isset( $words[ $key ] ) ) {
				$words[ $key ] = $word;
			}
		}

		return array_slice( array_values( $words ), 0, 6 );
	}

	/**
	 * Open issues in a repository that share words with the given title, the
	 * ones sharing the most first.
	 *
	 * @param string $repo  Repository, "owner/name".
	 * @param string $title Title of the new report.
	 * @return array|\WP_Error At most five rows of `number`, `title` and `url`.
	 */
	public static function similar( $repo, $title ) {
		$words = self::words( $title );

		if ( ! $words ) {
			return [];
		}

		$query = 'repo:' . $repo . ' is:issue is:open in:title ' . implode( ' OR ', $words );
		$data  = self::request( 'GET', '/search/issues?' . http_build_query( [ 'q' => $query, 'per_page' => 30 ] ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- two short pairs.

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$issues = [];
		$wanted = array_map( 'mb_strtolower', $words );

		foreach ( isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : [] as $index => $item ) {
			if ( isset( $item['number'], $item['title'], $item['html_url'] ) ) {
				$shared   = count( array_intersect( $wanted, array_map( 'mb_strtolower', self::words( $item['title'] ) ) ) );
				$issues[] = [
					'number' => (int) $item['number'],
					'title'  => (string) $item['title'],
					'url'    => (string) $item['html_url'],
					'rank'   => [ -$shared, $index ],
				];
			}
		}

		usort( $issues, [ self::class, 'by_rank' ] );

		return array_map( [ self::class, 'without_rank' ], array_slice( $issues, 0, 5 ) );
	}

	/**
	 * Sort order of similar issues: most shared words, then GitHub's order.
	 *
	 * @param array $a Issue.
	 * @param array $b Issue.
	 * @return int
	 */
	public static function by_rank( $a, $b ) {
		return $a['rank'] <=> $b['rank'];
	}

	/**
	 * An issue row without its sort key.
	 *
	 * @param array $issue Issue.
	 * @return array
	 */
	public static function without_rank( $issue ) {
		unset( $issue['rank'] );

		return $issue;
	}

	/**
	 * Open an issue.
	 *
	 * @param string $repo  Repository, "owner/name".
	 * @param string $title Title.
	 * @param string $body  Body, in Markdown.
	 * @return array|\WP_Error The new issue's `number` and `url`.
	 */
	public static function create( $repo, $title, $body ) {
		$data = self::request( 'POST', '/repos/' . $repo . '/issues', [ 'title' => $title, 'body' => $body ] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- two short pairs.

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! isset( $data['number'], $data['html_url'] ) ) {
			return self::error( 0 );
		}

		return [
			'number' => (int) $data['number'],
			'url'    => (string) $data['html_url'],
		];
	}

	/**
	 * Add a comment to an issue.
	 *
	 * @param string $repo   Repository, "owner/name".
	 * @param int    $number Issue number.
	 * @param string $body   Comment, in Markdown.
	 * @return array|\WP_Error The comment's `url`.
	 */
	public static function comment( $repo, $number, $body ) {
		$data = self::request( 'POST', '/repos/' . $repo . '/issues/' . (int) $number . '/comments', [ 'body' => $body ] );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return isset( $data['html_url'] ) ? [ 'url' => (string) $data['html_url'] ] : self::error( 0 );
	}

	/**
	 * Whether the token can see a repository, asked when one is saved.
	 *
	 * @param string $repo Repository, "owner/name".
	 * @return true|\WP_Error
	 */
	public static function check( $repo ) {
		$data = self::request( 'GET', '/repos/' . $repo );

		if ( is_wp_error( $data ) ) {
			/* translators: 1: repository such as owner/name, 2: why it failed. */
			return new \WP_Error( $data->get_error_code(), sprintf( __( '%1$s: %2$s', 'nhrrob-issue-reporter' ), $repo, $data->get_error_message() ) );
		}

		return true;
	}

	/**
	 * Whether an issue is open or closed.
	 *
	 * @param string $repo   Repository, "owner/name".
	 * @param int    $number Issue number.
	 * @return string "open", "closed", or empty when GitHub did not say.
	 */
	public static function state( $repo, $number ) {
		$data = self::request( 'GET', '/repos/' . $repo . '/issues/' . (int) $number );

		return ! is_wp_error( $data ) && isset( $data['state'] ) && in_array( $data['state'], [ 'open', 'closed' ], true ) ? $data['state'] : '';
	}

	/**
	 * The body of an issue, from the parts of the form.
	 *
	 * @param string $what        What happened.
	 * @param string $steps       Steps to reproduce.
	 * @param string $environment Details about the site.
	 * @return string
	 */
	public static function body( $what, $steps, $environment ) {
		$parts    = [];
		$sections = [
			'What happened'      => $what,
			'Steps to reproduce' => $steps,
			'Environment'        => $environment,
		];

		foreach ( $sections as $heading => $text ) {
			if ( '' !== trim( (string) $text ) ) {
				$parts[] = '### ' . $heading . "\n\n" . trim( (string) $text );
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Call the GitHub API.
	 *
	 * @param string     $method GET or POST.
	 * @param string     $path   Path under api.github.com, starting with a slash.
	 * @param array|null $body   Data to send as JSON.
	 * @return array|\WP_Error The decoded answer.
	 */
	public static function request( $method, $path, $body = null ) {
		$token = Settings::token();

		if ( '' === $token ) {
			return new \WP_Error( 'nhrrob_issue_reporter_no_token', __( 'No GitHub token is set. Add one in the Settings tab.', 'nhrrob-issue-reporter' ) );
		}

		$args = [
			'method'  => $method,
			'timeout' => 15,
			'headers' => [
				'Accept'               => 'application/vnd.github+json',
				'Authorization'        => 'Bearer ' . $token,
				'X-GitHub-Api-Version' => '2022-11-28',
			],
		];

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::API . $path, $args );

		if ( is_wp_error( $response ) ) {
			return self::error( 0 );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code > 299 || ! is_array( $data ) ) {
			return self::error( $code );
		}

		return $data;
	}

	/**
	 * What to tell the user for an answer that is not a success.
	 *
	 * @param int $code HTTP status, or 0 when GitHub was not reached.
	 * @return \WP_Error
	 */
	private static function error( $code ) {
		switch ( $code ) {
			case 401:
				$message = __( 'GitHub did not accept the token. It may be mistyped, expired or revoked.', 'nhrrob-issue-reporter' );
				break;

			case 403:
			case 404:
				$message = __( 'The token cannot open issues in this repository. Check that the repository exists and that the token has "Issues: Read and write" for it.', 'nhrrob-issue-reporter' );
				break;

			case 410:
				$message = __( 'Issues are switched off in this repository.', 'nhrrob-issue-reporter' );
				break;

			case 422:
				$message = __( 'GitHub refused the report. Shorten the title and try again.', 'nhrrob-issue-reporter' );
				break;

			default:
				$message = __( 'GitHub could not be reached. Nothing was sent; please try again.', 'nhrrob-issue-reporter' );
		}

		return new \WP_Error( 'nhrrob_issue_reporter_github_' . $code, $message );
	}
}
