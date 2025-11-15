<?php
/**
 * Sanitizer Utility
 *
 * @package VQCheckout\Utils
 */

namespace VQCheckout\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Input sanitization helpers
 */
class Sanitizer {
	public static function phone( $phone ) {
		$phone = sanitize_text_field( $phone );
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		if ( strpos( $phone, '+84' ) === 0 ) {
			$phone = '0' . substr( $phone, 3 );
		}

		return $phone;
	}

	public static function ward_code( $code ) {
		return preg_replace( '/[^0-9]/', '', sanitize_text_field( $code ) );
	}

	public static function price( $price ) {
		return max( 0, floatval( $price ) );
	}

	public static function array_of_strings( $array ) {
		if ( ! is_array( $array ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $array );
	}

	public static function json( $json ) {
		if ( is_string( $json ) ) {
			$data = json_decode( $json, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $data;
			}
		}

		return is_array( $json ) ? $json : array();
	}
}
