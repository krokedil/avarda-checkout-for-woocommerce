<?php
/**
 * Shipping error model class for the shipping broker api.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package Avarda_Checkout/Classes/API/Models/Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_Shipping_Error_Model
 */
class ACO_Shipping_Error_Model extends ACO_Shipping_Response_Model {
	/**
	 * The details of the error.
	 *
	 * @var string
	 */
	public $details = 'Unknown error';

	/**
	 * Instance of the error.
	 *
	 * @var string
	 */
	public $instance = '';

	/**
	 * Additional props of the error.
	 *
	 * @var array
	 */
	public $additionalProps = array();

	/**
	 * Create a error object from a WP_Error.
	 *
	 * @param WP_Error $error The error object.
	 *
	 * @return ACO_Shipping_Error_Model
	 */
	public static function from_wp_error( $error ) {
		$error_model = new self();

		$error_model->details  = $error->get_error_message();
		$error_model->instance = '';

		return $error_model;
	}
}
