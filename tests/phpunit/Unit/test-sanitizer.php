<?php
/**
 * Sanitizer Unit Tests
 *
 * @package VQCheckout\Tests\Unit
 */

namespace VQCheckout\Tests\Unit;

use VQCheckout\Utils\Sanitizer;

/**
 * Test Sanitizer utility
 */
class Test_Sanitizer extends \WP_UnitTestCase {
	public function test_sanitize_phone() {
		$this->assertEquals( '0987654321', Sanitizer::phone( '+84987654321' ) );
		$this->assertEquals( '0987654321', Sanitizer::phone( '0987654321' ) );
		$this->assertEquals( '0987654321', Sanitizer::phone( '098-765-4321' ) );
	}

	public function test_sanitize_ward_code() {
		$this->assertEquals( '00001', Sanitizer::ward_code( '00001' ) );
		$this->assertEquals( '00001', Sanitizer::ward_code( 'abc00001xyz' ) );
	}

	public function test_sanitize_price() {
		$this->assertEquals( 30000, Sanitizer::price( '30000' ) );
		$this->assertEquals( 0, Sanitizer::price( '-1000' ) );
		$this->assertEquals( 25500.5, Sanitizer::price( '25500.5' ) );
	}

	public function test_sanitize_array_of_strings() {
		$input = array( 'Test<script>', 'Hello', 123 );
		$result = Sanitizer::array_of_strings( $input );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test', $result[0] );
		$this->assertEquals( 'Hello', $result[1] );
		$this->assertEquals( '123', $result[2] );
	}

	public function test_sanitize_json() {
		$json_string = '{"min": 100000, "max": 500000}';
		$result = Sanitizer::json( $json_string );

		$this->assertIsArray( $result );
		$this->assertEquals( 100000, $result['min'] );

		$array = array( 'min' => 100000 );
		$result = Sanitizer::json( $array );
		$this->assertEquals( $array, $result );
	}
}
