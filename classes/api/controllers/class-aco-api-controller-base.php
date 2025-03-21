<?php
/**
 * Base controller class for the Avarda Checkout API.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package Avarda_Checkout/Classes/API/Controllers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_API_Controller
 */
abstract class ACO_API_Controller_Base {
	/**
	 * The namespace of the controller.
	 *
	 * @var string
	 */
	protected $namespace = 'aco';

	/**
	 * The version of the controller.
	 *
	 * @var string
	 */
	protected $version = '';

	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Get the base path for the controller.
	 *
	 * @return string
	 */
	protected function get_base_path() {
		// Combine the version and path to create the base path, ensuring that the path doesn't start or end with a slash.
		return trim( "{$this->version}/{$this->path}", '/' );
	}

	/**
	 * Get the request path for a specific endpoint.
	 *
	 * @param string $endpoint The endpoint to get the path for.
	 *
	 * @return string
	 */
	protected function get_request_path( $endpoint ) {
		$base_path = $this->get_base_path();
		return trim( "{$base_path}/{$endpoint}", '/' );
	}

	/**
	 * Verify the request is valid.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool
	 */
	public function verify_request( $request ) {
		// Get the auth header.
		$auth_header = $request->get_header( 'Authorization' );

		// Check if the auth header is set.
		if ( empty( $auth_header ) ) {
			$error = new WP_Error( 401, __( 'Authorization header is missing.', 'avarda-checkout-for-woocommerce' ) );
			$this->send_response( $error, 401 );

			// Return false to ensure the request is stopped if the send_response doesn't stop it.
			return false;
		}

		// Get the bearer token from the auth header.
		$auth_header_parts = explode( ' ', $auth_header );
		if ( count( $auth_header_parts ) !== 2 ) {
			$error = new WP_Error( 401, __( 'Authorization header is invalid.', 'avarda-checkout-for-woocommerce' ) );
			$this->send_response( $error, 401 );

			// Return false to ensure the request is stopped if the send_response doesn't stop it.
			return false;
		}

		$type  = $auth_header_parts[0] ?? '';
		$value = $auth_header_parts[1] ?? '';

		// Check if the auth token is valid.
		if ( 'Bearer' !== $type || ACO_API_Registry::get_auth_token() !== $value ) {
			$error = new WP_Error( 401, __( 'Authorization header is invalid.', 'avarda-checkout-for-woocommerce' ) );
			$this->send_response( $error, 401 );

			// Return false to ensure the request is stopped if the send_response doesn't stop it.
			return false;
		}

		return true;
	}

	/**
	 * Send a response.
	 *
	 * @param object|array|null|WP_Error $response Response object.
	 * @param int                        $status_code Status code.
	 *
	 * @return void
	 */
	protected function send_response( $response, $status_code = 200 ) {
		// Check if the response is a WP_Error.
		if ( is_wp_error( $response ) ) {
			$this->send_error_response( $response );
		}

		wp_send_json( $response, $status_code );
	}

	/**
	 * Send a error response.
	 *
	 * @param WP_Error $wp_error The error object.
	 *
	 * @return void
	 */
	protected function send_error_response( $wp_error ) {
		$error = ACO_Shipping_Error_Model::from_wp_error( $wp_error );
		// Convert the additional props from an array to key value pairs for the response object.
		$additional_props = array();
		foreach ( $error->additionalProps as $key => $value ) {
			$additional_props[ $key ] = $value;
		}

		// Create the error response.
		$error_response = array(
			'error'    => $error->details,
			'instance' => $error->instance,
		);

		// Add the additional props to the error response.
		if ( ! empty( $additional_props ) ) {
			$error_response = array_merge( $error_response, $additional_props );
		}

		// Send the error response.
		wp_send_json( $error_response, $wp_error->get_error_code() );
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @return void
	 */
	abstract public function register_routes();
}
