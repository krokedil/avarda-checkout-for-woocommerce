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
		return array(
			'Authorization' => $this->calculate_auth(),
			'Content-Type'  => $this->content_type,
		);
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
		$this->payer_settings = get_option( 'woocommerce_aco_settings' );
		$this->client_id      = $this->payer_settings['agent_id'];
		$this->client_secret  = $this->payer_settings['api_key'];
		$this->testmode       = $this->payer_settings['testmode'];
		$this->content_type   = ( $this->auth ) ? 'application/json\r\n' : 'application/json';
		$this->base_url       = ( 'yes' === $this->testmode ) ? AVARDA_CHECKOUT_TEST_ENV : AVARDA_CHECKOUT_LIVE_ENV;
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
		// Check the status code.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = ' ';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true )['errors'] ) {
				foreach ( json_decode( $response['body'], true )['errors'] as $error ) {
					$error_message = $error_message . '<br>' . $error['message'];
				}
			}
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $response['response']['message'] . $error_message, $data );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Calculates the auth needed for the different requests.
	 *
	 * @return string
	 */
	public function calculate_auth() {
		if ( $this->auth ) {
			return '';
		} else {
			$token = aco_maybe_create_token();
			if ( is_wp_error( $token ) ) {
				wp_die( esc_html( $token ) );
			}
			return 'Bearer ' . $token;
		}
	}
}
