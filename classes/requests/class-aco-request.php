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
	 * The request environment.
	 *
	 * @var $environment
	 */
	public $environment;

	/**
	 * If the request is authenticated.
	 *
	 * @var boolean $auth
	 */
	public $auth = false;

	/**
	 * If the request if using international credentials.
	 *
	 * @var boolean $international
	 */
	public $international = false;

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
	 * Sets the environment.
	 *
	 * @return void
	 */
	public function set_environment() {
		$live_environment = 'https://checkout-api.avarda.com';
		$test_environment = 'https://stage.checkout-api.avarda.com';
		$avarda_settings  = get_option( 'woocommerce_aco_settings' );

		if ( 'no' === $avarda_settings['testmode'] ) {
			$this->environment = $live_environment;
		} else {
			$this->environment = $test_environment;
		}
	}

	/**
	 * Sets the environment.
	 *
	 * @return void
	 */
	public function set_environment_variables() {
		$this->avarda_settings = get_option( 'woocommerce_aco_settings' );
		$this->testmode        = $this->avarda_settings['testmode'];

		// Get the client id and secret based on the country.
		$credential_country = $this->get_credential_country();
		$credentials        = $this->get_credentials( $credential_country );

		// Set the client id and secret and apply filters to allow for customization.
		$this->client_id     = apply_filters( 'aco_client_id', $credentials['client_id'] ?? '', $this->international );
		$this->client_secret = apply_filters( 'aco_client_secret', $credentials['client_secret'] ?? '', $this->international );

		$this->base_url = ( 'yes' === $this->testmode ) ? AVARDA_CHECKOUT_TEST_ENV : AVARDA_CHECKOUT_LIVE_ENV;
	}

	/**
	 * Get the country to use when getting the credentials from the settings.
	 *
	 * @param WC_Order|int|null $order The WooCommerce order, order id or null if we are using the cart.
	 *
	 * @return string The country code.
	 */
	public function get_credential_country( $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}

		$currency         = $order ? $order->get_currency() : get_woocommerce_currency();
		$currency_country = array(
			'SEK' => 'se',
			'NOK' => 'no',
			'DKK' => 'dk',
			'EUR' => 'fi',
		);

		return $currency_country[ $currency ] ?? ''; // Default to se if the currency is not a supported currency.
	}

	/**
	 * Get the credentials to use for the country.
	 *
	 * @param string $country The country code.
	 *
	 * @return array The credentials.
	 */
	public function get_credentials( $country ) {
		$client_id     = '';
		$client_secret = '';

		if ( isset( $this->avarda_settings[ 'merchant_id_' . $country ] ) && isset( $this->avarda_settings[ 'api_key_' . $country ] ) ) {
			$client_id     = $this->avarda_settings[ 'merchant_id_' . $country ];
			$client_secret = $this->avarda_settings[ 'api_key_' . $country ];
		}

		// If the client id or secret is still empty, use the international credentials.
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$client_id           = $this->avarda_settings['merchant_id_int'] ?? null;
			$client_secret       = $this->avarda_settings['api_key_int'] ?? null;
			$this->international = true;
		}

		return apply_filters(
			'aco_credentials',
			array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			),
			$country,
			$this->international,
			$this->testmode
		);
	}

	/**
	 * Checks response for any error.
	 *
	 * @param array  $response The response.
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
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			if ( null !== $response['response'] ) {
				$aco_error_code    = isset( $response['response']['code'] ) ? $response['response']['code'] . ' ' : '';
				$aco_error_message = isset( $response['response']['message'] ) ? $response['response']['message'] . ' ' : '';
				$error_message     = $aco_error_code . $aco_error_message;
			}

			if ( isset( $response['body'] ) && is_string( $response['body'] ) ) {
				$error_message = $response['body'];
			}

			if ( null !== json_decode( $response['body'], true ) ) {
				$errors = json_decode( $response['body'], true );
				foreach ( $errors as $error => $aco_error_messages ) {
					// Ensure the error message is an array so we can loop through it.
					if ( ! is_array( $aco_error_messages ) ) {
						$aco_error_messages = array( $aco_error_messages );
					}

					foreach ( $aco_error_messages as $aco_error_message ) {
						if ( is_array( $aco_error_message ) ) {
							foreach ( $aco_error_message as $aco_err_msg ) {
								$error_message .= $aco_err_msg . ' ';
							}
						} else {
							$error_message .= $aco_error_message . ' ';
						}
					}
				}
			}
			if ( empty( $error_message ) ) {
				// Translators: https request response code.
				$error_message = sprintf( __( 'Avarda request error. Request response code: %s', 'avarda-checkout-for-woocommerce' ), wp_remote_retrieve_response_code( $response ) );
			}
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $error_message, $data );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
