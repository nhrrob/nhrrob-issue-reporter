<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Feedback\Store;
use NHRRob\IssueReporter\Feedback\Widget;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Who may use the widget is the chosen roles, unless a filter says more;
 * a switched-off widget stays off whatever the filter says. The Inbox row
 * can be changed by a filter.
 */
class WidgetTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function widget( $enabled ) {
		WP_Mock::userFunction( 'get_option' )->with( 'nhrrob_issue_reporter_settings', [] )->andReturn(
			[
				'widget' => [
					'enabled' => $enabled,
					'roles'   => [ 'editor' ],
				],
			]
		);
	}

	public function testAVisitorWithoutAnAccountNeedsTheFilter() {
		$this->widget( true );
		WP_Mock::userFunction( 'is_user_logged_in' )->andReturn( false );

		WP_Mock::onFilter( 'nhrrob_issue_reporter_can_send' )->with( false )->reply( false );
		$this->assertFalse( Widget::can_send() );
	}

	public function testTheFilterCanLetSomeoneIn() {
		$this->widget( true );
		WP_Mock::userFunction( 'is_user_logged_in' )->andReturn( false );

		WP_Mock::onFilter( 'nhrrob_issue_reporter_can_send' )->with( false )->reply( true );
		$this->assertTrue( Widget::can_send() );
	}

	public function testAChosenRoleIsAllowed() {
		$this->widget( true );
		WP_Mock::userFunction( 'is_user_logged_in' )->andReturn( true );
		WP_Mock::userFunction( 'wp_get_current_user' )->andReturn( (object) [ 'roles' => [ 'editor' ] ] );

		WP_Mock::onFilter( 'nhrrob_issue_reporter_can_send' )->with( true )->reply( true );
		$this->assertTrue( Widget::can_send() );
	}

	public function testAWidgetThatIsOffIsNeverAskedAbout() {
		$this->widget( false );
		WP_Mock::expectFilterNotAdded( 'nhrrob_issue_reporter_can_send', 'anything' );

		$this->assertFalse( Widget::can_send() );
	}

	public function testTheInboxRowCanBeChanged() {
		$post = (object) [
			'ID'           => 5,
			'post_author'  => 0,
			'post_content' => 'Broken',
		];
		WP_Mock::userFunction( 'get_post_meta' )->andReturn( [] );
		WP_Mock::userFunction( 'get_userdata' )->with( 0 )->andReturn( false );
		WP_Mock::userFunction( 'get_post_time' )->andReturn( 100 );
		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			function ( $args, $defaults ) {
				return array_merge( $defaults, (array) $args );
			}
		);

		// Without a hooked filter the row comes back as built.
		$row = Store::row( $post );
		$this->assertSame( '', $row['who'] );

		WP_Mock::onFilter( 'nhrrob_issue_reporter_feedback_row' )->with( $row, $post )->reply(
			[
				'id'  => 5,
				'who' => 'Sara',
			]
		);

		$this->assertSame( 'Sara', Store::row( $post )['who'] );
	}
}
