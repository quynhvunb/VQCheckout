<?php
/**
 * Google reCAPTCHA Service
 *
 * @package VQCheckout\Security
 */

namespace VQCheckout\Security;

defined( 'ABSPATH' ) || exit;

/**
 * reCAPTCHA v3/v2 validation
 */
class Recaptcha_Service {
	const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
	const V3_THRESHOLD = 0.5;

	private $options;

	public function __construct() {
		$this->options = get_option( 'vqcheckout_options', array() );
	}

	public function is_enabled() {
		return ! empty( $this->options['enable_recaptcha'] );
	}

	public function get_version() {
		$version = $this->options['enable_recaptcha'] ?? '0';

		if ( $version === '1' ) {
			return 'v2';
		} elseif ( $version === '2' ) {
			return 'v3';
		}

		return null;
	}

	public function get_site_key() {
		$version = $this->get_version();

		if ( $version === 'v3' ) {
			return $this->options['recaptcha_sitekey_v3'] ?? '';
		} elseif ( $version === 'v2' ) {
			return $this->options['recaptcha_sitekey'] ?? '';
		}

		return '';
	}

	public function get_secret_key() {
		$version = $this->get_version();

		if ( $version === 'v3' ) {
			return $this->options['recaptcha_secretkey_v3'] ?? '';
		} elseif ( $version === 'v2' ) {
			return $this->options['recaptcha_secretkey'] ?? '';
		}

		return '';
	}

	public function verify( $token, $action = 'checkout' ) {
		if ( ! $this->is_enabled() ) {
			return array(
				'success' => true,
				'message' => 'reCAPTCHA disabled',
			);
		}

		$secret = $this->get_secret_key();

		if ( empty( $secret ) || empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'reCAPTCHA configuration error', 'vq-checkout' ),
			);
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'body' => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => Rate_Limiter::get_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'VQCheckout reCAPTCHA error: ' . $response->get_error_message() );
			return array(
				'success' => false,
				'message' => __( 'reCAPTCHA verification failed', 'vq-checkout' ),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['success'] ) ) {
			$this->log_failure( $action, $body );
			return array(
				'success' => false,
				'message' => __( 'reCAPTCHA verification failed', 'vq-checkout' ),
			);
		}

		if ( $this->get_version() === 'v3' ) {
			$score = $body['score'] ?? 0;

			if ( $score < self::V3_THRESHOLD ) {
				$this->log_failure( $action, $body );
				return array(
					'success' => false,
					'message' => __( 'reCAPTCHA score too low', 'vq-checkout' ),
					'score'   => $score,
				);
			}

			$this->log_success( $action, $score );

			return array(
				'success' => true,
				'score'   => $score,
			);
		}

		$this->log_success( $action, 1 );

		return array(
			'success' => true,
		);
	}

	private function log_success( $action, $score ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_security_log';

		$wpdb->insert(
			$table,
			array(
				'ip_address' => Rate_Limiter::get_ip(),
				'action'     => 'recaptcha_' . $action,
				'score'      => $score,
				'decision'   => 'allowed',
			),
			array( '%s', '%s', '%f', '%s' )
		);
	}

	private function log_failure( $action, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_security_log';

		$wpdb->insert(
			$table,
			array(
				'ip_address' => Rate_Limiter::get_ip(),
				'action'     => 'recaptcha_' . $action,
				'score'      => $data['score'] ?? null,
				'decision'   => 'blocked',
				'metadata'   => wp_json_encode( $data ),
			),
			array( '%s', '%s', '%f', '%s', '%s' )
		);
	}

	public function enqueue_script() {
		if ( ! $this->is_enabled() || ! is_checkout() ) {
			return;
		}

		$version  = $this->get_version();
		$site_key = $this->get_site_key();

		if ( empty( $site_key ) ) {
			return;
		}

		if ( $version === 'v3' ) {
			wp_enqueue_script(
				'google-recaptcha-v3',
				'https://www.google.com/recaptcha/api.js?render=' . $site_key,
				array(),
				null,
				true
			);
		} elseif ( $version === 'v2' ) {
			wp_enqueue_script(
				'google-recaptcha-v2',
				'https://www.google.com/recaptcha/api.js',
				array(),
				null,
				true
			);
		}
	}
}
