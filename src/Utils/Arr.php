<?php
/**
 * Array Utilities
 *
 * @package VQCheckout\Utils
 */

namespace VQCheckout\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Array helper functions
 */
class Arr {
	/**
	 * Get value from array using dot notation
	 *
	 * @param array  $array   Array to search
	 * @param string $key     Dot notation key (e.g., 'billing.address.city')
	 * @param mixed  $default Default value if not found
	 * @return mixed
	 */
	public static function get( $array, $key, $default = null ) {
		if ( ! is_array( $array ) ) {
			return $default;
		}

		if ( isset( $array[ $key ] ) ) {
			return $array[ $key ];
		}

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $array ) || ! array_key_exists( $segment, $array ) ) {
				return $default;
			}
			$array = $array[ $segment ];
		}

		return $array;
	}

	/**
	 * Set value in array using dot notation
	 *
	 * @param array  $array Array to modify
	 * @param string $key   Dot notation key
	 * @param mixed  $value Value to set
	 * @return array
	 */
	public static function set( &$array, $key, $value ) {
		if ( ! is_array( $array ) ) {
			$array = array();
		}

		$keys = explode( '.', $key );
		$current = &$array;

		foreach ( $keys as $k ) {
			if ( ! isset( $current[ $k ] ) || ! is_array( $current[ $k ] ) ) {
				$current[ $k ] = array();
			}
			$current = &$current[ $k ];
		}

		$current = $value;

		return $array;
	}

	/**
	 * Check if key exists using dot notation
	 *
	 * @param array  $array Array to check
	 * @param string $key   Dot notation key
	 * @return bool
	 */
	public static function has( $array, $key ) {
		if ( ! is_array( $array ) ) {
			return false;
		}

		if ( isset( $array[ $key ] ) ) {
			return true;
		}

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $array ) || ! array_key_exists( $segment, $array ) ) {
				return false;
			}
			$array = $array[ $segment ];
		}

		return true;
	}

	/**
	 * Pluck values from array of arrays/objects
	 *
	 * @param array  $array Array to pluck from
	 * @param string $key   Key to pluck
	 * @return array
	 */
	public static function pluck( $array, $key ) {
		return array_map(
			function( $item ) use ( $key ) {
				if ( is_array( $item ) ) {
					return self::get( $item, $key );
				}
				if ( is_object( $item ) ) {
					return $item->{$key} ?? null;
				}
				return null;
			},
			$array
		);
	}

	/**
	 * Filter array by keys
	 *
	 * @param array $array Array to filter
	 * @param array $keys  Keys to keep
	 * @return array
	 */
	public static function only( $array, $keys ) {
		return array_intersect_key( $array, array_flip( (array) $keys ) );
	}

	/**
	 * Remove keys from array
	 *
	 * @param array $array Array to filter
	 * @param array $keys  Keys to remove
	 * @return array
	 */
	public static function except( $array, $keys ) {
		return array_diff_key( $array, array_flip( (array) $keys ) );
	}

	/**
	 * Flatten multi-dimensional array
	 *
	 * @param array $array Array to flatten
	 * @param int   $depth Max depth (0 = infinite)
	 * @return array
	 */
	public static function flatten( $array, $depth = 0 ) {
		$result = array();

		foreach ( $array as $item ) {
			if ( ! is_array( $item ) ) {
				$result[] = $item;
			} elseif ( $depth === 1 ) {
				$result = array_merge( $result, array_values( $item ) );
			} else {
				$result = array_merge( $result, self::flatten( $item, $depth - 1 ) );
			}
		}

		return $result;
	}

	/**
	 * Wrap value in array if not already an array
	 *
	 * @param mixed $value Value to wrap
	 * @return array
	 */
	public static function wrap( $value ) {
		if ( is_null( $value ) ) {
			return array();
		}

		return is_array( $value ) ? $value : array( $value );
	}

	/**
	 * Group array by key
	 *
	 * @param array  $array Array to group
	 * @param string $key   Key to group by
	 * @return array
	 */
	public static function group_by( $array, $key ) {
		$result = array();

		foreach ( $array as $item ) {
			$group_key = self::get( $item, $key );
			if ( ! isset( $result[ $group_key ] ) ) {
				$result[ $group_key ] = array();
			}
			$result[ $group_key ][] = $item;
		}

		return $result;
	}

	/**
	 * Sort array by key
	 *
	 * @param array  $array Array to sort
	 * @param string $key   Key to sort by
	 * @param string $order ASC or DESC
	 * @return array
	 */
	public static function sort_by( $array, $key, $order = 'ASC' ) {
		usort(
			$array,
			function( $a, $b ) use ( $key, $order ) {
				$val_a = self::get( $a, $key );
				$val_b = self::get( $b, $key );

				if ( $val_a === $val_b ) {
					return 0;
				}

				if ( 'DESC' === strtoupper( $order ) ) {
					return $val_a < $val_b ? 1 : -1;
				}

				return $val_a < $val_b ? -1 : 1;
			}
		);

		return $array;
	}

	/**
	 * Check if array is associative
	 *
	 * @param array $array Array to check
	 * @return bool
	 */
	public static function is_assoc( $array ) {
		if ( ! is_array( $array ) || empty( $array ) ) {
			return false;
		}

		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	/**
	 * Get first element of array
	 *
	 * @param array $array Array
	 * @return mixed|null
	 */
	public static function first( $array ) {
		if ( ! is_array( $array ) || empty( $array ) ) {
			return null;
		}

		return reset( $array );
	}

	/**
	 * Get last element of array
	 *
	 * @param array $array Array
	 * @return mixed|null
	 */
	public static function last( $array ) {
		if ( ! is_array( $array ) || empty( $array ) ) {
			return null;
		}

		return end( $array );
	}

	/**
	 * Chunk array preserving keys
	 *
	 * @param array $array Array to chunk
	 * @param int   $size  Chunk size
	 * @return array
	 */
	public static function chunk( $array, $size ) {
		return array_chunk( $array, $size, true );
	}
}
