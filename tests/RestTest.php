<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Rest;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * What is typed reaches GitHub as typed: GitHub renders and sanitizes the
 * issue, so nothing that looks like a tag is removed here.
 */
class RestTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'wp_check_invalid_utf8' )->andReturnArg( 0 );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function testAngleBracketsAndCodeArriveAsTyped() {
		$text = "Search for `repo:<owner>/<name>` fails.\n\n```html\n<div class=\"x\">Array<string></div>\n```";

		$this->assertSame( $text, Rest::text( "  $text\n" ) );
	}

	public function testControlCharactersAndWindowsLineEndsAreRemoved() {
		$this->assertSame( "a\n\tb", Rest::text( "a\r\n\tb\x00\x07" ) );
	}

	public function testATitleIsOneLineOfAtMost200Characters() {
		$this->assertSame( 'Array<string> is lost', Rest::line( " Array<string>\n is   lost " ) );
		$this->assertSame( 200, mb_strlen( Rest::line( str_repeat( 'é', 300 ) ) ) );
	}

	private function site() {
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		WP_Mock::userFunction( 'is_wp_error' )->andReturnUsing(
			function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		WP_Mock::userFunction( 'get_option' )->with( 'nhrrob_issue_reporter_settings', [] )->andReturn( [] );
		WP_Mock::userFunction( 'get_option' )->with( 'nhrrob_issue_reporter_token', '' )->andReturn( 'secret' );
		WP_Mock::userFunction( 'get_option' )->with( 'nhrrob_issue_reporter_reports', [] )->andReturn( [] );
		WP_Mock::userFunction( 'get_plugins' )->andReturn(
			[
				'p/p.php' => [ 'Name' => 'P', 'Version' => '1', 'GitHub Plugin URI' => 'me/p' ],
				'q/q.php' => [ 'Name' => 'Q', 'Version' => '1' ],
			]
		);
		WP_Mock::userFunction( 'get_mu_plugins' )->andReturn( [] );
		WP_Mock::userFunction( 'wp_get_themes' )->andReturn( [] );
	}

	private function request( array $params ) {
		return new class( $params ) {
			private $params;

			public function __construct( $params ) {
				$this->params = $params;
			}

			public function get_param( $key ) {
				return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
			}
		};
	}

	public function testACommentGoesToTheRepositoryOfThePlugin() {
		$this->site();
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 201 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( '{"html_url":"https://github.com/me/p/issues/7#c"}' );
		WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturnUsing(
			function ( $url ) {
				$this->assertSame( 'https://api.github.com/repos/me/p/issues/7/comments', $url );
				return [];
			}
		);

		$answer = ( new Rest() )->comment( $this->request( [ 'plugin' => 'p/p.php', 'number' => '7', 'body' => 'Seen again.' ] ) );

		$this->assertSame( 'https://github.com/me/p/issues/7#c', $answer['url'] );
	}

	/**
	 * @dataProvider refusedComments
	 */
	public function testACommentWithoutARepositoryNumberOrTextIsNotSent( $params ) {
		$this->site();
		WP_Mock::userFunction( 'wp_remote_request' )->never();

		$this->assertInstanceOf( \WP_Error::class, ( new Rest() )->comment( $this->request( $params ) ) );
	}

	public function refusedComments() {
		return [
			'plugin without a repository' => [ [ 'plugin' => 'q/q.php', 'number' => 7, 'body' => 'x' ] ],
			'unknown plugin'              => [ [ 'plugin' => 'x/x.php', 'number' => 7, 'body' => 'x' ] ],
			'number with a path'          => [ [ 'plugin' => 'p/p.php', 'number' => '7/../../a/b', 'body' => 'x' ] ],
			'number zero'                 => [ [ 'plugin' => 'p/p.php', 'number' => 0, 'body' => 'x' ] ],
			'no text'                     => [ [ 'plugin' => 'p/p.php', 'number' => 7, 'body' => ' ' ] ],
		];
	}

	public function testScreenshotsAreNotSentUnlessTheSiteAddressMayBeShown() {
		$this->site();
		WP_Mock::userFunction( 'wp_remote_request' )->never();

		$params = [ 'plugin' => 'p/p.php', 'title' => 'Title', 'what' => 'It broke.', 'screenshots' => [ 4 ] ];

		$this->assertInstanceOf( \WP_Error::class, ( new Rest() )->send( $this->request( $params ) ) );
		$this->assertInstanceOf( \WP_Error::class, ( new Rest() )->send( $this->request( $params + [ 'allowSiteAddress' => 'yes' ] ) ) );
		$this->assertInstanceOf( \WP_Error::class, ( new Rest() )->comment( $this->request( [ 'number' => 7, 'body' => 'x' ] + $params ) ) );
	}

	public function testANonScalarIsEmpty() {
		$this->assertSame( '', Rest::text( [ 'x' ] ) );
	}

	public function testAPluginWithoutARepositorySaysWhatToDo() {
		$this->site();
		WP_Mock::userFunction( 'wp_remote_request' )->never();

		$error = ( new Rest() )->similar( $this->request( [ 'plugin' => 'q/q.php', 'title' => 'Broken' ] ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertStringContainsString( 'Q does not name its GitHub repository', $error->get_error_message() );
	}

	public function testAPluginWithoutARepositoryCanBeGivenOneThatIsCheckedAndSaved() {
		$this->site();
		$urls  = [];
		$saved = null;
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( '{"html_url":"https://github.com/me/q/issues/7#c","full_name":"me/q"}' );
		WP_Mock::userFunction( 'wp_remote_request' )->twice()->andReturnUsing(
			function ( $url ) use ( &$urls ) {
				$urls[] = $url;
				return [];
			}
		);
		WP_Mock::userFunction( 'update_option' )->andReturnUsing(
			function ( $option, $value ) use ( &$saved ) {
				$saved = [ $option, $value ];
				return true;
			}
		);

		( new Rest() )->comment( $this->request( [ 'plugin' => 'q/q.php', 'repo' => 'https://github.com/me/q', 'number' => 7, 'body' => 'x' ] ) );

		$this->assertSame( [ 'https://api.github.com/repos/me/q', 'https://api.github.com/repos/me/q/issues/7/comments' ], $urls );
		$this->assertSame( 'nhrrob_issue_reporter_settings', $saved[0] );
		$this->assertSame( [ 'q/q.php' => 'me/q' ], $saved[1]['repos'] );
	}

	public function testAnUnsavedOtherRepositoryIsNotATarget() {
		$this->site();
		WP_Mock::userFunction( 'wp_remote_request' )->never();

		$error = ( new Rest() )->similar( $this->request( [ 'plugin' => 'repo:me/else', 'title' => 'Broken' ] ) );

		$this->assertStringContainsString( 'me/else is not one of the other repositories', $error->get_error_message() );
	}

	public function testLabelsAreShortLinesEachOnceAtMostTen() {
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing( 'trim' );

		$this->assertSame( [ 'bug', 'severity: major' ], Rest::labels( [ 'bug', ' bug ', 'severity: major', '', [ 'x' ] ] ) );
		$this->assertSame( 50, mb_strlen( Rest::labels( [ str_repeat( 'é', 80 ) ] )[0] ) );
		$this->assertCount( 10, Rest::labels( range( 1, 20 ) ) );
		$this->assertSame( [], Rest::labels( 'bug' ) );
	}

	public function testANewIssueCarriesItsLabels() {
		$this->site();
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing( 'trim' );
		WP_Mock::userFunction( 'update_option' )->andReturn( true );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 201 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( '{"number":3,"html_url":"https://github.com/me/p/issues/3"}' );
		WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturnUsing(
			function ( $url, $args ) {
				$this->assertSame( [ 'bug', 'severity: minor' ], json_decode( $args['body'], true )['labels'] );
				return [];
			}
		);

		( new Rest() )->send( $this->request( [ 'plugin' => 'p/p.php', 'title' => 'T', 'what' => 'W', 'labels' => [ 'bug', 'severity: minor' ] ] ) );
	}

	private function routeQ() {
		$plain = [
			'p/p.php' => [ 'type' => 'plugin', 'name' => 'P', 'version' => '1', 'repo' => 'me/p', 'detected' => 'me/p' ],
			'q/q.php' => [ 'type' => 'plugin', 'name' => 'Q', 'version' => '1', 'repo' => '', 'detected' => '' ],
		];
		$routed                     = $plain;
		$routed['q/q.php']['route'] = 'Jira · ACME';
		WP_Mock::onFilter( 'nhrrob_issue_reporter_targets' )->with( $plain )->reply( $routed );
	}

	public function testAReportWithARouteNeverGoesToGitHub() {
		$this->site();
		$this->routeQ();
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing( 'trim' );
		WP_Mock::userFunction( 'wp_remote_request' )->never();

		$this->assertInstanceOf( \WP_Error::class, ( new Rest() )->send( $this->request( [ 'plugin' => 'q/q.php', 'title' => 'T', 'what' => 'W' ] ) ) );
		$this->assertInstanceOf( \WP_Error::class, ( new Rest() )->comment( $this->request( [ 'plugin' => 'q/q.php', 'number' => 3, 'body' => 'B' ] ) ) );
		$this->assertSame( [ 'issues' => [] ], ( new Rest() )->similar( $this->request( [ 'plugin' => 'q/q.php', 'title' => 'Broken' ] ) ) );
	}

	public function testAReportWithARouteIsListedUnderItsRoute() {
		$this->site();
		$this->routeQ();
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing( 'trim' );
		WP_Mock::userFunction( 'sanitize_key' )->andReturnUsing( 'strtolower' );
		WP_Mock::userFunction( 'update_option' )->andReturn( true );
		$target = [ 'type' => 'plugin', 'name' => 'Q', 'version' => '1', 'repo' => '', 'detected' => '', 'route' => 'Jira · ACME' ];
		WP_Mock::onFilter( 'nhrrob_issue_reporter_pre_send' )->with( null, $target, 'T', \NHRRob\IssueReporter\GitHub::body( 'W', '', '' ), [] )->reply(
			[
				'number'  => 7,
				'url'     => 'https://acme.atlassian.net/browse/ACME-7',
				'tracker' => 'jira',
				'ref'     => 'ACME-7',
			]
		);

		$answer = ( new Rest() )->send( $this->request( [ 'plugin' => 'q/q.php', 'title' => 'T', 'what' => 'W' ] ) );

		$this->assertSame( 'Jira · ACME', $answer['report']['repo'] );
		$this->assertSame( 'ACME-7', $answer['report']['ref'] );
	}
}
