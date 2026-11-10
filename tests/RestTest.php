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

	public function testANonScalarIsEmpty() {
		$this->assertSame( '', Rest::text( [ 'x' ] ) );
	}
}
