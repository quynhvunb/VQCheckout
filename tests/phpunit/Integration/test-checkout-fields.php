<?php
/**
 * Checkout Fields Integration Tests
 *
 * @package VQCheckout\Tests\Integration
 */

namespace VQCheckout\Tests\Integration;

use VQCheckout\Checkout\Fields;
use VQCheckout\Data\Migrations;

/**
 * Test checkout field customization
 */
class Test_Checkout_Fields extends \WP_UnitTestCase {
	private $fields;

	public function setUp(): void {
		parent::setUp();

		$migrations = new Migrations();
		$migrations->run();

		$this->fields = new Fields();
		$this->fields->init();
	}

	public function test_vn_address_fields_added() {
		$fields = array();
		$fields = apply_filters( 'woocommerce_checkout_fields', $fields );

		$this->assertArrayHasKey( 'billing', $fields );
		$this->assertArrayHasKey( 'billing_province', $fields['billing'] );
		$this->assertArrayHasKey( 'billing_district', $fields['billing'] );
		$this->assertArrayHasKey( 'billing_ward', $fields['billing'] );
	}

	public function test_province_field_required() {
		$fields = apply_filters( 'woocommerce_checkout_fields', array() );

		$this->assertTrue( $fields['billing']['billing_province']['required'] );
		$this->assertEquals( 'select', $fields['billing']['billing_province']['type'] );
	}

	public function test_district_field_required() {
		$fields = apply_filters( 'woocommerce_checkout_fields', array() );

		$this->assertTrue( $fields['billing']['billing_district']['required'] );
		$this->assertEquals( 'select', $fields['billing']['billing_district']['type'] );
	}

	public function test_ward_field_required() {
		$fields = apply_filters( 'woocommerce_checkout_fields', array() );

		$this->assertTrue( $fields['billing']['billing_ward']['required'] );
		$this->assertEquals( 'select', $fields['billing']['billing_ward']['type'] );
	}

	public function test_default_fields_customized() {
		$default_fields = array(
			'country'  => array( 'required' => true ),
			'postcode' => array( 'required' => true ),
			'state'    => array(),
			'city'     => array(),
		);

		$fields = apply_filters( 'woocommerce_default_address_fields', $default_fields );

		$this->assertFalse( $fields['country']['required'] );
		$this->assertFalse( $fields['postcode']['required'] );
		$this->assertArrayNotHasKey( 'state', $fields );
		$this->assertArrayNotHasKey( 'city', $fields );
	}

	public function test_phone_vn_validation() {
		update_option( 'vqcheckout_options', array( 'phone_vn' => '1' ) );

		$data = array(
			'billing_phone' => '0987654321',
		);

		$result = apply_filters( 'woocommerce_checkout_posted_data', $data );

		$this->assertEquals( '0987654321', $result['billing_phone'] );
	}

	public function tearDown(): void {
		Migrations::drop_tables();
		delete_option( 'vqcheckout_options' );
		parent::tearDown();
	}
}
