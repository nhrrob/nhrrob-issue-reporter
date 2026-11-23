<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Reports;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * The list of sent reports stays small and asks GitHub as little as it can.
 */
class ReportsTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function row( $number, $state = 'open', $checked = 0 ) {
		return [
			'plugin'  => 'Plugin',
			'repo'    => 'a/b',
			'number'  => $number,
			'url'     => 'https://github.com/a/b/issues/' . $number,
			'title'   => 'Title ' . $number,
			'time'    => 1,
			'state'   => $state,
			'checked' => $checked,
		];
	}

	public function testANewReportGoesFirstAndTheListIsCapped() {
		WP_Mock::userFunction( 'get_option' )->with( Reports::OPTION, [] )->andReturn( array_map( [ $this, 'row' ], range( 1, Reports::MAX ) ) );
		WP_Mock::userFunction( 'update_option' )->once()->andReturnUsing(
			function ( $option, $rows, $autoload ) {
				$this->assertSame( Reports::OPTION, $option );
				$this->assertFalse( $autoload );
				$this->assertCount( Reports::MAX, $rows );
				$this->assertSame( 500, $rows[0]['number'] );
				$this->assertSame( 'open', $rows[0]['state'] );
				$this->assertSame( Reports::MAX - 1, $rows[ Reports::MAX - 1 ]['number'] );
				return true;
			}
		);

		Reports::add( 'Plugin', 'a/b', [ 'number' => 500, 'url' => 'https://github.com/a/b/issues/500' ], 'New' );
	}

	public function testOnlyOpenIssuesNotAskedAboutLatelyAreChecked() {
		WP_Mock::userFunction( 'get_option' )->with( Reports::OPTION, [] )->andReturn(
			[
				$this->row( 1, 'closed' ),
				$this->row( 2, 'open', time() ),
				$this->row( 3, 'open', time() - Reports::RECHECK - 1 ),
				'not a row',
			]
		);
		WP_Mock::userFunction( 'get_option' )->with( 'nhrrob_issue_reporter_token', '' )->andReturn( 'secret' );
		WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturnUsing(
			function ( $url ) {
				$this->assertSame( 'https://api.github.com/repos/a/b/issues/3', $url );
				return [];
			}
		);
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( '{"state":"closed"}' );
		WP_Mock::userFunction( 'update_option' )->once()->andReturnUsing(
			function ( $option, $rows ) {
				$this->assertSame( [ 'closed', 'open', 'closed' ], array_column( $rows, 'state' ) );
				return true;
			}
		);

		Reports::refresh();
	}

	public function testNothingIsWrittenWhenNothingWasChecked() {
		WP_Mock::userFunction( 'get_option' )->with( Reports::OPTION, [] )->andReturn( [ $this->row( 1, 'closed' ) ] );
		WP_Mock::userFunction( 'wp_remote_request' )->never();
		WP_Mock::userFunction( 'update_option' )->never();

		Reports::refresh();
		$this->assertTrue( true );
	}

	public function testAnIssueInAnotherTrackerAsksTheFilterNotGitHub() {
		$row            = $this->row( 7, 'open', 0 );
		$row['tracker'] = 'jira';
		$row['ref']     = 'ACME-7';
		WP_Mock::userFunction( 'get_option' )->with( Reports::OPTION, [] )->andReturn( [ $row ] );
		WP_Mock::onFilter( 'nhrrob_issue_reporter_issue_state' )->with( '', $row )->reply( 'closed' );
		WP_Mock::userFunction( 'update_option' )->once()->andReturnUsing(
			function ( $option, $rows ) {
				$this->assertSame( 'closed', $rows[0]['state'] );
				return true;
			}
		);

		Reports::refresh();
	}

	public function testAnIssueInAnotherTrackerKeepsItsTrackerAndReference() {
		WP_Mock::userFunction( 'get_option' )->with( Reports::OPTION, [] )->andReturn( [] );
		WP_Mock::userFunction( 'sanitize_key' )->andReturnUsing( 'strtolower' );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'update_option' )->once();

		$row = Reports::add( 'Plugin', 'Jira · ACME', [ 'number' => 7, 'url' => 'https://acme.atlassian.net/browse/ACME-7', 'tracker' => 'Jira', 'ref' => 'ACME-7' ], 'New' );

		$this->assertSame( 'jira', $row['tracker'] );
		$this->assertSame( 'ACME-7', $row['ref'] );
	}
}
