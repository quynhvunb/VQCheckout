<?php
/**
 * String Utilities
 *
 * @package VQCheckout\Utils
 */

namespace VQCheckout\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * String helper functions
 */
class Str {
	/**
	 * Convert string to slug
	 *
	 * @param string $str String to slugify
	 * @return string
	 */
	public static function slug( $str ) {
		return sanitize_title( $str );
	}

	/**
	 * Check if string starts with substring
	 *
	 * @param string $haystack String to search in
	 * @param string $needle   String to search for
	 * @return bool
	 */
	public static function starts_with( $haystack, $needle ) {
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}

	/**
	 * Check if string ends with substring
	 *
	 * @param string $haystack String to search in
	 * @param string $needle   String to search for
	 * @return bool
	 */
	public static function ends_with( $haystack, $needle ) {
		$length = strlen( $needle );
		if ( 0 === $length ) {
			return true;
		}

		return substr( $haystack, -$length ) === $needle;
	}

	/**
	 * Check if string contains substring
	 *
	 * @param string $haystack String to search in
	 * @param string $needle   String to search for
	 * @return bool
	 */
	public static function contains( $haystack, $needle ) {
		return false !== strpos( $haystack, $needle );
	}

	/**
	 * Limit string length
	 *
	 * @param string $str    String to limit
	 * @param int    $limit  Character limit
	 * @param string $append String to append if limited
	 * @return string
	 */
	public static function limit( $str, $limit = 100, $append = '...' ) {
		if ( mb_strlen( $str ) <= $limit ) {
			return $str;
		}

		return mb_substr( $str, 0, $limit ) . $append;
	}

	/**
	 * Convert string to camelCase
	 *
	 * @param string $str String to convert
	 * @return string
	 */
	public static function camel( $str ) {
		$str = str_replace( array( '-', '_' ), ' ', $str );
		$str = ucwords( $str );
		$str = str_replace( ' ', '', $str );
		return lcfirst( $str );
	}

	/**
	 * Convert string to snake_case
	 *
	 * @param string $str String to convert
	 * @return string
	 */
	public static function snake( $str ) {
		$str = preg_replace( '/([a-z])([A-Z])/', '$1_$2', $str );
		$str = preg_replace( '/[^a-zA-Z0-9]+/', '_', $str );
		return strtolower( $str );
	}

	/**
	 * Convert string to Title Case
	 *
	 * @param string $str String to convert
	 * @return string
	 */
	public static function title( $str ) {
		return ucwords( strtolower( $str ) );
	}

	/**
	 * Remove Vietnamese accents
	 *
	 * @param string $str String with Vietnamese accents
	 * @return string
	 */
	public static function remove_accents( $str ) {
		$accents = array(
			'à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ằ', 'ắ', 'ẳ', 'ẵ', 'ặ', 'â', 'ầ', 'ấ', 'ẩ', 'ẫ', 'ậ',
			'đ',
			'è', 'é', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ề', 'ế', 'ể', 'ễ', 'ệ',
			'ì', 'í', 'ỉ', 'ĩ', 'ị',
			'ò', 'ó', 'ỏ', 'õ', 'ọ', 'ô', 'ồ', 'ố', 'ổ', 'ỗ', 'ộ', 'ơ', 'ờ', 'ớ', 'ở', 'ỡ', 'ợ',
			'ù', 'ú', 'ủ', 'ũ', 'ụ', 'ư', 'ừ', 'ứ', 'ử', 'ữ', 'ự',
			'ỳ', 'ý', 'ỷ', 'ỹ', 'ỵ',
		);

		$no_accents = array(
			'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
			'd',
			'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
			'i', 'i', 'i', 'i', 'i',
			'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
			'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
			'y', 'y', 'y', 'y', 'y',
		);

		$str = str_replace( $accents, $no_accents, $str );
		$str = str_replace(
			array_map( 'strtoupper', $accents ),
			array_map( 'strtoupper', $no_accents ),
			$str
		);

		return $str;
	}

	/**
	 * Generate random string
	 *
	 * @param int $length Length of string
	 * @return string
	 */
	public static function random( $length = 16 ) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$string = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$string .= $characters[ wp_rand( 0, strlen( $characters ) - 1 ) ];
		}

		return $string;
	}

	/**
	 * Replace first occurrence of substring
	 *
	 * @param string $search  String to search for
	 * @param string $replace Replacement string
	 * @param string $subject String to search in
	 * @return string
	 */
	public static function replace_first( $search, $replace, $subject ) {
		$pos = strpos( $subject, $search );

		if ( false === $pos ) {
			return $subject;
		}

		return substr_replace( $subject, $replace, $pos, strlen( $search ) );
	}

	/**
	 * Replace last occurrence of substring
	 *
	 * @param string $search  String to search for
	 * @param string $replace Replacement string
	 * @param string $subject String to search in
	 * @return string
	 */
	public static function replace_last( $search, $replace, $subject ) {
		$pos = strrpos( $subject, $search );

		if ( false === $pos ) {
			return $subject;
		}

		return substr_replace( $subject, $replace, $pos, strlen( $search ) );
	}

	/**
	 * Mask string (for privacy)
	 *
	 * @param string $str    String to mask
	 * @param int    $start  Characters to show at start
	 * @param int    $end    Characters to show at end
	 * @param string $mask   Mask character
	 * @return string
	 */
	public static function mask( $str, $start = 2, $end = 2, $mask = '*' ) {
		$length = mb_strlen( $str );

		if ( $length <= $start + $end ) {
			return str_repeat( $mask, $length );
		}

		$mask_length = $length - $start - $end;

		return mb_substr( $str, 0, $start )
			. str_repeat( $mask, $mask_length )
			. mb_substr( $str, -$end );
	}

	/**
	 * Normalize whitespace
	 *
	 * @param string $str String to normalize
	 * @return string
	 */
	public static function normalize_whitespace( $str ) {
		return preg_replace( '/\s+/', ' ', trim( $str ) );
	}

	/**
	 * Convert currency symbol
	 *
	 * @param string $str String containing ₫ symbol
	 * @return string
	 */
	public static function vnd_to_text( $str ) {
		return str_replace( '₫', 'VNĐ', $str );
	}

	/**
	 * Parse query string into array
	 *
	 * @param string $query Query string
	 * @return array
	 */
	public static function parse_query( $query ) {
		parse_str( $query, $result );
		return $result;
	}
}
