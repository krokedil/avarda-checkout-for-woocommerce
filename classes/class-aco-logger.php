<?php
/**
 * Logger class file.
 *
 * @package Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Logger class.
 */
class ACO_Logger {
	/**
	 * Log message string
	 *
	 * @var $log
	 */
	public static $log;

	/**
	 * Logs an event.
	 *
	 * @param string $data The data string.
	 */
	public static function log( $data ) {
		$avarda_settings = get_option( 'woocommerce_aco_settings' );
		if ( 'yes' === $avarda_settings['debug'] ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'Avarda_Checkout', wp_json_encode( $message ) );
		}
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param string $data Json string of data.
	 * @return array
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) ) {
			$request_body            = json_decode( $data['request']['body'], true );
			$data['request']['body'] = $request_body;
		}

		return $data;
	}

	/**
	 * Formats the log data to be logged.
	 *
	 * @param string $checkout_id The gateway Checkout ID.
	 * @param string $method The method.
	 * @param string $title The title for the log.
	 * @param array  $request_args The request args.
	 * @param array  $response The response.
	 * @param string $code The status code.
	 * @return array
	 */
	public static function format_log( $checkout_id, $method, $title, $request_args, $response, $code ) {
		// Unset the snippet to prevent issues in the response.
		// Add logic to remove any HTML snippets from the response.

		// Unset the snippet to prevent issues in the request body.
		// Add logic to remove any HTML snippets from the request body.

		return array(
			'id'             => $checkout_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ),
			'plugin_version' => AVARDA_CHECKOUT_VERSION,
		);
	}
}
