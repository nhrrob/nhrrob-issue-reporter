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
			],
			array_keys( $this->definitions() )
		);
	}

	public function testEachIsBehindTheCapabilityOfTheRestRoutes() {
		foreach ( $this->definitions() as $name => $args ) {
			$this->assertSame( [ Settings::class, 'can_manage' ], $args['permission_callback'], $name );
		}
	}

	public function testOnlyReportIssueWrites() {
		foreach ( $this->definitions() as $name => $args ) {
			$this->assertSame( 'nhrrob-issue-reporter/report-issue' !== $name, $args['meta']['annotations']['readonly'], $name );
			$this->assertFalse( $args['meta']['annotations']['destructive'], $name );
		}

		$this->assertFalse( $this->definitions()['nhrrob-issue-reporter/report-issue']['meta']['annotations']['idempotent'] );
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

		$this->assertSame( [ 'plugin', 'title', 'what', 'steps', 'environment' ], array_keys( $input['properties'] ) );
		$this->assertSame( [ 'plugin', 'title', 'what' ], $input['required'] );
		$this->assertFalse( $input['additionalProperties'] );
	}

	public function testASearchNeedsAPluginAndATitle() {
		$input = $this->definitions()['nhrrob-issue-reporter/find-similar-issues']['input_schema'];

		$this->assertSame( [ 'plugin', 'title' ], $input['required'] );
		$this->assertFalse( $input['additionalProperties'] );
	}
}
