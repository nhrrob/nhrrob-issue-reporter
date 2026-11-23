<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Abilities;
use NHRRob\IssueReporter\Settings;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Three abilities, behind the same capability as the REST routes. Only
 * report-issue writes to GitHub, and it takes no field the form does not have.
 */
class AbilitiesTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function definitions() {
		return ( new Abilities() )->definitions();
	}

	public function testTheAbilities() {
		$this->assertSame(
			[
				'nhrrob-issue-reporter/get-state',
				'nhrrob-issue-reporter/find-similar-issues',
				'nhrrob-issue-reporter/report-issue',
				'nhrrob-issue-reporter/comment-on-issue',
			],
			array_keys( $this->definitions() )
		);
	}

	public function testEachIsBehindTheCapabilityOfTheRestRoutes() {
		foreach ( $this->definitions() as $name => $args ) {
			$this->assertSame( [ Settings::class, 'can_manage' ], $args['permission_callback'], $name );
		}
	}

	public function testOnlyReportingAndCommentingWrite() {
		$writes = [ 'nhrrob-issue-reporter/report-issue', 'nhrrob-issue-reporter/comment-on-issue' ];

		foreach ( $this->definitions() as $name => $args ) {
			$this->assertSame( ! in_array( $name, $writes, true ), $args['meta']['annotations']['readonly'], $name );
			$this->assertFalse( $args['meta']['annotations']['destructive'], $name );
		}

		foreach ( $writes as $name ) {
			$this->assertFalse( $this->definitions()[ $name ]['meta']['annotations']['idempotent'], $name );
		}
	}

	public function testACommentNeedsAPluginANumberAndText() {
		$input = $this->definitions()['nhrrob-issue-reporter/comment-on-issue']['input_schema'];

		$this->assertSame( [ 'plugin', 'number', 'body' ], $input['required'] );
		$this->assertSame( [ 'plugin', 'number', 'body', 'screenshots', 'allowSiteAddress', 'repo' ], array_keys( $input['properties'] ) );
		$this->assertFalse( $input['additionalProperties'] );
	}

	public function testEachIsOfferedOverRestAndMcp() {
		foreach ( $this->definitions() as $name => $args ) {
			$this->assertTrue( $args['meta']['show_in_rest'], $name );
			$this->assertTrue( $args['meta']['mcp']['public'], $name );
			$this->assertSame( Abilities::CATEGORY, $args['category'], $name );
		}
	}

	public function testAReportTakesTheFieldsOfTheFormOnly() {
		$input = $this->definitions()['nhrrob-issue-reporter/report-issue']['input_schema'];

		$this->assertSame( [ 'plugin', 'title', 'what', 'steps', 'environment', 'labels', 'screenshots', 'allowSiteAddress', 'repo' ], array_keys( $input['properties'] ) );
		$this->assertSame( [ 'plugin', 'title', 'what' ], $input['required'] );
		$this->assertFalse( $input['additionalProperties'] );
	}

	public function testTheStateCanBeNarrowedButNeedsNothing() {
		$input = $this->definitions()['nhrrob-issue-reporter/get-state']['input_schema'];

		$this->assertSame( [ 'repo', 'state', 'search' ], array_keys( $input['properties'] ) );
		$this->assertSame( [ 'open', 'closed' ], $input['properties']['state']['enum'] );
		$this->assertArrayNotHasKey( 'required', $input );
		$this->assertSame( [], $input['default'] );
	}

	public function testASearchNeedsAPluginAndATitle() {
		$input = $this->definitions()['nhrrob-issue-reporter/find-similar-issues']['input_schema'];

		$this->assertSame( [ 'plugin', 'title' ], $input['required'] );
		$this->assertFalse( $input['additionalProperties'] );
	}

	public function testSentReportsAreNarrowedByRepositoryStateAndSearch() {
		WP_Mock::userFunction( 'wp_list_filter' )->andReturnUsing(
			function ( $rows, $args ) {
				return array_filter(
					$rows,
					function ( $row ) use ( $args ) {
						return ! array_diff_assoc( $args, $row );
					}
				);
			}
		);

		$reports = [
			[ 'repo' => 'me/a', 'number' => 12, 'title' => 'Login button broken', 'state' => 'open' ],
			[ 'repo' => 'me/a', 'number' => 3, 'title' => 'Typo on the cart', 'state' => 'closed' ],
			[ 'repo' => 'me/b', 'number' => 12, 'title' => 'Slow search', 'state' => 'open' ],
		];
		$numbers = function ( $rows ) {
			return array_map(
				function ( $row ) {
					return $row['repo'] . '#' . $row['number'];
				},
				$rows
			);
		};

		$this->assertSame( [ 'me/a#12', 'me/b#12' ], $numbers( Abilities::filter_reports( $reports, [ 'state' => 'open' ] ) ) );
		$this->assertSame( [ 'me/a#3' ], $numbers( Abilities::filter_reports( $reports, [ 'search' => 'CART' ] ) ) );
		$this->assertSame( [ 'me/a#12', 'me/b#12' ], $numbers( Abilities::filter_reports( $reports, [ 'search' => '#12' ] ) ) );
		$this->assertSame( [ 'me/a#12' ], $numbers( Abilities::filter_reports( $reports, [ 'repo' => 'https://github.com/me/a', 'search' => '12' ] ) ) );
		$this->assertCount( 3, Abilities::filter_reports( $reports, [ 'state' => 'merged' ] ) );
	}
}
