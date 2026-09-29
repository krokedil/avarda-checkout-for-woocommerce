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
	 * The transport id for a completed shipping session.
	 *
	 * @var string
	 */
	public $transportId;

	/**
	 * Get a fallback shipping session in case of errors.
	 *
	 * @param string $purchase_id The purchase id.
	 *
	 * @return ACO_Shipping_Session_Model
	 */
	public static function get_fallback_shipping_session( $purchase_id ) {
		$session                         = new self();
		$no_shipping                     = ACO_Shipping_Option_Model::no_shipping();
		$session->id                     = $purchase_id;
		$session->expiresAt              = gmdate( 'Y-m-d\TH:i:s\Z', time() + 60 * 60 ); // 1 hour from now same as Avarda.
		$session->status                 = 'ACTIVE';
		$session->selectedShippingOption = $no_shipping;
		$session->modules                = wp_json_encode(
			array(
				'options'         => array( $no_shipping ),
				'selected_option' => 'no_shipping',
			)
		);

		/**
		 * Filter the fallback shipping session to allow others to add or modify data as needed.
		 *
		 * @param ACO_Shipping_Session_Model $session The fallback shipping session.
		 * @param string                     $purchase_id The purchase id for the Avarda order.
		 *
		 * @return ACO_Shipping_Session_Model
		 */
		return apply_filters( 'aco_fallback_shipping_session', $session, $purchase_id );
	}

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
		$session            = new self();
		$default_rate       = is_array( $shipping_rates ) && ! empty( $shipping_rates ) ? array_key_first( $shipping_rates ) : null;
		$selected           = $shipping_rates[ $chosen_shipping_method ] ?? $shipping_rates[ $default_rate ] ?? null;
		$session->id        = $purchase_id;
		$session->expiresAt = gmdate( 'Y-m-d\TH:i:s\Z', time() + 60 * 60 ); // 1 hour from now same as Avarda.
		$session->status    = 'ACTIVE';

		if ( empty( $shipping_rates ) ) {
			$no_shipping                     = ACO_Shipping_Option_Model::no_shipping();
			$session->selectedShippingOption = $no_shipping;

			$session->modules = wp_json_encode(
				array(
					'options'         => array( $no_shipping ),
					'selected_option' => 'no_shipping',
				)
			);

			return $session;
		}

		if ( ! empty( $selected ) ) {
			$session->selectedShippingOption = ACO_Shipping_Option_Model::from_shipping_rate( $selected );
		}

		$options = array();
		foreach ( $shipping_rates as $rate ) {
			// If the rate is null, then skip it.
			if ( is_null( $rate ) ) {
				continue;
			}

			$options[] = ACO_Shipping_Option_Model::from_shipping_rate( $rate );
		}

		$session->modules = wp_json_encode(
			array(
				'options'         => $options,
				'selected_option' => $selected ? $selected->get_id() : null,
			)
		);

		/**
		 * Filter the shipping session to allow others to add or modify data as needed.
		 *
		 * @param ACO_Shipping_Session_Model $session The shipping session for the cart.
		 * @param WC_Shipping_Rate[]         $shipping_rates The shipping rates for the cart.
		 * @param string                     $chosen_shipping_method The chosen shipping method for the cart.
		 * @param string                     $purchase_id The purchase id for the Avarda order.
		 */
		return apply_filters( 'aco_shipping_session', $session, $shipping_rates, $chosen_shipping_method, $purchase_id );
	}

	/**
	 * Create a instance for a completed session.
	 *
	 * @param string        $purchase_id The purchase id.
	 * @param WC_Order|null $order The order for the purchase, used for the transport id and the selected shipping option.
	 *
	 * @return ACO_Shipping_Session_Model
	 */
	public static function completed_session( $purchase_id, $order = null ) {
		$session            = new self();
		$session->id        = $purchase_id;
		$session->expiresAt = gmdate( 'Y-m-d\TH:i:s\Z', time() + 60 * 60 ); // 1 hour from now same as Avarda.
		$session->status    = 'COMPLETED';

		if ( ! $order instanceof WC_Order ) {
			return $session;
		}

		$session->transportId = (string) $order->get_order_number();

		$rate = self::get_shipping_rate_from_order( $order );
		if ( $rate ) {
			$session->selectedShippingOption = ACO_Shipping_Option_Model::from_shipping_rate( $rate );
			$session->modules                = wp_json_encode(
				array(
					'options'         => array( $session->selectedShippingOption ),
					'selected_option' => $rate->get_id(),
				)
			);
		}

		return $session;
	}

	/**
	 * Recreate the shipping rate the customer chose from the order's shipping line.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return WC_Shipping_Rate|null
	 */
	private static function get_shipping_rate_from_order( $order ) {
		$shipping_items = $order->get_items( 'shipping' );
		$item           = reset( $shipping_items );

		if ( ! $item instanceof WC_Order_Item_Shipping ) {
			return null;
		}

		// Orders placed before the rate id was stored only have the method and instance id.
		$rate_id = $item->get_meta( '_aco_shipping_rate_id' );
		if ( empty( $rate_id ) ) {
			$rate_id = $item->get_method_id() . ':' . $item->get_instance_id();
		}

		$taxes = $item->get_taxes();
		$rate  = new WC_Shipping_Rate( $rate_id, $item->get_name(), $item->get_total(), $taxes['total'] ?? array(), $item->get_method_id(), $item->get_instance_id() );

		// The shipping line keeps the rate's meta data, including the pickup points and the selected one.
		foreach ( $item->get_meta_data() as $meta ) {
			$rate->add_meta_data( $meta->key, $meta->value );
		}

		return $rate;
	}
}
