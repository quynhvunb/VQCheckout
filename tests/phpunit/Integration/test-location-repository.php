<?php
/**
 * Location Repository Integration Tests
 *
 * @package VQCheckout\Tests\Integration
 */

namespace VQCheckout\Tests\Integration;

use VQCheckout\Cache\Cache;
use VQCheckout\Shipping\Location_Repository;
use VQCheckout\Data\Migrations;

/**
 * Test Location Repository
 */
class Test_Location_Repository extends \WP_UnitTestCase {
	private $cache;
	private $repository;

	public function setUp(): void {
		parent::setUp();

		$migrations = new Migrations();
		$migrations->run();

		$this->cache      = new Cache();
		$this->repository = new Location_Repository( $this->cache );

		$this->seed_sample_data();
	}

	private function seed_sample_data() {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		$wpdb->insert(
			$table,
			array(
				'code'           => '01',
				'name'           => 'Hà Nội',
				'name_with_type' => 'Thành phố Hà Nội',
				'level'          => 1,
			),
			array( '%s', '%s', '%s', '%d' )
		);

		$wpdb->insert(
			$table,
			array(
				'code'           => '010',
				'name'           => 'Ba Đình',
				'name_with_type' => 'Quận Ba Đình',
				'parent_code'    => '01',
				'level'          => 2,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		$wpdb->insert(
			$table,
			array(
				'code'           => '00001',
				'name'           => 'Ba Đình',
				'name_with_type' => 'Phường Ba Đình',
				'parent_code'    => '010',
				'level'          => 3,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
	}

	public function test_get_provinces() {
		$provinces = $this->repository->get_provinces();

		$this->assertIsArray( $provinces );
		$this->assertNotEmpty( $provinces );
		$this->assertEquals( 'Hà Nội', $provinces[0]['name'] );
	}

	public function test_get_districts() {
		$districts = $this->repository->get_districts( '01' );

		$this->assertIsArray( $districts );
		$this->assertNotEmpty( $districts );
		$this->assertEquals( 'Ba Đình', $districts[0]['name'] );
	}

	public function test_get_wards() {
		$wards = $this->repository->get_wards( '010' );

		$this->assertIsArray( $wards );
		$this->assertNotEmpty( $wards );
		$this->assertEquals( 'Ba Đình', $wards[0]['name'] );
	}

	public function test_cache_works() {
		$this->repository->get_provinces();
		$cached_provinces = $this->cache->get( 'locs:root:lvl:1' );

		$this->assertNotFalse( $cached_provinces );
		$this->assertIsArray( $cached_provinces );
	}

	public function tearDown(): void {
		Migrations::drop_tables();
		parent::tearDown();
	}
}
