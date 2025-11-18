<?php
/**
 * Phone Number Utilities (Vietnam)
 *
 * @package VQCheckout\Utils
 */

namespace VQCheckout\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Vietnamese phone number helper functions
 */
class Phone {
	/**
	 * Valid Vietnamese mobile prefixes (10-digit format)
	 */
	const MOBILE_PREFIXES = array(
		'032', '033', '034', '035', '036', '037', '038', '039', // Viettel
		'070', '079', '077', '076', '078',                      // Mobifone
		'083', '084', '085', '081', '082',                      // Vinaphone
		'056', '058',                                            // Vietnamobile
		'059',                                                   // Gmobile
		'092', '052', '088',                                     // Other
	);

	/**
	 * Validate Vietnamese phone number
	 *
	 * @param string $phone Phone number
	 * @return bool
	 */
	public static function validate( $phone ) {
		$normalized = self::normalize( $phone );

		if ( empty( $normalized ) ) {
			return false;
		}

		// Must be 10 or 11 digits
		if ( ! preg_match( '/^0\d{9,10}$/', $normalized ) ) {
			return false;
		}

		// Check valid prefix for mobile
		$prefix = substr( $normalized, 0, 3 );
		return in_array( $prefix, self::MOBILE_PREFIXES, true );
	}

	/**
	 * Normalize phone number to standard format
	 *
	 * @param string $phone Phone number (various formats)
	 * @return string|null Normalized phone (0XXXXXXXXX) or null if invalid
	 */
	public static function normalize( $phone ) {
		// Remove all non-digits
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		if ( empty( $phone ) ) {
			return null;
		}

		// Handle international format (+84)
		if ( Str::starts_with( $phone, '84' ) ) {
			$phone = '0' . substr( $phone, 2 );
		}

		// Must start with 0
		if ( ! Str::starts_with( $phone, '0' ) ) {
			return null;
		}

		// Limit to 10-11 digits
		if ( strlen( $phone ) < 10 || strlen( $phone ) > 11 ) {
			return null;
		}

		return $phone;
	}

	/**
	 * Format phone number for display
	 *
	 * @param string $phone     Phone number
	 * @param string $separator Separator character
	 * @return string
	 */
	public static function format( $phone, $separator = ' ' ) {
		$normalized = self::normalize( $phone );

		if ( empty( $normalized ) ) {
			return $phone;
		}

		// Format as: 0XXX XXX XXXX or 0XXX XXX XX XXX
		if ( 10 === strlen( $normalized ) ) {
			return substr( $normalized, 0, 4 ) . $separator
				. substr( $normalized, 4, 3 ) . $separator
				. substr( $normalized, 7 );
		}

		// 11 digits
		return substr( $normalized, 0, 4 ) . $separator
			. substr( $normalized, 4, 3 ) . $separator
			. substr( $normalized, 7, 2 ) . $separator
			. substr( $normalized, 9 );
	}

	/**
	 * Mask phone number for privacy (show first 3 and last 2 digits)
	 *
	 * @param string $phone Phone number
	 * @return string
	 */
	public static function mask( $phone ) {
		$normalized = self::normalize( $phone );

		if ( empty( $normalized ) ) {
			return '***';
		}

		return substr( $normalized, 0, 4 ) . '***' . substr( $normalized, -2 );
	}

	/**
	 * Get carrier from phone number
	 *
	 * @param string $phone Phone number
	 * @return string|null Carrier name or null
	 */
	public static function get_carrier( $phone ) {
		$normalized = self::normalize( $phone );

		if ( empty( $normalized ) ) {
			return null;
		}

		$prefix = substr( $normalized, 0, 3 );

		$carriers = array(
			'viettel'      => array( '032', '033', '034', '035', '036', '037', '038', '039' ),
			'mobifone'     => array( '070', '079', '077', '076', '078' ),
			'vinaphone'    => array( '083', '084', '085', '081', '082' ),
			'vietnamobile' => array( '056', '058' ),
			'gmobile'      => array( '059' ),
		);

		foreach ( $carriers as $carrier => $prefixes ) {
			if ( in_array( $prefix, $prefixes, true ) ) {
				return $carrier;
			}
		}

		return 'other';
	}

	/**
	 * Convert to international format (+84)
	 *
	 * @param string $phone Phone number
	 * @return string
	 */
	public static function to_international( $phone ) {
		$normalized = self::normalize( $phone );

		if ( empty( $normalized ) ) {
			return $phone;
		}

		// Remove leading 0 and add +84
		return '+84' . substr( $normalized, 1 );
	}

	/**
	 * Check if two phone numbers are the same
	 *
	 * @param string $phone1 First phone number
	 * @param string $phone2 Second phone number
	 * @return bool
	 */
	public static function equals( $phone1, $phone2 ) {
		$norm1 = self::normalize( $phone1 );
		$norm2 = self::normalize( $phone2 );

		return ! empty( $norm1 ) && $norm1 === $norm2;
	}

	/**
	 * Generate random Vietnamese phone number (for testing)
	 *
	 * @return string
	 */
	public static function random() {
		$prefix = self::MOBILE_PREFIXES[ array_rand( self::MOBILE_PREFIXES ) ];
		$suffix = str_pad( wp_rand( 0, 9999999 ), 7, '0', STR_PAD_LEFT );

		return '0' . substr( $prefix, 1 ) . $suffix;
	}

	/**
	 * Hash phone number for privacy-preserving lookups
	 *
	 * @param string $phone Phone number
	 * @return string|null SHA256 hash or null
	 */
	public static function hash( $phone ) {
		$normalized = self::normalize( $phone );

		if ( empty( $normalized ) ) {
			return null;
		}

		return hash( 'sha256', $normalized );
	}
}
