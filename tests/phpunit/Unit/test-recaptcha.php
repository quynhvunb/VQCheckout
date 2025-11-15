<?php
/**
 * Recaptcha Service Unit Tests
 *
 * @package VQCheckout\Tests\Unit
 */

namespace VQCheckout\Tests\Unit;

use VQCheckout\Security\Recaptcha_Service;

/**
 * Test reCAPTCHA service
 */
class Test_Recaptcha extends \WP_UnitTestCase {
	public function test_disabled_by_default() {
		delete_option( 'vqcheckout_options' );

		$recaptcha = new Recaptcha_Service();

		$this->assertFalse( $recaptcha->is_enabled() );
		$this->assertNull( $recaptcha->get_version() );
	}

	public function test_v3_enabled() {
		update_option(
			'vqcheckout_options',
			array(
				'enable_recaptcha'       => '2',
				'recaptcha_sitekey_v3'   => 'test_site_key',
				'recaptcha_secretkey_v3' => 'test_secret_key',
			)
		);

		$recaptcha = new Recaptcha_Service();

		$this->assertTrue( $recaptcha->is_enabled() );
		$this->assertEquals( 'v3', $recaptcha->get_version() );
		$this->assertEquals( 'test_site_key', $recaptcha->get_site_key() );
		$this->assertEquals( 'test_secret_key', $recaptcha->get_secret_key() );
	}

	public function test_v2_enabled() {
		update_option(
			'vqcheckout_options',
			array(
				'enable_recaptcha'     => '1',
				'recaptcha_sitekey'    => 'test_site_key_v2',
				'recaptcha_secretkey'  => 'test_secret_key_v2',
			)
		);

		$recaptcha = new Recaptcha_Service();

		$this->assertTrue( $recaptcha->is_enabled() );
		$this->assertEquals( 'v2', $recaptcha->get_version() );
		$this->assertEquals( 'test_site_key_v2', $recaptcha->get_site_key() );
		$this->assertEquals( 'test_secret_key_v2', $recaptcha->get_secret_key() );
	}

	public function test_verify_when_disabled() {
		delete_option( 'vqcheckout_options' );

		$recaptcha = new Recaptcha_Service();
		$result    = $recaptcha->verify( 'test_token' );

		$this->assertTrue( $result['success'] );
	}

	public function tearDown(): void {
		delete_option( 'vqcheckout_options' );
		parent::tearDown();
	}
}
