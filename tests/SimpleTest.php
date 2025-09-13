<?php
/**
 * Simple test to verify PHPUnit setup
 */

class SimpleTest extends WP_UnitTestCase {

	public function test_wordpress_loaded() {
		$this->assertTrue( function_exists( 'add_action' ) );
	}

	public function test_plugin_loaded() {
		$this->assertTrue( defined( 'EIGHTYFOUREM_GF_AI_VERSION' ) );
	}
}