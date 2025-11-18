<?php
/**
 * Sanitizer - Input sanitization and output escaping helpers
 *
 * @package VQCheckout\Security
 */

namespace VQCheckout\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized sanitization and escaping
 */
class Sanitizer {
	/**
	 * Sanitize ward code (alphanumeric, max 10 chars)
	 *
	 * @param string $ward_code Ward code input
	 * @return string
	 */
	public function sanitize_ward_code( $ward_code ) {
		$ward_code = sanitize_text_field( $ward_code );
		$ward_code = preg_replace( '/[^a-zA-Z0-9]/', '', $ward_code );
		return substr( $ward_code, 0, 10 );
	}

	/**
	 * Sanitize province/district code
	 *
	 * @param string $code Location code
	 * @return string
	 */
	public function sanitize_location_code( $code ) {
		$code = sanitize_text_field( $code );
		$code = preg_replace( '/[^0-9]/', '', $code );
		return substr( $code, 0, 4 );
	}

	/**
	 * Sanitize phone number (Vietnamese format)
	 *
	 * @param string $phone Phone number
	 * @return string
	 */
	public function sanitize_phone( $phone ) {
		$phone = sanitize_text_field( $phone );
		// Remove all non-digits
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		// Normalize to 10 digits (remove country code if present)
		if ( strlen( $phone ) > 10 ) {
			$phone = preg_replace( '/^84/', '0', $phone );
		}

		return substr( $phone, 0, 11 );
	}

	/**
	 * Sanitize cart total
	 *
	 * @param mixed $total Cart total value
	 * @return float
	 */
	public function sanitize_cart_total( $total ) {
		return abs( floatval( $total ) );
	}

	/**
	 * Sanitize array of ward codes
	 *
	 * @param array $ward_codes Array of ward codes
	 * @return array
	 */
	public function sanitize_ward_codes( $ward_codes ) {
		if ( ! is_array( $ward_codes ) ) {
			return array();
		}

		return array_filter(
			array_map( array( $this, 'sanitize_ward_code' ), $ward_codes ),
			function( $code ) {
				return ! empty( $code );
			}
		);
	}

	/**
	 * Sanitize rate data for create/update
	 *
	 * @param array $data Rate data
	 * @return array
	 */
	public function sanitize_rate_data( $data ) {
		$sanitized = array();

		if ( isset( $data['instance_id'] ) ) {
			$sanitized['instance_id'] = absint( $data['instance_id'] );
		}

		if ( isset( $data['rate_order'] ) ) {
			$sanitized['rate_order'] = absint( $data['rate_order'] );
		}

		if ( isset( $data['label'] ) ) {
			$sanitized['label'] = sanitize_text_field( $data['label'] );
		}

		if ( isset( $data['base_cost'] ) ) {
			$sanitized['base_cost'] = abs( floatval( $data['base_cost'] ) );
		}

		if ( isset( $data['is_block_rule'] ) ) {
			$sanitized['is_block_rule'] = (int) (bool) $data['is_block_rule'];
		}

		if ( isset( $data['stop_processing'] ) ) {
			$sanitized['stop_processing'] = (int) (bool) $data['stop_processing'];
		}

		if ( isset( $data['conditions'] ) && is_array( $data['conditions'] ) ) {
			$sanitized['conditions'] = $this->sanitize_conditions( $data['conditions'] );
		}

		if ( isset( $data['ward_codes'] ) && is_array( $data['ward_codes'] ) ) {
			$sanitized['ward_codes'] = $this->sanitize_ward_codes( $data['ward_codes'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize shipping conditions
	 *
	 * @param array $conditions Conditions array
	 * @return array
	 */
	public function sanitize_conditions( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}

			$item = array();

			if ( isset( $condition['min'] ) ) {
				$item['min'] = abs( floatval( $condition['min'] ) );
			}

			if ( isset( $condition['max'] ) ) {
				$item['max'] = abs( floatval( $condition['max'] ) );
			}

			if ( isset( $condition['cost'] ) ) {
				$item['cost'] = abs( floatval( $condition['cost'] ) );
			}

			if ( ! empty( $item ) ) {
				$sanitized[] = $item;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize reCAPTCHA response token
	 *
	 * @param string $token reCAPTCHA token
	 * @return string
	 */
	public function sanitize_recaptcha_token( $token ) {
		$token = sanitize_text_field( $token );
		// reCAPTCHA tokens are alphanumeric with hyphens/underscores
		return preg_replace( '/[^a-zA-Z0-9_-]/', '', $token );
	}

	/**
	 * Sanitize IP address
	 *
	 * @param string $ip IP address
	 * @return string|null
	 */
	public function sanitize_ip( $ip ) {
		$ip = sanitize_text_field( $ip );

		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return null;
	}

	/**
	 * Escape JSON for HTML attribute
	 *
	 * @param mixed $data Data to encode
	 * @return string
	 */
	public function esc_json( $data ) {
		return esc_attr( wp_json_encode( $data ) );
	}

	/**
	 * Sanitize search query
	 *
	 * @param string $query Search query
	 * @return string
	 */
	public function sanitize_search( $query ) {
		$query = sanitize_text_field( $query );
		$query = trim( $query );
		return substr( $query, 0, 100 );
	}

	/**
	 * Sanitize pagination parameters
	 *
	 * @param array $params Pagination params (page, per_page, orderby, order)
	 * @return array
	 */
	public function sanitize_pagination( $params ) {
		$sanitized = array(
			'page'     => isset( $params['page'] ) ? max( 1, absint( $params['page'] ) ) : 1,
			'per_page' => isset( $params['per_page'] ) ? min( 100, max( 1, absint( $params['per_page'] ) ) ) : 20,
			'orderby'  => isset( $params['orderby'] ) ? sanitize_key( $params['orderby'] ) : 'rate_order',
			'order'    => isset( $params['order'] ) && in_array( strtoupper( $params['order'] ), array( 'ASC', 'DESC' ), true )
				? strtoupper( $params['order'] )
				: 'ASC',
		);

		return $sanitized;
	}

	/**
	 * Validate and sanitize email
	 *
	 * @param string $email Email address
	 * @return string|false
	 */
	public function sanitize_email( $email ) {
		return sanitize_email( $email );
	}

	/**
	 * Sanitize URL
	 *
	 * @param string $url URL
	 * @return string
	 */
	public function sanitize_url( $url ) {
		return esc_url_raw( $url );
	}

	/**
	 * Sanitize HTML content (allow safe tags)
	 *
	 * @param string $content HTML content
	 * @return string
	 */
	public function sanitize_html( $content ) {
		$allowed = array(
			'a'      => array( 'href' => array(), 'title' => array() ),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'p'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
		);

		return wp_kses( $content, $allowed );
	}
}
