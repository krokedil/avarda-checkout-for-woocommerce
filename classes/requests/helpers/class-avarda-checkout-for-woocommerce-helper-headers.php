<?php
/**
 * Gets the headers for a request.
 *
 * @package Avarda_Checkout/Classes/Requests/Helpers
 */

/**
 * Class to generate headers for requests.
 */
class Avarda_Checkout_For_WooCommerce_Helper_Headers {
	/**
	 * The request headers.
	 *
	 * @var boolean|array
	 */
	protected static $headers = false;

	/**
	 * Returns the headers.
	 *
	 * @return array
	 */
	public static function get_headers() {
		self::maybe_set_headers();
		return self::$headers;
	}

	/**
	 * Maybe sets the headers.
	 *
	 * @return array
	 */
	protected static function maybe_set_headers() {
		if ( false === self::$headers ) {
			self::set_headers();
		}
		return self::$headers;
	}

	/**
	 * Sets the headers
	 *
	 * @return void
	 */
	protected static function set_headers() {
		$avarda_settings = get_option( 'woocommerce_aco_settings' );
		$merchant_id     = $avarda_settings['merchant_id'];
		$api_key         = $avarda_settings['api_key'];

		$auth_key = 'Basic ' . base64_encode( $merchant_id . ':' . $api_key );

		self::$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => $auth_key,
		);
	}
}
