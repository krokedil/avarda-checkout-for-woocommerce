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
	 * The base url
	 *
	 * @var string the base url.
	 */
	public $base_url;

	/**
	 * Client id
	 *
	 * @var string $client_id
	 */
	public $client_id;

	/**
	 * Client secret
	 *
	 * @var string $client_secret
	 */
	public $client_secret;

	/**
	 * Enable Avarda Checkout testmode.
	 *
	 * @var string $testmode
	 */
	public $testmode;


	/**
	 * Plugin settings.
	 *
	 * @var array $avarda_settings
	 */
	public $avarda_settings;

	/**
	 * The request enviroment.
	 *
	 * @var $enviroment
	 */
	public $enviroment;

	/**
	 * Class constructor
	 *
	 * @param boolean $auth Auth.
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

		switch ( get_woocommerce_currency() ) {
			case 'SEK':
				$this->client_id     = $this->avarda_settings['merchant_id_se'];
				$this->client_secret = $this->avarda_settings['api_key_se'];
				break;
			case 'NOK':
				$this->client_id     = $this->avarda_settings['merchant_id_no'];
				$this->client_secret = $this->avarda_settings['api_key_no'];
				break;
			case 'DKK':
				$this->client_id     = $this->avarda_settings['merchant_id_dk'];
				$this->client_secret = $this->avarda_settings['api_key_dk'];
				break;
			case 'EUR':
				$this->client_id     = $this->avarda_settings['merchant_id_fi'];
				$this->client_secret = $this->avarda_settings['api_key_fi'];
				break;
			default:
				$this->client_id     = $this->avarda_settings['merchant_id_se'];
				$this->client_secret = $this->avarda_settings['api_key_se'];
				break;
		}
		$this->testmode = $this->avarda_settings['testmode'];
		$this->base_url = ( 'yes' === $this->testmode ) ? AVARDA_CHECKOUT_TEST_ENV : AVARDA_CHECKOUT_LIVE_ENV;
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
			if ( null !== $response['response'] ) {
				$aco_error_code    = isset( $response['response']['code'] ) ? $response['response']['code'] . ' ' : '';
				$aco_error_message = isset( $response['response']['message'] ) ? $response['response']['message'] . ' ' : '';
				$error_message     = $aco_error_code . $aco_error_message;
			}

			if ( null !== json_decode( $response['body'], true ) ) {
				$errors = json_decode( $response['body'], true );
				foreach ( $errors as $error => $aco_error_messages ) {
					foreach ( $aco_error_messages as $aco_error_message ) {
						$error_message .= $aco_error_message . ' ';
					}
				}
			}
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $error_message, $data );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
