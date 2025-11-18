<?php
/**
 * Validation Utilities
 *
 * @package VQCheckout\Utils
 */

namespace VQCheckout\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Validation helper functions
 */
class Validation {
	/**
	 * Validate ward code format
	 *
	 * @param string $ward_code Ward code
	 * @return bool
	 */
	public static function is_valid_ward_code( $ward_code ) {
		// Ward codes are typically 5-10 alphanumeric characters
		return ! empty( $ward_code )
			&& is_string( $ward_code )
			&& preg_match( '/^[a-zA-Z0-9]{2,10}$/', $ward_code );
	}

	/**
	 * Validate province/district code
	 *
	 * @param string $code Location code
	 * @return bool
	 */
	public static function is_valid_location_code( $code ) {
		// Location codes are typically 2-4 digit numbers
		return ! empty( $code )
			&& is_string( $code )
			&& preg_match( '/^\d{1,4}$/', $code );
	}

	/**
	 * Validate cart total
	 *
	 * @param mixed $total Cart total
	 * @return bool
	 */
	public static function is_valid_cart_total( $total ) {
		if ( ! is_numeric( $total ) ) {
			return false;
		}

		$total = floatval( $total );
		return $total >= 0 && $total < 999999999;
	}

	/**
	 * Validate rate data
	 *
	 * @param array $data Rate data
	 * @return true|\WP_Error
	 */
	public static function validate_rate_data( $data ) {
		$errors = array();

		// Required fields
		if ( empty( $data['label'] ) ) {
			$errors[] = __( 'Rate label is required.', 'vq-checkout' );
		}

		if ( ! isset( $data['base_cost'] ) || ! is_numeric( $data['base_cost'] ) ) {
			$errors[] = __( 'Base cost must be a valid number.', 'vq-checkout' );
		}

		if ( empty( $data['ward_codes'] ) || ! is_array( $data['ward_codes'] ) ) {
			$errors[] = __( 'At least one ward must be selected.', 'vq-checkout' );
		}

		// Validate conditions if present
		if ( ! empty( $data['conditions'] ) ) {
			if ( ! is_array( $data['conditions'] ) ) {
				$errors[] = __( 'Conditions must be an array.', 'vq-checkout' );
			} else {
				foreach ( $data['conditions'] as $index => $condition ) {
					if ( ! is_array( $condition ) ) {
						$errors[] = sprintf( __( 'Condition %d must be an object.', 'vq-checkout' ), $index + 1 );
						continue;
					}

					if ( isset( $condition['min'] ) && ! is_numeric( $condition['min'] ) ) {
						$errors[] = sprintf( __( 'Condition %d: min must be a number.', 'vq-checkout' ), $index + 1 );
					}

					if ( isset( $condition['max'] ) && ! is_numeric( $condition['max'] ) ) {
						$errors[] = sprintf( __( 'Condition %d: max must be a number.', 'vq-checkout' ), $index + 1 );
					}

					if ( isset( $condition['cost'] ) && ! is_numeric( $condition['cost'] ) ) {
						$errors[] = sprintf( __( 'Condition %d: cost must be a number.', 'vq-checkout' ), $index + 1 );
					}

					if ( isset( $condition['min'], $condition['max'] ) && floatval( $condition['min'] ) > floatval( $condition['max'] ) ) {
						$errors[] = sprintf( __( 'Condition %d: min cannot be greater than max.', 'vq-checkout' ), $index + 1 );
					}
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'validation_failed', implode( ' ', $errors ) );
		}

		return true;
	}

	/**
	 * Validate email address
	 *
	 * @param string $email Email address
	 * @return bool
	 */
	public static function is_valid_email( $email ) {
		return is_email( $email );
	}

	/**
	 * Validate URL
	 *
	 * @param string $url URL
	 * @return bool
	 */
	public static function is_valid_url( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Validate IP address
	 *
	 * @param string $ip IP address
	 * @return bool
	 */
	public static function is_valid_ip( $ip ) {
		return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * Validate phone number (Vietnamese)
	 *
	 * @param string $phone Phone number
	 * @return bool
	 */
	public static function is_valid_phone( $phone ) {
		return Phone::validate( $phone );
	}

	/**
	 * Validate reCAPTCHA token format
	 *
	 * @param string $token reCAPTCHA token
	 * @return bool
	 */
	public static function is_valid_recaptcha_token( $token ) {
		// reCAPTCHA tokens are typically long alphanumeric strings
		return ! empty( $token )
			&& is_string( $token )
			&& strlen( $token ) > 20
			&& preg_match( '/^[a-zA-Z0-9_-]+$/', $token );
	}

	/**
	 * Validate positive integer
	 *
	 * @param mixed $value Value to validate
	 * @return bool
	 */
	public static function is_positive_int( $value ) {
		return is_numeric( $value ) && intval( $value ) > 0;
	}

	/**
	 * Validate non-negative number
	 *
	 * @param mixed $value Value to validate
	 * @return bool
	 */
	public static function is_non_negative( $value ) {
		return is_numeric( $value ) && floatval( $value ) >= 0;
	}

	/**
	 * Validate date format (Y-m-d)
	 *
	 * @param string $date Date string
	 * @return bool
	 */
	public static function is_valid_date( $date ) {
		$d = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Validate JSON string
	 *
	 * @param string $json JSON string
	 * @return bool
	 */
	public static function is_valid_json( $json ) {
		if ( ! is_string( $json ) ) {
			return false;
		}

		json_decode( $json );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Validate array structure
	 *
	 * @param array $array    Array to validate
	 * @param array $required Required keys
	 * @return true|\WP_Error
	 */
	public static function validate_array_structure( $array, $required ) {
		if ( ! is_array( $array ) ) {
			return new \WP_Error( 'invalid_array', __( 'Data must be an array.', 'vq-checkout' ) );
		}

		$missing = array();

		foreach ( $required as $key ) {
			if ( ! isset( $array[ $key ] ) ) {
				$missing[] = $key;
			}
		}

		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'missing_fields',
				sprintf(
					__( 'Missing required fields: %s', 'vq-checkout' ),
					implode( ', ', $missing )
				)
			);
		}

		return true;
	}

	/**
	 * Validate string length
	 *
	 * @param string $str String to validate
	 * @param int    $min Minimum length
	 * @param int    $max Maximum length
	 * @return bool
	 */
	public static function is_valid_length( $str, $min = 1, $max = 255 ) {
		$length = mb_strlen( $str );
		return $length >= $min && $length <= $max;
	}

	/**
	 * Validate enum value
	 *
	 * @param mixed $value   Value to validate
	 * @param array $allowed Allowed values
	 * @return bool
	 */
	public static function is_valid_enum( $value, $allowed ) {
		return in_array( $value, $allowed, true );
	}

	/**
	 * Validate pagination parameters
	 *
	 * @param array $params Pagination params
	 * @return true|\WP_Error
	 */
	public static function validate_pagination( $params ) {
		if ( isset( $params['page'] ) && ! self::is_positive_int( $params['page'] ) ) {
			return new \WP_Error( 'invalid_page', __( 'Page must be a positive integer.', 'vq-checkout' ) );
		}

		if ( isset( $params['per_page'] ) ) {
			$per_page = intval( $params['per_page'] );
			if ( $per_page < 1 || $per_page > 100 ) {
				return new \WP_Error( 'invalid_per_page', __( 'Per page must be between 1 and 100.', 'vq-checkout' ) );
			}
		}

		if ( isset( $params['order'] ) && ! self::is_valid_enum( strtoupper( $params['order'] ), array( 'ASC', 'DESC' ) ) ) {
			return new \WP_Error( 'invalid_order', __( 'Order must be ASC or DESC.', 'vq-checkout' ) );
		}

		return true;
	}

	/**
	 * Sanitize and validate input
	 *
	 * @param mixed  $value      Value to validate
	 * @param string $type       Type (string, int, float, email, etc.)
	 * @param bool   $required   Is required
	 * @param mixed  $default    Default value if not required
	 * @return mixed|\WP_Error
	 */
	public static function sanitize_and_validate( $value, $type, $required = false, $default = null ) {
		if ( empty( $value ) && $required ) {
			return new \WP_Error( 'required_field', __( 'This field is required.', 'vq-checkout' ) );
		}

		if ( empty( $value ) && ! $required ) {
			return $default;
		}

		switch ( $type ) {
			case 'string':
				return sanitize_text_field( $value );

			case 'int':
				return absint( $value );

			case 'float':
				return floatval( $value );

			case 'email':
				$email = sanitize_email( $value );
				if ( ! is_email( $email ) ) {
					return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'vq-checkout' ) );
				}
				return $email;

			case 'url':
				return esc_url_raw( $value );

			case 'phone':
				$phone = Phone::normalize( $value );
				if ( ! Phone::validate( $phone ) ) {
					return new \WP_Error( 'invalid_phone', __( 'Invalid phone number.', 'vq-checkout' ) );
				}
				return $phone;

			case 'ward_code':
				return preg_replace( '/[^a-zA-Z0-9]/', '', $value );

			default:
				return $value;
		}
	}
}
