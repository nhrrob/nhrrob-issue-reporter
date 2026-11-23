<?php
namespace NHRRob\IssueReporter\Tests;

use NHRRob\IssueReporter\Settings;
use PHPUnit\Framework\TestCase;

/**
 * The feedback widget's settings.
 */
class SettingsTest extends TestCase {

	public function testTheWidgetIsOffForAdministratorsAndEditorsWithAButtonUntilChanged() {
		$this->assertSame(
			[
				'enabled' => false,
				'roles'   => [ 'administrator', 'editor' ],
				'button'  => true,
			],
			Settings::widget( [] )
		);
	}

	public function testRolesAreSlugsOnlyAndEachOnce() {
		$widget = Settings::widget(
			[
				'enabled' => '1',
				'roles'   => [ 'Editor', 'editor', 'shop_manager', '<b>x</b>', [ 'a' ], '' ],
				'button'  => false,
			]
		);

		$this->assertTrue( $widget['enabled'] );
		$this->assertSame( [ 'editor', 'shop_manager', 'bxb' ], $widget['roles'] );
		$this->assertFalse( $widget['button'] );
	}

	public function testNoRolesMeansNobody() {
		$this->assertSame( [], Settings::widget( [ 'roles' => [] ] )['roles'] );
	}
}
