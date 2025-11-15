<?php
/**
 * Phone Lookup Integration Tests
 *
 * @package VQCheckout\Tests\Integration
 */

namespace VQCheckout\Tests\Integration;

use VQCheckout\API\Phone_Controller;

/**
 * Test phone lookup functionality
 */
class Test_Phone_Lookup extends \WP_UnitTestCase {
	private $controller;
	private $order_id;
	private $test_phone = '0987654321';

	public function setUp(): void {
		parent::setUp();

		$this->controller = new Phone_Controller();

		// Enable phone lookup feature.
		update_option( 'vqcheckout_options', array( 'enable_phone_lookup' => '1' ) );

		// Create a test order.
		$this->order_id = $this->create_test_order();
	}

	private function create_test_order() {
		$order = wc_create_order();
		$order->set_billing_first_name( 'Nguyen Van' );
		$order->set_billing_last_name( 'A' );
		$order->set_billing_phone( $this->test_phone );
		$order->set_billing_email( 'test@example.com' );
		$order->set_billing_address_1( '123 Test Street' );
		$order->set_billing_city( 'Ho Chi Minh' );
		$order->set_billing_postcode( '700000' );
		$order->set_billing_country( 'VN' );

		$order->update_meta_data( '_billing_province', '79' );
		$order->update_meta_data( '_billing_district', '760' );
		$order->update_meta_data( '_billing_ward', '26734' );
		$order->update_meta_data( '_billing_gender', 'anh' );

		$order->save();

		return $order->get_id();
	}

	public function test_validate_phone_valid() {
		$result = $this->controller->validate_phone( '0987654321' );

		$this->assertTrue( $result );
	}

	public function test_validate_phone_with_plus_84() {
		$result = $this->controller->validate_phone( '+84987654321' );

		$this->assertTrue( $result );
	}

	public function test_validate_phone_invalid_format() {
		$result = $this->controller->validate_phone( '123456' );

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	public function test_validate_phone_empty() {
		$result = $this->controller->validate_phone( '' );

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	public function test_lookup_phone_found() {
		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', $this->test_phone );

		$response = $this->controller->lookup_phone( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['found'] );
		$this->assertArrayHasKey( 'address', $data );
	}

	public function test_lookup_phone_not_found() {
		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', '0111111111' );

		$response = $this->controller->lookup_phone( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['found'] );
		$this->assertArrayNotHasKey( 'address', $data );
	}

	public function test_lookup_phone_returns_minimal_data() {
		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', $this->test_phone );

		$response = $this->controller->lookup_phone( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'first_name', $data['address'] );
		$this->assertArrayHasKey( 'last_name', $data['address'] );
		$this->assertArrayHasKey( 'address_1', $data['address'] );
		$this->assertEquals( 'Nguyen Van', $data['address']['first_name'] );
	}

	public function test_lookup_phone_masks_email() {
		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', $this->test_phone );

		$response = $this->controller->lookup_phone( $request );
		$data     = $response->get_data();

		$email = $data['address']['email'];

		$this->assertStringContainsString( '*', $email );
		$this->assertStringContainsString( '@example.com', $email );
		$this->assertStringStartsWith( 'te', $email );
	}

	public function test_lookup_phone_includes_vn_fields() {
		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', $this->test_phone );

		$response = $this->controller->lookup_phone( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'province', $data['address'] );
		$this->assertArrayHasKey( 'district', $data['address'] );
		$this->assertArrayHasKey( 'ward', $data['address'] );
		$this->assertArrayHasKey( 'gender', $data['address'] );

		$this->assertEquals( '79', $data['address']['province'] );
		$this->assertEquals( '760', $data['address']['district'] );
		$this->assertEquals( '26734', $data['address']['ward'] );
		$this->assertEquals( 'anh', $data['address']['gender'] );
	}

	public function test_lookup_phone_disabled_feature() {
		update_option( 'vqcheckout_options', array( 'enable_phone_lookup' => '' ) );

		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', $this->test_phone );

		$response = $this->controller->lookup_phone( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'feature_disabled', $response->get_error_code() );
	}

	public function test_lookup_phone_finds_most_recent_order() {
		// Create another order with same phone.
		$order2 = wc_create_order();
		$order2->set_billing_first_name( 'Tran Thi' );
		$order2->set_billing_last_name( 'B' );
		$order2->set_billing_phone( $this->test_phone );
		$order2->set_billing_email( 'newer@example.com' );
		$order2->set_billing_address_1( '456 New Street' );
		$order2->save();

		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', $this->test_phone );

		$response = $this->controller->lookup_phone( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'Tran Thi', $data['address']['first_name'] );
		$this->assertEquals( '456 New Street', $data['address']['address_1'] );
	}

	public function test_lookup_phone_filters_empty_values() {
		$order = wc_get_order( $this->order_id );
		$order->set_billing_company( '' );
		$order->set_billing_address_2( '' );
		$order->save();

		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', $this->test_phone );

		$response = $this->controller->lookup_phone( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'company', $data['address'] );
		$this->assertArrayNotHasKey( 'address_2', $data['address'] );
	}

	public function test_email_masking_short_email() {
		$order = wc_get_order( $this->order_id );
		$order->set_billing_email( 'ab@test.com' );
		$order->save();

		$request = new \WP_REST_Request( 'POST', '/vqcheckout/v1/phone/lookup' );
		$request->set_param( 'phone', $this->test_phone );

		$response = $this->controller->lookup_phone( $request );
		$data     = $response->get_data();

		$email = $data['address']['email'];

		$this->assertStringContainsString( '*', $email );
		$this->assertStringContainsString( '@test.com', $email );
	}

	public function tearDown(): void {
		if ( $this->order_id ) {
			wp_delete_post( $this->order_id, true );
		}

		delete_option( 'vqcheckout_options' );

		parent::tearDown();
	}
}
