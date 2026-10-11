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
					'bad/plugin.php'                  => 'https://gitlab.com/x/y',
					'<script>'                        => 'x/y',
					7                                 => 'x/y',
				],
				'other' => 'dropped',
			]
		);

		$this->assertSame(
			[
				'repos' => [
					'nhrrob-secure/nhrrob-secure.php' => 'nhrrob/nhrrob-secure',
					'hello.php'                       => 'nhrrob/hello',
				],
			],
			$clean
		);
	}
}
