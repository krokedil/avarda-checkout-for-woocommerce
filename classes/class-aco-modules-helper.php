<?php
/**
 * Class with helper methods to handle modules in the Avarda api response
 *
 * @package Avarda_Checkout/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * ACO_Modules_Helper class.
 */
class ACO_Modules_Helper {
	/**
	 * Return the shipping module data from the Avarda order.
	 *
	 * @param array  $avarda_order The Avarda order.
	 * @param string ...$keys The keys to check against to get the correct module.
	 *
	 * @return array|false
	 */
	public static function get_module( $avarda_order, ...$keys ) {
		$modules = $avarda_order['Modules'];

		// If the shipping module is not set, return an empty array.
		if ( empty( $modules ) ) {
			return false;
		}

		foreach ( $modules as $module ) {
			// Get the correct module by testing if the keys are set in the module.
			if ( self::is_module( $module, ...$keys ) ) {
				return $module;
			}
		}

		return false;
	}

	/**
	 * Test if the module has the correct keys.
	 *
	 * @param array  $module The module to test.
	 * @param string ...$keys The keys to test against.
	 *
	 * @return bool
	 */
	public static function is_module( $module, ...$keys ) {
		foreach ( $keys as $key ) {
			if ( ! isset( $module[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}
}
