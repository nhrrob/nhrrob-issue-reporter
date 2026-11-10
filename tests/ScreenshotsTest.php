<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Screenshots;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Only real images of a few types and sizes are uploaded, and only images in
 * the media library are linked from an issue.
 */
class ScreenshotsTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'is_wp_error' )->andReturnUsing(
			function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function file( $size = 1000 ) {
		return [
			'name'     => 'shot.png',
			'tmp_name' => '/tmp/php123',
			'size'     => $size,
			'error'    => 0,
		];
	}

	public function testATooLargeImageIsRefusedBeforeItIsRead() {
		WP_Mock::userFunction( 'wp_check_filetype_and_ext' )->never();
		WP_Mock::userFunction( 'media_handle_sideload' )->never();

		$this->assertInstanceOf( \WP_Error::class, Screenshots::upload( $this->file( Screenshots::MAX_BYTES + 1 ) ) );
	}

	public function testAFileThatIsNotAnAllowedImageIsRefused() {
		WP_Mock::userFunction( 'wp_check_filetype_and_ext' )->andReturn( [ 'type' => 'image/svg+xml' ] );
		WP_Mock::userFunction( 'media_handle_sideload' )->never();

		$this->assertInstanceOf( \WP_Error::class, Screenshots::upload( $this->file() ) );
	}

	public function testAFailedUploadIsRefused() {
		WP_Mock::userFunction( 'media_handle_sideload' )->never();

		$this->assertInstanceOf( \WP_Error::class, Screenshots::upload( [ 'error' => 1 ] + $this->file() ) );
		$this->assertInstanceOf( \WP_Error::class, Screenshots::upload( null ) );
	}

	public function testImagesInTheMediaLibraryBecomeMarkdownLinks() {
		WP_Mock::userFunction( 'wp_attachment_is_image' )->andReturn( true );
		WP_Mock::userFunction( 'get_post_mime_type' )->andReturn( 'image/png' );
		WP_Mock::userFunction( 'wp_get_attachment_url' )->andReturnUsing(
			function ( $id ) {
				return 'https://example.com/wp-content/uploads/' . $id . '.png';
			}
		);
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );

		$this->assertSame(
			"![Screenshot 1](https://example.com/wp-content/uploads/4.png)\n\n![Screenshot 2](https://example.com/wp-content/uploads/9.png)",
			Screenshots::markdown( [ 4, '9', 4 ] )
		);
	}

	public function testAnAttachmentThatIsNotAnImageIsRefused() {
		WP_Mock::userFunction( 'wp_attachment_is_image' )->andReturn( false );

		$this->assertInstanceOf( \WP_Error::class, Screenshots::markdown( [ 5 ] ) );
	}

	public function testAtMostFiveScreenshots() {
		$this->assertInstanceOf( \WP_Error::class, Screenshots::markdown( range( 1, Screenshots::MAX_FILES + 1 ) ) );
		$this->assertSame( '', Screenshots::markdown( 'not a list' ) );
	}
}
