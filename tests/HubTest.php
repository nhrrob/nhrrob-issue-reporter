<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Hub;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * A hub key is an address and a token; feedback is signed with the site's
 * secret over the time and the exact body sent.
 */
class HubTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
			function ( $s ) {
				return rtrim( $s, '/' );
			}
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function testAKeyIsAnAddressAndAToken() {
		$token = str_repeat( 'a1', 16 );

		$this->assertSame( [ 'https://agency.test/wp-json/nhrrob-issue-reporter-pro/v1/hub', $token ], Hub::parse( ' https://agency.test/wp-json/nhrrob-issue-reporter-pro/v1/hub/#' . $token . ' ' ) );
		$this->assertSame( [ 'https://agency.test/?rest_route=/nhrrob-issue-reporter-pro/v1/hub', $token ], Hub::parse( 'https://agency.test/?rest_route=/nhrrob-issue-reporter-pro/v1/hub#' . $token ) );
		$this->assertNull( Hub::parse( 'ftp://agency.test#' . $token ) );
		$this->assertNull( Hub::parse( 'https://agency.test#short' ) );
		$this->assertNull( Hub::parse( 'https://agency.test#' . $token . '<' ) );
	}

	public function testTheSignatureCoversTheTimeAndTheBody() {
		$this->assertSame( hash_hmac( 'sha256', '100.{"a":1}', 'secret' ), Hub::sign( '100', '{"a":1}', 'secret' ) );
		$this->assertNotSame( Hub::sign( '100', '{"a":1}', 'secret' ), Hub::sign( '101', '{"a":1}', 'secret' ) );
	}

	public function testNothingIsSentWithoutAHub() {
		WP_Mock::userFunction( 'get_option' )->with( Hub::OPTION, [] )->andReturn( [] );
		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			function ( $args, $defaults ) {
				return array_merge( $defaults, (array) $args );
			}
		);

		$this->assertFalse( Hub::connected() );
	}
}
