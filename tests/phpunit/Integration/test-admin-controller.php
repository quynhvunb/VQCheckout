<?php
/**
 * Admin Controller Integration Tests
 *
 * @package VQCheckout\Tests\Integration
 */

namespace VQCheckout\Tests\Integration;

use VQCheckout\Data\Migrations;

/**
 * Test Admin REST Controller
 */
class Test_Admin_Controller extends \WP_UnitTestCase {
	private $admin_user;

	public function setUp(): void {
		parent::setUp();

		$migrations = new Migrations();
		$migrations->run();

		$this->admin_user = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $this->admin_user );
	}

	public function test_create_rate_endpoint() {
		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/admin/rates' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'zone_id'     => 1,
				'instance_id' => 1,
				'title'       => 'Test Rate',
				'cost'        => 30000,
				'priority'    => 0,
				'ward_codes'  => array( '00001', '00002' ),
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertNotEmpty( $data['rate_id'] );
	}

	public function test_get_rates_endpoint() {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$wpdb->insert(
			$table,
			array(
				'zone_id'     => 1,
				'instance_id' => 1,
				'title'       => 'Test Rate',
				'cost'        => 30000,
				'priority'    => 0,
			),
			array( '%d', '%d', '%s', '%f', '%d' )
		);

		$request  = new \WP_REST_Request( 'GET', '/vqcheckout/v1/admin/rates' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );
		$this->assertEquals( 'Test Rate', $data[0]['title'] );
	}

	public function test_permission_check() {
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'GET', '/vqcheckout/v1/admin/rates' );

		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	public function tearDown(): void {
		Migrations::drop_tables();
		parent::tearDown();
	}
}
