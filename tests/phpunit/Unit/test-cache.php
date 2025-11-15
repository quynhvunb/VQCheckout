<?php
/**
 * Cache Unit Tests
 *
 * @package VQCheckout\Tests\Unit
 */

namespace VQCheckout\Tests\Unit;

use VQCheckout\Cache\Cache;
use VQCheckout\Cache\Keys;

/**
 * Test Cache service
 */
class Test_Cache extends \WP_UnitTestCase {
	private $cache;

	public function setUp(): void {
		parent::setUp();
		$this->cache = new Cache();
	}

	public function test_set_and_get() {
		$key   = 'test_key';
		$value = array( 'foo' => 'bar' );

		$this->cache->set( $key, $value );
		$result = $this->cache->get( $key );

		$this->assertEquals( $value, $result );
	}

	public function test_delete() {
		$key   = 'test_key';
		$value = 'test_value';

		$this->cache->set( $key, $value );
		$this->cache->delete( $key );

		$result = $this->cache->get( $key );

		$this->assertFalse( $result );
	}

	public function test_cache_keys() {
		$instance_id = 123;
		$ward_code   = '00001';
		$subtotal    = 250000;

		$key = Keys::rate_match( $instance_id, $ward_code, $subtotal );

		$this->assertStringContainsString( (string) $instance_id, $key );
		$this->assertStringContainsString( $ward_code, $key );
	}
}
