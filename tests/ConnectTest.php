<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Connect;
use PHPUnit\Framework\TestCase;

/**
 * GitHub's answers to a device-flow poll, as the app is told them.
 */
class ConnectTest extends TestCase {

	/**
	 * @dataProvider answers
	 */
	public function testAPollAnswerMeansOneThing( $answer, $status ) {
		$this->assertSame( $status, Connect::status( $answer ) );
	}

	public function answers() {
		return [
			'approved'       => [ [ 'access_token' => 'gho_abc', 'token_type' => 'bearer' ], 'done' ],
			'not yet'        => [ [ 'error' => 'authorization_pending' ], 'pending' ],
			'too often'      => [ [ 'error' => 'slow_down', 'interval' => 10 ], 'slow_down' ],
			'code expired'   => [ [ 'error' => 'expired_token' ], 'expired' ],
			'cancelled'      => [ [ 'error' => 'access_denied' ], 'denied' ],
			'anything else'  => [ [ 'error' => 'incorrect_device_code' ], 'error' ],
			'empty token'    => [ [ 'access_token' => '' ], 'error' ],
			'token not text' => [ [ 'access_token' => [ 'x' ] ], 'error' ],
		];
	}

	public function testTheClientIdIsTheRegisteredApp() {
		$this->assertSame( 'Ov23liJiAf3ChO15S5pG', Connect::CLIENT_ID );
	}
}
