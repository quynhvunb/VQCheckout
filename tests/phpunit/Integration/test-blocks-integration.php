<?php
/**
 * Blocks Integration Tests
 *
 * @package VQCheckout\Tests\Integration
 */

namespace VQCheckout\Tests\Integration;

use VQCheckout\Checkout\Blocks_Integration;

/**
 * Test WooCommerce Blocks integration
 */
class Test_Blocks_Integration extends \WP_UnitTestCase {
	private $blocks;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
			$this->markTestSkipped( 'WooCommerce Blocks not available' );
		}

		$this->blocks = new Blocks_Integration();
	}

	public function test_blocks_integration_initializes() {
		$this->blocks->init();

		$this->assertTrue( has_action( 'woocommerce_blocks_loaded' ) !== false );
		$this->assertTrue( has_action( 'woocommerce_store_api_checkout_update_order_meta' ) !== false );
	}

	public function test_extend_checkout_schema() {
		$schema = $this->blocks->extend_checkout_schema();

		$this->assertIsArray( $schema );
		$this->assertArrayHasKey( 'province', $schema );
		$this->assertArrayHasKey( 'district', $schema );
		$this->assertArrayHasKey( 'ward', $schema );
		$this->assertArrayHasKey( 'gender', $schema );
	}

	public function test_schema_province_field() {
		$schema = $this->blocks->extend_checkout_schema();

		$this->assertEquals( 'string', $schema['province']['type'] );
		$this->assertTrue( $schema['province']['required'] );
		$this->assertNotEmpty( $schema['province']['description'] );
	}

	public function test_schema_district_field() {
		$schema = $this->blocks->extend_checkout_schema();

		$this->assertEquals( 'string', $schema['district']['type'] );
		$this->assertTrue( $schema['district']['required'] );
		$this->assertNotEmpty( $schema['district']['description'] );
	}

	public function test_schema_ward_field() {
		$schema = $this->blocks->extend_checkout_schema();

		$this->assertEquals( 'string', $schema['ward']['type'] );
		$this->assertTrue( $schema['ward']['required'] );
		$this->assertNotEmpty( $schema['ward']['description'] );
	}

	public function test_schema_gender_field() {
		$schema = $this->blocks->extend_checkout_schema();

		$this->assertEquals( 'string', $schema['gender']['type'] );
		$this->assertArrayHasKey( 'enum', $schema['gender'] );
		$this->assertContains( 'anh', $schema['gender']['enum'] );
		$this->assertContains( 'chi', $schema['gender']['enum'] );
	}

	public function test_extend_checkout_data_with_session() {
		WC()->session->set( 'billing_province', '79' );
		WC()->session->set( 'billing_district', '760' );
		WC()->session->set( 'billing_ward', '26734' );
		WC()->session->set( 'billing_gender', 'anh' );

		$data = $this->blocks->extend_checkout_data();

		$this->assertEquals( '79', $data['province'] );
		$this->assertEquals( '760', $data['district'] );
		$this->assertEquals( '26734', $data['ward'] );
		$this->assertEquals( 'anh', $data['gender'] );
	}

	public function test_extend_checkout_data_without_session() {
		$data = $this->blocks->extend_checkout_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'province', $data );
		$this->assertArrayHasKey( 'district', $data );
		$this->assertArrayHasKey( 'ward', $data );
		$this->assertArrayHasKey( 'gender', $data );
	}

	public function test_save_blocks_checkout_fields() {
		$order = wc_create_order();

		$_POST['extensions'] = array(
			'vqcheckout' => array(
				'province' => '79',
				'district' => '760',
				'ward'     => '26734',
				'gender'   => 'anh',
			),
		);

		$this->blocks->save_blocks_checkout_fields( $order );

		$this->assertEquals( '79', $order->get_meta( '_billing_province' ) );
		$this->assertEquals( '760', $order->get_meta( '_billing_district' ) );
		$this->assertEquals( '26734', $order->get_meta( '_billing_ward' ) );
		$this->assertEquals( 'anh', $order->get_meta( '_billing_gender' ) );

		unset( $_POST['extensions'] );
		wp_delete_post( $order->get_id(), true );
	}

	public function test_save_blocks_checkout_fields_sanitizes_input() {
		$order = wc_create_order();

		$_POST['extensions'] = array(
			'vqcheckout' => array(
				'province' => '<script>79</script>',
				'district' => '760<script>',
				'ward'     => '<b>26734</b>',
				'gender'   => 'anh<script>',
			),
		);

		$this->blocks->save_blocks_checkout_fields( $order );

		$this->assertEquals( '79', $order->get_meta( '_billing_province' ) );
		$this->assertEquals( '760', $order->get_meta( '_billing_district' ) );
		$this->assertEquals( '26734', $order->get_meta( '_billing_ward' ) );
		$this->assertEquals( 'anh', $order->get_meta( '_billing_gender' ) );

		unset( $_POST['extensions'] );
		wp_delete_post( $order->get_id(), true );
	}

	public function test_save_blocks_checkout_fields_partial_data() {
		$order = wc_create_order();

		$_POST['extensions'] = array(
			'vqcheckout' => array(
				'province' => '79',
				'district' => '760',
			),
		);

		$this->blocks->save_blocks_checkout_fields( $order );

		$this->assertEquals( '79', $order->get_meta( '_billing_province' ) );
		$this->assertEquals( '760', $order->get_meta( '_billing_district' ) );
		$this->assertEquals( '', $order->get_meta( '_billing_ward' ) );
		$this->assertEquals( '', $order->get_meta( '_billing_gender' ) );

		unset( $_POST['extensions'] );
		wp_delete_post( $order->get_id(), true );
	}

	public function test_save_blocks_checkout_fields_no_data() {
		$order = wc_create_order();

		$this->blocks->save_blocks_checkout_fields( $order );

		$this->assertEquals( '', $order->get_meta( '_billing_province' ) );
		$this->assertEquals( '', $order->get_meta( '_billing_district' ) );
		$this->assertEquals( '', $order->get_meta( '_billing_ward' ) );
		$this->assertEquals( '', $order->get_meta( '_billing_gender' ) );

		wp_delete_post( $order->get_id(), true );
	}

	public function tearDown(): void {
		if ( WC()->session ) {
			WC()->session->set( 'billing_province', '' );
			WC()->session->set( 'billing_district', '' );
			WC()->session->set( 'billing_ward', '' );
			WC()->session->set( 'billing_gender', '' );
		}

		parent::tearDown();
	}
}
