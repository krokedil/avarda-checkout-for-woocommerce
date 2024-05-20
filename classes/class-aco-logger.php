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
	 * @param string $level The log level. Default 'info' from WC_Log_Levels::INFO.
	 *
	 * @return void
	 */
	public static function log( $data, $level = null ) {
		$avarda_settings = get_option( 'woocommerce_aco_settings' );

		$status_code = isset( $data['response']['code'] ) ? $data['response']['code'] : '';
		$level       = self::get_log_level( $status_code, $level );

		if ( 'yes' === $avarda_settings['debug'] ) {
			$message = self::format_data( $data );

			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			self::$log->add( 'Avarda_Checkout', wp_json_encode( $message ), $level );
		}

		if ( isset( $data['response']['code'] ) && ( $data['response']['code'] < 200 || $data['response']['code'] > 299 ) ) {
			self::log_to_db( $data );
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
	 * @param string $request_url The request url.
	 * @param array  $request_args The request args.
	 * @param array  $response The response.
	 * @param string $code The status code.
	 * @return array
	 */
	public static function format_log( $checkout_id, $method, $title, $request_url, $request_args, $response, $code ) {
		// Unset the snippet to prevent issues in the response.
		// Add logic to remove any HTML snippets from the response.

		// Unset the snippet to prevent issues in the request body.
		// Add logic to remove any HTML snippets from the request body.

		return array(
			'id'             => $checkout_id,
			'type'           => $method,
			'title'          => $title,
			'request_url'    => $request_url,
			'request'        => $request_args,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ),
			'plugin_version' => AVARDA_CHECKOUT_VERSION,
			'stack'          => self::get_stack(),
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Data is not used for display.
		$stack      = array();
		foreach ( $debug_data as $data ) {
			$extra_data = '';
			if ( ! in_array( $data['function'], array( 'get_stack', 'format_log' ), true ) ) {
				if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ), true ) ) {
					if ( isset( $data['object'] ) ) {
						$priority   = $data['object']->current_priority();
						$name       = key( $data['object']->current() );
						$extra_data = $name . ' : ' . $priority;
					}
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}
		return $stack;
	}

	/**
	 * Logs an event in the WP DB.
	 *
	 * @param array $data The data to be logged.
	 */
	public static function log_to_db( $data ) {
		$logs = get_option( 'krokedil_debuglog_aco', array() );

		if ( ! empty( $logs ) ) {
			$logs = json_decode( $logs );
		}

		$logs   = array_slice( $logs, - 14 );
		$logs[] = $data;
		$logs   = wp_json_encode( $logs );
		update_option( 'krokedil_debuglog_aco', $logs );
	}

	/**
	 * Get the log level.
	 *
	 * @param string      $status_code The status code.
	 * @param string|null $level The log level.
	 *
	 * @return string
	 */
	public static function get_log_level( $status_code, $level ) {
		if ( ! empty( $level ) ) {
			return $level;
		}

		// Get the first number from the status code.
		$first_digit = substr( $status_code, 0, 1 );

		switch ( $first_digit ) {
			case '2':
			case '3':
				$level = WC_Log_Levels::INFO;
				break;
			case '4':
			case '5':
				$level = WC_Log_Levels::ERROR;
				break;
			default:
				$level = WC_Log_Levels::INFO;
				break;
		}

		return $level;
	}
}
