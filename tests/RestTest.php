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
}
