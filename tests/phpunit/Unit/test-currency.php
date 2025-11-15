<?php
/**
 * Currency Unit Tests
 *
 * @package VQCheckout\Tests\Unit
 */

namespace VQCheckout\Tests\Unit;

use VQCheckout\Checkout\Currency;

/**
 * Test currency symbol conversion
 */
class Test_Currency extends \WP_UnitTestCase {
	private $currency;

	public function setUp(): void {
		parent::setUp();
		$this->currency = new Currency();
	}

	public function test_currency_symbol_not_changed_when_disabled() {
		delete_option( 'vqcheckout_options' );

		$this->currency->init();

		$symbol = apply_filters( 'woocommerce_currency_symbol', '₫', 'VND' );

		$this->assertEquals( '₫', $symbol );
	}

	public function test_currency_symbol_changed_when_enabled() {
		update_option( 'vqcheckout_options', array( 'to_vnd' => '1' ) );

		$this->currency->init();

		$symbol = apply_filters( 'woocommerce_currency_symbol', '₫', 'VND' );

		$this->assertEquals( 'VNĐ', $symbol );
	}

	public function test_other_currencies_not_affected() {
		update_option( 'vqcheckout_options', array( 'to_vnd' => '1' ) );

		$this->currency->init();

		$symbol = apply_filters( 'woocommerce_currency_symbol', '$', 'USD' );

		$this->assertEquals( '$', $symbol );
	}

	public function tearDown(): void {
		delete_option( 'vqcheckout_options' );
		parent::tearDown();
	}
}
