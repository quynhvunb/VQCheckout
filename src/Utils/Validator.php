<?php
/**
 * Validator Utility
 *
 * @package VQCheckout\Utils
 */

namespace VQCheckout\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Input validation helpers
 */
class Validator {
	public static function phone_vn( $phone ) {
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		if ( strlen( $phone ) !== 10 ) {
			return false;
		}

		if ( ! in_array( substr( $phone, 0, 2 ), array( '03', '05', '07', '08', '09' ), true ) ) {
			return false;
		}

		return true;
	}

	public static function ward_code( $code ) {
		return preg_match( '/^[0-9]{5}$/', $code );
	}

	public static function price( $price ) {
		return is_numeric( $price ) && $price >= 0;
	}

	public static function email( $email ) {
		return is_email( $email );
	}

	public static function required( $value ) {
		return ! empty( $value );
	}
}
