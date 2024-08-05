<?php
/**
 * Shipping session model class for the shipping broker api.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package Avarda_Checkout/Classes/API/Models/Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_Shipping_Session_Model
 */
class ACO_Shipping_Session_Model extends ACO_Shipping_Response_Model {
	/**
	 * Expiration time for the shipping session.
	 *
	 * @var string
	 */
	public $expiresAt;

	/**
	 * The selected shipping option model.
	 *
	 * @var ACO_Shipping_Option_Model
	 */
	public $selectedShippingOption;

	/**
	 * The modules for the shipping session.
	 *
	 * @var string
	 */
	public $modules;

	/**
	 * Create a instance from a shipping rate.
	 *
	 * @param WC_Shipping_Rate[] $shipping_rates The shipping rate.
	 * @param string             $chosen_shipping_method The chosen shipping method.
	 * @param string             $purchase_id The purchase id.
	 *
	 * @return ACO_Shipping_Session_Model
	 */
	public static function from_shipping_rates( $shipping_rates, $chosen_shipping_method, $purchase_id ) {
		$session = new self();

		$chosen_shipping_method = empty( $chosen_shipping_method ) ? array_key_first( $shipping_rates ) : $chosen_shipping_method;
		$selected               = $shipping_rates[ $chosen_shipping_method ];

		$session->id                     = $purchase_id;
		$session->expiresAt              = gmdate( 'Y-m-d\TH:i:s\Z', time() + 60 * 60 ); // 1 hour from now same as Avarda.
		$session->status                 = 'ACTIVE';
		$session->selectedShippingOption = ACO_Shipping_Option_Model::from_shipping_rate( $selected );

		$options = array();
		foreach ( $shipping_rates as $rate ) {
			$options[] = ACO_Shipping_Option_Model::from_shipping_rate( $rate );
		}

		$session->modules = wp_json_encode(
			array(
				'options'         => $options,
				'selected_option' => $selected->get_id(),
			)
		);

		return $session;
	}
}