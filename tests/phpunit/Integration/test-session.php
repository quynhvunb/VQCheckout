<?php
/**
 * Session Integration Tests
 *
 * @package VQCheckout\Tests\Integration
 */

namespace VQCheckout\Tests\Integration;

use VQCheckout\Checkout\Session;

/**
 * Test session management
 */
class Test_Session extends \WP_UnitTestCase {
	private $session;

	public function setUp(): void {
		parent::setUp();
		$this->session = new Session();
		$this->session->init();

		if ( ! class_exists( 'WC_Session_Handler' ) ) {
			$this->markTestSkipped( 'WooCommerce not loaded' );
		}

		WC()->session = new \WC_Session_Handler();
		WC()->session->init();
	}

	public function test_ward_code_saved_to_session() {
		$post_data = 'billing_ward=00001&billing_district=010&billing_province=01';

		do_action( 'woocommerce_checkout_update_order_review', $post_data );

		$this->assertEquals( '00001', WC()->session->get( 'billing_ward' ) );
		$this->assertEquals( '010', WC()->session->get( 'billing_district' ) );
		$this->assertEquals( '01', WC()->session->get( 'billing_province' ) );
	}

	public function test_ward_code_added_to_package() {
		WC()->session->set( 'billing_ward', '00001' );

		$packages = array(
			array(
				'destination' => array(
					'country' => 'VN',
				),
			),
		);

		$result = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		$this->assertEquals( '00001', $result[0]['destination']['ward_code'] );
	}

	public function tearDown(): void {
		if ( WC()->session ) {
			WC()->session = null;
		}
		parent::tearDown();
	}
}
