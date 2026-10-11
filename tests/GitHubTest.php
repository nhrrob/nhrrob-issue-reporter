<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\GitHub;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * The token goes to api.github.com only, a failure never looks like a sent
 * report, and what the user typed cannot change the search.
 */
class GitHubTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		WP_Mock::userFunction( 'is_wp_error' )->andReturnUsing(
			function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function token( $token ) {
		WP_Mock::userFunction( 'get_option' )->with( 'nhrrob_issue_reporter_token', '' )->andReturn( $token );
	}

	private function answer( $code, $body ) {
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( $code );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( $body );
	}

	public function testWithoutATokenNoRequestIsMade() {
		$this->token( '' );
		WP_Mock::userFunction( 'wp_remote_request' )->never();

		$this->assertInstanceOf( \WP_Error::class, GitHub::create( 'a/b', 'Title', 'Body' ) );
	}

	public function testAnIssueIsCreatedOnApiGitHubWithTheToken() {
		$this->token( 'secret' );
		$this->answer( 201, '{"number":7,"html_url":"https://github.com/a/b/issues/7"}' );
		WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturnUsing(
			function ( $url, $args ) {
				$this->assertSame( 'https://api.github.com/repos/a/b/issues', $url );
				$this->assertSame( 'POST', $args['method'] );
				$this->assertSame( 'Bearer secret', $args['headers']['Authorization'] );
				$this->assertSame( [ 'title' => 'Title', 'body' => 'Body' ], json_decode( $args['body'], true ) );
				return [];
			}
		);

		$this->assertSame( [ 'number' => 7, 'url' => 'https://github.com/a/b/issues/7' ], GitHub::create( 'a/b', 'Title', 'Body' ) );
	}

	/**
	 * @dataProvider failures
	 */
	public function testAFailureIsAnErrorNeverAnIssue( $code, $body ) {
		$this->token( 'secret' );
		$this->answer( $code, $body );
		WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( [] );

		$error = GitHub::create( 'a/b', 'Title', 'Body' );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertNotSame( '', $error->get_error_message() );
	}

	public function failures() {
		return [
			'bad token'         => [ 401, '{"message":"Bad credentials"}' ],
			'no access'         => [ 404, '{"message":"Not Found"}' ],
			'issues off'        => [ 410, '{"message":"Issues are disabled"}' ],
			'refused'           => [ 422, '{"message":"Validation Failed"}' ],
			'server error'      => [ 500, 'oops' ],
			'success, not json' => [ 201, '<html>' ],
			'success, no issue' => [ 201, '{"message":"ok"}' ],
		];
	}

	public function testANetworkFailureIsAnError() {
		$this->token( 'secret' );
		WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( new \WP_Error( 'http_request_failed', 'timeout' ) );

		$this->assertInstanceOf( \WP_Error::class, GitHub::similar( 'a/b', 'Title' ) );
	}

	public function testTheSearchStaysInsideTheRepositoryWhateverTheTitleSays() {
		$this->token( 'secret' );
		$this->answer( 200, '{"items":[{"number":3,"title":"Old one","html_url":"https://github.com/a/b/issues/3","body":"x"},{"title":"no number"}]}' );
		WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturnUsing(
			function ( $url ) {
				parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
				$this->assertSame( 'https://api.github.com/search/issues', strtok( $url, '?' ) );
				// Colons, quotes and dashes are gone, so "repo:other/secret" is three plain words.
				$this->assertSame( 'repo:a/b is:issue is:open in:title Fatal error repo other secret is closed', $query['q'] );
				return [];
			}
		);

		$this->assertSame(
			[ [ 'number' => 3, 'title' => 'Old one', 'url' => 'https://github.com/a/b/issues/3' ] ],
			GitHub::similar( 'a/b', 'Fatal error: repo:other/secret -is:closed "' )
		);
	}

	public function testATitleWithoutWordsSearchesNothing() {
		WP_Mock::userFunction( 'wp_remote_request' )->never();

		$this->assertSame( [], GitHub::similar( 'a/b', '?!' ) );
	}

	public function testTheBodyLeavesOutEmptySections() {
		$this->assertSame( "### What happened\n\nIt broke.\n\n### Environment\n\n- PHP: 8.2", GitHub::body( " It broke.\n", '  ', '- PHP: 8.2' ) );
	}

	public function testAStateOtherThanOpenOrClosedIsUnknown() {
		$this->token( 'secret' );
		$this->answer( 200, '{"state":"weird"}' );
		WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( [] );

		$this->assertSame( '', GitHub::state( 'a/b', 3 ) );
	}
}
