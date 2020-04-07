<?php
/**
 * Main request class
 *
 * @package Avarda_Checkout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main request class
 */
class ACO_Request {
	/**
	 * The request enviroment.
	 *
	 * @var $enviroment
	 */
	public $enviroment;

	/**
	 * Class constructor.
	 */
	public function __construct( $auth = false ) {
		$this->auth = $auth;
		$this->set_environment_variables();
	}

	/**
	 * Returns headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		if ( $this->auth ) {
			return array(
				'Content-Type' => 'application/json',
			);
		} else {
			$token = aco_maybe_create_token();
			if ( is_wp_error( $token ) ) {
				wp_die( esc_html( $token ) );
			}
			return array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			);
		}
	}

	/**
	 * Sets the enviroment.
	 *
	 * @return void
	 */
	public function set_enviroment() {
		$live_enviroment = 'https://avdonl-p-checkout.avarda.org';
		$test_enviroment = 'https://avdonl-s-checkout.westeurope.cloudapp.azure.com';
		$avarda_settings = get_option( 'woocommerce_aco_settings' );

		if ( 'no' === $avarda_settings['testmode'] ) {
			$this->enviroment = $live_enviroment;
		} else {
			$this->enviroment = $test_enviroment;
		}
	}

	/**
	 * Sets the environment.
	 *
	 * @return void
	 */
	public function set_environment_variables() {
		$this->avarda_settings = get_option( 'woocommerce_aco_settings' );
		$this->client_id       = $this->avarda_settings['merchant_id'];
		$this->client_secret   = $this->avarda_settings['api_key'];
		$this->testmode        = $this->avarda_settings['testmode'];
		$this->base_url        = ( 'yes' === $this->testmode ) ? AVARDA_CHECKOUT_TEST_ENV : AVARDA_CHECKOUT_LIVE_ENV;
	}


	/**
	 * Checks response for any error.
	 *
	 * @param object $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 * @return object|array
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		// Check if response is a WP_Error, and return it back if it is.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check the status code, if its not between 200 and 299 then its an error.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true ) ) {
				$errors = json_decode( $response['body'], true );
				foreach ( $errors['error_messages'] as $error ) {
					$error_message = $error_message . ' ' . $error;
				}
			}
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $response['body'] . $error_message, $data );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
