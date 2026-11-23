<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Repos;
use NHRRob\IssueReporter\Settings;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * A report only ever goes to a repository on github.com, named by the
 * settings or by the plugin's own header.
 */
class ReposTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing(
			function ( $text ) {
				return trim( strip_tags( $text ) );
			}
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * @dataProvider values
	 */
	public function testAValueIsReducedToOwnerAndName( $raw, $expected ) {
		$this->assertSame( $expected, Repos::normalize( $raw ) );
	}

	public function values() {
		return [
			'plain'              => [ 'nhrrob/nhrrob-secure', 'nhrrob/nhrrob-secure' ],
			'spaces'             => [ '  nhrrob/nhrrob-secure ', 'nhrrob/nhrrob-secure' ],
			'address'            => [ 'https://github.com/nhrrob/nhrrob-secure', 'nhrrob/nhrrob-secure' ],
			'address with www'   => [ 'http://www.github.com/nhrrob/nhrrob-secure/', 'nhrrob/nhrrob-secure' ],
			'no scheme'          => [ 'github.com/nhrrob/nhrrob-secure', 'nhrrob/nhrrob-secure' ],
			'git suffix'         => [ 'https://github.com/nhrrob/nhrrob-secure.git', 'nhrrob/nhrrob-secure' ],
			'deeper page'        => [ 'https://github.com/nhrrob/nhrrob-secure/issues/12?x=1#top', 'nhrrob/nhrrob-secure' ],
			'another host'       => [ 'https://gitlab.com/nhrrob/nhrrob-secure', '' ],
			'look-alike host'    => [ 'https://github.com.example.com/nhrrob/nhrrob-secure', '' ],
			'wordpress.org'      => [ 'https://wordpress.org/plugins/nhrrob-secure/', '' ],
			'owner only'         => [ 'nhrrob', '' ],
			'path traversal'     => [ 'nhrrob/../../user', '' ],
			'search syntax'      => [ 'nhrrob/repo is:public', '' ],
			'empty'              => [ '', '' ],
			'not a string'       => [ [ 'nhrrob/nhrrob-secure' ], '' ],
		];
	}

	public function testTheGitHubHeaderWinsAndAPluginAddressCountsOnlyOnGitHub() {
		$this->assertSame( 'a/b', Repos::detect( [ 'GitHub Plugin URI' => 'a/b', 'PluginURI' => 'https://github.com/c/d' ] ) );
		$this->assertSame( 'c/d', Repos::detect( [ 'PluginURI' => 'https://github.com/c/d' ] ) );
		$this->assertSame( 'e/f', Repos::detect( [ 'PluginURI' => 'https://example.com', 'UpdateURI' => 'https://github.com/e/f' ] ) );
		$this->assertSame( '', Repos::detect( [ 'PluginURI' => 'https://wordpress.org/plugins/nhrrob-secure/' ] ) );
		// Without a host, "owner/name" is believed only in the header meant for it.
		$this->assertSame( '', Repos::detect( [ 'PluginURI' => 'nhrrob/nhrrob-secure' ] ) );
	}

	public function testSettingsKeepOnlyRealPluginFilesAndGitHubRepositories() {
		$clean = Settings::sanitize(
			[
				'repos' => [
					'nhrrob-secure/nhrrob-secure.php' => 'https://github.com/nhrrob/nhrrob-secure',
					'hello.php'                       => 'nhrrob/hello',
					'mu:site.php'                     => 'nhrrob/site',
					'theme:nazmulrobin'               => 'https://github.com/nhrrob/nazmulrobin_blog',
					'theme:../up'                     => 'x/y',
					'repo:x/y'                        => 'x/y',
					'bad/plugin.php'                  => 'https://gitlab.com/x/y',
					'<script>'                        => 'x/y',
					7                                 => 'x/y',
				],
				'extra' => [
					[ 'label' => ' The app <b>', 'repo' => 'https://github.com/nhrrob/app' ],
					[ 'repo' => 'nhrrob/other' ],
					[ 'label' => 'Twice', 'repo' => 'nhrrob/app' ],
					[ 'label' => 'Elsewhere', 'repo' => 'https://gitlab.com/x/y' ],
					'not a row',
				],
				'other' => 'dropped',
			]
		);

		$this->assertSame(
			[
				'repos'  => [
					'nhrrob-secure/nhrrob-secure.php' => 'nhrrob/nhrrob-secure',
					'hello.php'                       => 'nhrrob/hello',
					'mu:site.php'                     => 'nhrrob/site',
					'theme:nazmulrobin'               => 'nhrrob/nazmulrobin_blog',
				],
				'extra'  => [
					[ 'label' => 'The app', 'repo' => 'nhrrob/app' ],
					[ 'label' => 'nhrrob/other', 'repo' => 'nhrrob/other' ],
				],
				'widget' => Settings::widget( [] ),
			],
			$clean
		);
	}

	public function testPluginsMustUsePluginsThemesAndOtherRepositoriesAreTargets() {
		WP_Mock::userFunction( 'get_option' )->with( Settings::OPTION, [] )->andReturn(
			[
				'repos' => [ 'theme:child' => 'me/child' ],
				'extra' => [ [ 'label' => 'App', 'repo' => 'me/app' ] ],
			]
		);
		WP_Mock::userFunction( 'get_plugins' )->andReturn( [ 'p/p.php' => [ 'Name' => 'P', 'Version' => '1', 'GitHub Plugin URI' => 'me/p' ] ] );
		WP_Mock::userFunction( 'get_mu_plugins' )->andReturn( [ 'site.php' => [ 'Name' => 'Site', 'Version' => '2', 'PluginURI' => 'https://github.com/me/site' ] ] );
		WP_Mock::userFunction( 'wp_get_themes' )->andReturn(
			[
				'child' => $this->theme( [ 'Name' => 'Child', 'Version' => '3' ] ),
				'other' => $this->theme( [ 'Name' => 'Other', 'Version' => '4', 'GitHub Theme URI' => 'me/other' ] ),
			]
		);

		$this->assertSame(
			[
				'p/p.php'     => [ 'type' => 'plugin', 'name' => 'P', 'version' => '1', 'repo' => 'me/p', 'detected' => 'me/p' ],
				'mu:site.php' => [ 'type' => 'mu-plugin', 'name' => 'Site', 'version' => '2', 'repo' => 'me/site', 'detected' => 'me/site' ],
				'theme:child' => [ 'type' => 'theme', 'name' => 'Child', 'version' => '3', 'repo' => 'me/child', 'detected' => '' ],
				'theme:other' => [ 'type' => 'theme', 'name' => 'Other', 'version' => '4', 'repo' => 'me/other', 'detected' => 'me/other' ],
				'repo:me/app' => [ 'type' => 'repo', 'name' => 'App', 'version' => '', 'repo' => 'me/app', 'detected' => 'me/app' ],
			],
			Repos::all()
		);
	}

	private function theme( array $headers ) {
		return new class( $headers ) {
			private $headers;

			public function __construct( $headers ) {
				$this->headers = $headers;
			}

			public function get( $header ) {
				return isset( $this->headers[ $header ] ) ? $this->headers[ $header ] : '';
			}
		};
	}
}
