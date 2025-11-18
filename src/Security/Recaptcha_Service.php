<?php
/**
 * reCAPTCHA Service - Server-side verification
 *
 * @package VQCheckout\Security
 */

namespace VQCheckout\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Handle reCAPTCHA v2 and v3 verification
 */
class Recaptcha_Service {
	const VERIFY_URL_V2 = 'https://www.google.com/recaptcha/api/siteverify';
	const VERIFY_URL_V3 = 'https://www.google.com/recaptcha/api/siteverify';
	const MIN_SCORE_V3  = 0.5;

	private $version;
	private $site_key;
	private $secret_key;

	public function __construct() {
		$settings = get_option( 'vqcheckout_security_settings', array() );

		$this->version    = $settings['recaptcha_version'] ?? 'disabled';
		$this->site_key   = $settings['recaptcha_site_key'] ?? '';
		$this->secret_key = $settings['recaptcha_secret_key'] ?? '';
	}

	/**
	 * Check if reCAPTCHA is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->version !== 'disabled' && ! empty( $this->secret_key );
	}

	/**
	 * Verify reCAPTCHA response
	 *
	 * @param string $response reCAPTCHA response token
	 * @param string $action   Action name (v3 only)
	 * @return array {success: bool, score: float|null, error: string|null}
	 */
	public function verify( $response, $action = '' ) {
		if ( ! $this->is_enabled() ) {
			return array(
				'success' => true,
				'score'   => null,
				'error'   => null,
			);
		}

		if ( empty( $response ) ) {
			return array(
				'success' => false,
				'score'   => null,
				'error'   => 'missing_response',
			);
		}

		$verify_url = $this->version === 'v3' ? self::VERIFY_URL_V3 : self::VERIFY_URL_V2;

		$args = array(
			'secret'   => $this->secret_key,
			'response' => $response,
			'remoteip' => $this->get_client_ip(),
		);

		$http_response = wp_remote_post(
			$verify_url,
			array(
				'body'    => $args,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $http_response ) ) {
			return array(
				'success' => false,
				'score'   => null,
				'error'   => 'http_error',
			);
		}

		$body = wp_remote_retrieve_body( $http_response );
		$result = json_decode( $body, true );

		if ( ! isset( $result['success'] ) ) {
			return array(
				'success' => false,
				'score'   => null,
				'error'   => 'invalid_response',
			);
		}

		// v3: Check score
		if ( $this->version === 'v3' ) {
			$score = $result['score'] ?? 0;

			return array(
				'success' => $result['success'] && $score >= self::MIN_SCORE_V3,
				'score'   => $score,
				'error'   => $score < self::MIN_SCORE_V3 ? 'low_score' : null,
			);
		}

		// v2: Simple success check
		return array(
			'success' => $result['success'],
			'score'   => null,
			'error'   => ! $result['success'] ? 'verification_failed' : null,
		);
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Get site key for frontend
	 *
	 * @return string
	 */
	public function get_site_key() {
		return $this->site_key;
	}

	/**
	 * Get version
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
