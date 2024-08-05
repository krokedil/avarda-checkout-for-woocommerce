<?php
/**
 * Base model class for the responses shipping models for the shipping broker api.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 *
 * @package Avarda_Checkout/Classes/API/Models/Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_Shipping_Response_Model
 */
abstract class ACO_Shipping_Response_Model {
	/**
	 * The ID of the shipping session.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * The status of the shipping session.
	 *
	 * @var string
	 */
	public $status;
}
