<?php
/**
 * Compatibility class for integrating with the Webshipper - Automated Shipping plugin.
 *
 * @see https://wordpress.org/plugins/webshipper-automated-shipping/
 * @package  Avarda_Checkout/Classes/Compatibility
 */

defined( 'ABSPATH' ) || exit;

use KrokedilAvardaDeps\Krokedil\Shipping\PickupPoint\PickupPoint;

/**
 * ACO_Compatibility_Webshipper class.
 */
class ACO_Compatibility_Webshipper {
	/**
	 * Webshipper api class instance.
	 *
	 * @var WebshipperAPI
	 */
	private $webshipper_api;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// If the WebshipperAPI class is not available, return.
		if ( ! class_exists( 'WebshipperAPI' ) ) {
			return;
		}

		$this->webshipper_api = new WebshipperAPI();
		add_filter( 'webshipper_add_shipping_rate', array( $this, 'maybe_set_pickup_points' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'add_hidden_order_itemmeta' ) );
		add_filter( 'aco_shipping_method_carrier', array( $this, 'get_carrier' ), 10, 2 );
	}

	/**
	 * Add hidden order item meta for the shipping rate.
	 *
	 * @param array $hidden_meta The hidden meta data.
	 *
	 * @return array
	 */
	public function add_hidden_order_itemmeta( $hidden_meta ) {
		$hidden_meta[] = 'aco_ws_carrier';
		return $hidden_meta;
	}

	/**
	 * Maybe set the pickup points for the shipping rates set by Webshipper.
	 *
	 * @param array $params The shipping rate parameters to set.
	 * @param array $rate The shipping rate from Webshipper to get the information from.
	 *
	 * @return array
	 */
	public function maybe_set_pickup_points( $params, $rate ) {
		$meta_data = $params['meta_data'] ?? array();

		if ( empty( $meta_data ) ) {
			return $params;
		}

		// Set the carrier alias as a meta data.
		$params['meta_data']['aco_ws_carrier'] = $rate['carrier_alias'];

		// Check if the rate has pickup points.
		if ( ! $rate['shipping_rate']['require_drop_point'] ?? false ) {
			return $params;
		}

		$rate_id          = sanitize_text_field( $meta_data['shipping_rate_id'] );
		$ws_pickup_points = $this->get_pickup_points( $rate_id );
		$pickup_points    = $this->format_pickup_points( $ws_pickup_points );

		if ( ! empty( $pickup_points ) ) {
			$selected_pickup_point                                    = $pickup_points[0];
			$params['meta_data']['krokedil_pickup_points']            = wp_json_encode( $pickup_points );
			$params['meta_data']['krokedil_selected_pickup_point']    = wp_json_encode( $selected_pickup_point );
			$params['meta_data']['krokedil_selected_pickup_point_id'] = $selected_pickup_point->get_id();
		}

		return $params;
	}

	/**
	 * Get the carrier alias for the shipping method.
	 *
	 * @param string           $carrier The carrier name to return.
	 * @param WC_Shipping_Rate $rate The shipping rate from WooCommerce.
	 *
	 * @return string
	 */
	public function get_carrier( $carrier, $rate ) {
		$meta_data = $rate->get_meta_data() ?? array();
		// Only do this if the rate has the meta data for aco_ws_carrier set.
		if ( ! isset( $meta_data['aco_ws_carrier'] ) ) {
			return $carrier;
		}

		return $meta_data['aco_ws_carrier'] ?? $carrier;
	}

	/**
	 * Get the Webshipper pickup points for the shipping rate.
	 *
	 * @param string $rate_id The shipping rate from WooCommerce.
	 *
	 * @return array
	 */
	private function get_pickup_points( $rate_id ) {
		// Find an appropriate address.
		$address      = WC()->checkout->get_value( 'shipping_address_1' ) ?? WC()->checkout->get_value( 'billing_address_1' );
		$zip          = WC()->checkout->get_value( 'shipping_postcode' ) ?? WC()->checkout->get_value( 'billing_postcode' );
		$city         = WC()->checkout->get_value( 'shipping_city' ) ?? WC()->checkout->get_value( 'billing_city' );
		$country_code = WC()->checkout->get_value( 'shipping_country' ) ?? WC()->checkout->get_value( 'billing_country' );

		// Sanitize above variables.
		$address      = sanitize_text_field( $address );
		$zip          = sanitize_text_field( $zip );
		$city         = sanitize_text_field( $city );
		$country_code = sanitize_text_field( $country_code );

		// Get the pickup points from the Webshipper API.
		$ws_pickup_points = $this->webshipper_api->searchDropPoint( $rate_id, $address, $zip, $city, $country_code );

		return $ws_pickup_points;
	}

	/**
	 * Format the Webshipper pickup points to the PickupPoint object.
	 *
	 * @param array $ws_pickup_points The Webshipper pickup points.
	 *
	 * @return PickupPoint[]
	 */
	private function format_pickup_points( $ws_pickup_points ) {
		$pickup_points = array();
		foreach ( $ws_pickup_points as $ws_pickup_point ) {
			$id = $ws_pickup_point['drop_point_id'];

			// Split the id on : to separate the pickup point id from the carrier id.
			$ids = explode( ':', $id );

			if ( count( $ids ) < 1 ) { // If we failed to get any ids, just skip.
				continue;
			}

			$pickup_point = ( new PickupPoint() )
				->set_id( $ids[0] )
				->set_name( $ws_pickup_point['name'] )
				->set_address( $ws_pickup_point['address_1'], $ws_pickup_point['city'], $ws_pickup_point['zip'], $ws_pickup_point['country_code'] );

			$pickup_points[] = $pickup_point;
		}

		return $pickup_points;
	}
}
