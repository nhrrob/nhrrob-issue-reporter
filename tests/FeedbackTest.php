<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Feedback\Store;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * What the widget sends is kept as a private post, cleaned and capped.
 */
class FeedbackTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'wp_check_invalid_utf8' )->andReturnArg( 0 );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function testFeedbackIsAPrivatePostWithCleanDetails() {
		$saved = [];
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 3 );
		WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );
		WP_Mock::userFunction( 'wp_insert_post' )->once()->andReturnUsing(
			function ( $post ) {
				$this->assertSame( Store::TYPE, $post['post_type'] );
				$this->assertSame( 'private', $post['post_status'] );
				$this->assertSame( 'The button <b>does</b> nothing', $post['post_title'] );
				return 9;
			}
		);
		WP_Mock::userFunction( 'update_post_meta' )->andReturnUsing(
			function ( $id, $key, $value ) use ( &$saved ) {
				$saved[ $id ][ $key ] = $value;
				return true;
			}
		);
		WP_Mock::userFunction( 'wp_update_post' )->once();
		// wp_insert_post() and update_post_meta() unslash, and kses would strip the <b>.
		WP_Mock::userFunction( 'wp_slash' )->twice()->andReturnArg( 0 );
		WP_Mock::userFunction( 'has_filter' )->with( 'content_save_pre', 'wp_filter_post_kses' )->andReturn( 10 );
		WP_Mock::userFunction( 'kses_remove_filters' )->once();
		WP_Mock::userFunction( 'kses_init_filters' )->once();

		$id = Store::create(
			[
				'text'    => "The button <b>does</b> nothing\nOn my phone.",
				'kind'    => 'hack',
				'page'    => "/book\n",
				'browser' => 'Safari 19 · iPhone',
				'screen'  => '390 × 844',
				'errors'  => array_merge( [ str_repeat( 'e', 600 ), '' ], array_fill( 0, 30, 'TypeError' ) ),
			],
			12
		);

		$meta = $saved[9][ Store::META ];
		$this->assertSame( 9, $id );
		$this->assertSame( 'new', $meta['status'] );
		$this->assertSame( 'bug', $meta['kind'] );
		$this->assertSame( '/book', $meta['page'] );
		$this->assertCount( 20, $meta['errors'] );
		$this->assertSame( 500, strlen( $meta['errors'][0] ) );
		$this->assertSame( 12, $meta['shot'] );
		$this->assertSame( 9, $saved[12][ Store::SHOT ] );
	}
}
