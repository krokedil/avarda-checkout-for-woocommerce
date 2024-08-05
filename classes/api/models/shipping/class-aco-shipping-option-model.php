<?php
/**
 * Shipping option model class for the shipping broker api.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package Avarda_Checkout/Classes/API/Models/Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_Shipping_Option_Model
 */
class ACO_Shipping_Option_Model {
	/**
	 * The shipping method.
	 *
	 * @var string
	 */
	public $shippingMethod;

	/**
	 * The delivery type of the shipping option.
	 *
	 * @var string
	 */
	public $deliveryType;

	/**
	 * The carrier for the shipping option.
	 *
	 * @var string
	 */
	public $carrier;

	/**
	 * The shipping product for the shipping option.
	 *
	 * @var string
	 */
	public $shippingProduct;

	/**
	 * The price for the shipping option, including tax.
	 *
	 * @var float
	 */
	public $price;

	/**
	 * The currency for the price.
	 *
	 * @var string
	 */
	public $currency;

	/**
	 * Instantiate a shipping option model from a shipping rate.
	 *
	 * @param WC_Shipping_Rate $shipping_rate The shipping rate.
	 *
	 * @return ACO_Shipping_Option_Model
	 */
	public static function from_shipping_rate( $shipping_rate ) {
		$option = new self();

		$option->shippingMethod  = $shipping_rate->get_id();
		$option->deliveryType    = 'mailbox';
		$option->carrier         = 'PostNord';
		$option->shippingProduct = $shipping_rate->get_label();
		$option->price           = floatval( $shipping_rate->get_cost() ) + array_sum( $shipping_rate->get_taxes() );
		$option->currency        = get_woocommerce_currency();

		return $option;
	}
}
