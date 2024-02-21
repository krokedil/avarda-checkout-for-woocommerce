<?php
/**
 * Class for handling the integrated shipping supported by Avarda Checkout.
 *
 * @package Avarda_Checkout/Classes
 */

use Krokedil\Shipping\PickupPoint\OpenHours;
use Krokedil\Shipping\PickupPoint\PickupPoint;

defined( 'ABSPATH' ) || exit;

/**
 * ACO_Shipping class.
 */
class ACO_Shipping extends WC_Shipping_Method {
	/**
	 * Class constructor.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'aco_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->title              = __( 'Avarda Checkout Shipping', 'avarda-checkout-for-woocommerce' );
		$this->method_title       = __( 'Avarda Checkout Shipping', 'avarda-checkout-for-woocommerce' );
		$this->method_description = __( 'A dynamic shipping method, that will get its prices set by Avarda Checkout and the integration towards a Shipping Broker. When Avarda Checkout is the selected payment method, the other shipping methods for this region wont be shown to the customer.', 'avarda-checkout-for-woocommerce' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		add_filter( 'woocommerce_package_rates', array( $this, 'maybe_unset_other_rates' ), 10 );

		// If the method is initialized, it means the rate is in use and we should be using the integrated shipping method.
		add_filter( 'aco_integrated_shipping', '__return_true' );
	}

	/**
	 * If the shipping method is available or not for the current checkout.
	 *
	 * @param array $package The package.
	 */
	public function is_available( $package ) {
		// If Avarda is not the chosen payment method, or its not the first option in the payment method lists, return false.
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		reset( $available_gateways );

		if ( 'aco' === WC()->session->get( 'chosen_payment_method' ) || ( empty( WC()->session->get( 'chosen_payment_method' ) ) && 'aco' === key( $available_gateways ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Maybe unset other rates.
	 *
	 * @param array $rates The rates.
	 *
	 * @return array
	 */
	public function maybe_unset_other_rates( $rates ) {
		// If any rate is the aco_shipping method, unset all others.
		if ( isset( $rates[ $this->get_rate_id() ] ) ) {
			$rates = array( $this->get_rate_id() => $rates[ $this->get_rate_id() ] );
		}

		return $rates;
	}

	/**
	 * Register the shipping method with WooCommerce.
	 *
	 * @param array $methods WooCommerce shipping methods.
	 *
	 * @return array
	 */
	public static function register( $methods ) {
		$methods['aco_shipping'] = self::class;
		return $methods;
	}

	/**
	 * Calculate shipping.
	 *
	 * @param array $package Package data.
	 *
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		$avarda_order = ACO_WC()->session()->get_avarda_order();

		// If no Avarda order is set, return.
		if ( is_wp_error( $avarda_order ) ) {
			$label = $this->get_option( 'fallback_shipping_name' );
			$price = $this->get_option( 'fallback_shipping_price' );

			$rate = array(
				'id'    => $this->get_rate_id(),
				'label' => $label,
				'cost'  => $price,
				'taxes' => WC_Tax::calc_shipping_tax( $price, WC_Tax::get_shipping_tax_rates() ),
			);

			$this->add_rate( $rate );
			return;
		}

		// Get any shipping module that might be set.
		foreach ( $avarda_order['Modules'] ?? array() as $module ) {
			if ( isset( $module['ShippingModule'] ) && 1 === $module['ShippingModule'] ) {
				$this->process_nshift_shipping( $module );
				break;
			}

			if ( isset( $module['selected_shipping_option'] ) && 'ACTIVE' === $module['status'] ?? '' ) {
				$this->process_ingrid_shipping( $module );
				break;
			}
		}
	}

	/**
	 * Process the Ingrid shipping module.
	 *
	 * @param array $shipping_module The shipping module.
	 *
	 * @return void
	 */
	private function process_ingrid_shipping( $shipping_module ) {
		$selected_option = $shipping_module['selected_shipping_option'] ?? false;

		// If no shipping option is set, return.
		if ( ! $selected_option ) {
			return;
		}

		$selected_option_id = $shipping_module['id'];
		$label              = $shipping_module['result']['shipping']['product'] ?? $shipping_module['result']['category']['name'];
		$price_inc_vat      = $shipping_module['shipping_price'];
		$eta                = $shipping_module['result']['delivery_time']['customer_delivery_promise'] ?? null;

		if ( isset( $shipping_module['result']['shipping']['location'] ) ) {
			$selected_pickup_point = $this->get_ingrid_selected_pickup_point( $shipping_module['result']['shipping']['location'] );
		}

		$rate = $this->format_rate( $selected_option_id, $label, $price_inc_vat, false, $selected_pickup_point );

		$this->add_rate( $rate );
	}

	/**
	 * Get the selected pickup point from the ingrid shipping module.
	 *
	 * @param array $location The location of the pickup point.
	 *
	 * @return PickupPoint|false
	 */
	private function get_ingrid_selected_pickup_point( $location ) {
		$address = $location['address'] ?? array();

		$open_hours = array();
		foreach ( $location['operational_hours'] ?? array() as $day => $hours ) {
			// Split the hours into open and close.
			$hours = explode( '-', $hours );

			$open_hours[] = array(
				'day'   => $day,
				'open'  => $hours[0],
				'close' => $hours[1],
			);
		}

		$pickup_point = ( new PickupPoint() )
			->set_id( $location['external_id'] )
			->set_name( $location['name'] )
			->set_address( $address['address_lines'][0], $address['city'], $address['postal_code'], $address['country'] )
			->set_coordinates( $address['coordinates']['lat'], $address['coordinates']['lng'] );

		if ( count( $open_hours ) > 0 ) {
			$pickup_point->set_open_hours( $open_hours );
		}

		return $pickup_point;
	}

	/**
	 * Process the NShift shipping module.
	 *
	 * @param array $shipping_module The shipping module.
	 *
	 * @return void
	 */
	private function process_nshift_shipping( $shipping_module ) {
		// Get the selected shipping option.
		$selected_option = $shipping_module['SelectedShippingOption'] ?? false;

		// If no shipping option is set, return.
		if ( ! $selected_option ) {
			return;
		}

		// Set the parameters from the shipping module needed for the rate.
		$selected_option_id = $selected_option['SelectedOptionId'];
		$label              = $selected_option['SelectedOptionName'];
		$price_inc_vat      = $selected_option['Price'];
		$selected_agent_id  = $selected_option['SelectedAgentId'];

		// Set the pickup points and the selected pickup points if any exists.
		$pickup_points         = $this->get_nshift_pickup_points( $shipping_module, $selected_option_id );
		$selected_pickup_point = $this->get_nshift_selected_pickup_point( $pickup_points, $selected_agent_id );

		// Get the shipping price without VAT.
		$shipping_tax = WC_Tax::calc_shipping_tax( $price_inc_vat, WC_Tax::get_shipping_tax_rates() );
		$price        = $price_inc_vat - array_sum( $shipping_tax );

		$rate = $this->format_rate( $selected_option_id, $label, $price, $pickup_points, $selected_pickup_point );

		$this->add_rate( $rate );
	}

	/**
	 * Get the pickup points from the shipping module.
	 *
	 * @param array $shipping_module The shipping module.
	 * @param int   $selected_option_id The selected option id.
	 *
	 * @return array<PickupPoint>|false
	 */
	private function get_nshift_pickup_points( $shipping_module, $selected_option_id ) {
		$widget_data = $shipping_module['WidgetDataJson'] ?? array();
		$options     = $widget_data['options'] ?? false;

		// If no options are set, return.
		if ( ! $options ) {
			return false;
		}

		// Get the option with the same id as the selected option.
		$selected_option = false;
		foreach ( $options as $option ) {
			if ( $option['id'] === $selected_option_id ) {
				$selected_option = $option;
				break;
			}
		}

		// If no selected option is found, return.
		if ( ! $selected_option ) {
			return false;
		}

		// Get the pickup points from the selected option.
		$pickup_points = array();
		foreach ( $selected_option['agents'] ?? array() as $agent ) {

			$open_hours = array();

			foreach ( $agent['openingHourWeekdays'] ?? array() as $weekday ) {
				foreach ( $weekday['hours'] as $hours ) {
					$open_hours[] = array(
						'day'   => $weekday['description'],
						'open'  => $hours['start'],
						'close' => $hours['stop'],
					);
				}
			}

			foreach ( $agent['openingHourSpecialDays'] ?? array() as $special_day ) {
				foreach ( $special_day['hours'] as $hours ) {
					$open_hours[] = array(
						'day'   => $weekday['description'],
						'open'  => $hours['start'],
						'close' => $hours['stop'],
					);
				}
			}

			$pickup_point = ( new PickupPoint() )
			->set_id( $agent['id'] )
			->set_name( $agent['name'] )
			->set_address( $agent['address1'], $agent['city'], $agent['zipCode'], $agent['country'] )
			->set_coordinates( $agent['mapLatitude'], $agent['mapLongitude'] )
			->set_open_hours( $open_hours );

			$pickup_points[] = $pickup_point;
		}

		return count( $pickup_points ) > 0 ? $pickup_points : false;
	}

	/**
	 * Get the selected pickup point from the pickup points.
	 *
	 * @param array  $pickup_points The pickup points.
	 * @param string $selected_agent_id The selected pickup point agent id.
	 *
	 * @return PickupPoint|false
	 */
	private function get_nshift_selected_pickup_point( $pickup_points, $selected_agent_id ) {
		if ( ! $pickup_points ) {
			return false;
		}

		$selected_pickup_point = false;
		foreach ( $pickup_points as $pickup_point ) {
			if ( $pickup_point->get_id() === $selected_agent_id ) {
				$selected_pickup_point = $pickup_point;
				break;
			}
		}

		return $selected_pickup_point;
	}

	/**
	 * Format a rate from the parameters given by the shipping module from Avarda.
	 *
	 * @param string                   $id The id of the rate.
	 * @param string                   $label The label of the rate.
	 * @param float                    $price_inc_vat The price of the rate including VAT.
	 * @param array<PickupPoint>|false $pickup_points The pickup points if any.
	 * @param PickupPoint|false        $selected_pickup_point The selected pickup point if any.
	 * @param string                   $description The description of the rate. Default is null. If null, the label will be used.
	 *
	 * @return array
	 */
	private function format_rate( $id, $label, $price_inc_vat, $pickup_points = false, $selected_pickup_point = false, $description = null ) {
		$shipping_tax = WC_Tax::calc_shipping_tax( $price_inc_vat, WC_Tax::get_shipping_tax_rates() );
		$price        = $price_inc_vat - array_sum( $shipping_tax );

		$rate = array(
			'id'          => $this->get_rate_id(),
			'label'       => $label,
			'description' => $description ?? $label,
			'cost'        => $price,
			'taxes'       => $shipping_tax,
			'calc_tax'    => 'per_order',
			'meta_data'   => array(
				'avarda_shipping_option_id' => $id,
				'avarda_shipping_option'    => $label,
			),
		);

		// If pickup points exists, add them to the rate.
		if ( $pickup_points ) {
			$rate['meta_data']['krokedil_pickup_points'] = wp_json_encode( $pickup_points );
		}

		if ( $selected_pickup_point ) {
			$rate['meta_data']['krokedil_selected_pickup_point']    = wp_json_encode( $selected_pickup_point );
			$rate['meta_data']['krokedil_selected_pickup_point_id'] = $selected_pickup_point->get_id();
		}

		return $rate;
	}

	/**
	 * Get the instance settings form fields for the shipping method.
	 *
	 * @return array
	 */
	public function get_instance_form_fields() {
		return array(
			'fallback_shipping_name'  => array(
				'title'       => __( 'Fallback shipping name', 'avarda-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The name of the fallback shipping method, this will be used in cases where Avarda can\'t get any shipping methods from a third party.', 'avarda-checkout-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Avarda Checkout Shipping', 'avarda-checkout-for-woocommerce' ),
			),
			'fallback_shipping_price' => array(
				'title'       => __( 'Fallback shipping price', 'avarda-checkout-for-woocommerce' ),
				'type'        => 'number',
				'description' => __( 'The price of the fallback shipping method., this will be used in cases where Avarda can\'t get any shipping methods from a third party.', 'avarda-checkout-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '0',
			),
		);
	}
}
