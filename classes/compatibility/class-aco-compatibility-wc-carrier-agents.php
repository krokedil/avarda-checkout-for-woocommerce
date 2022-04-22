<?php
/**
 * Handles Woo Carrier Agents compatibility for the plugin.
 *
 * @Author  https://markup.fi
 *
 * @package Avarda_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACO_Compatibility_WC_Carrier_Agents class.
 */
class ACO_Compatibility_WC_Carrier_Agents {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'woo_carrier_agents_search_output', array( $this, 'add_carrier_agents_search_output_to_avarda_template' ) );
	}

	/**
	 * Handles display of Woo Carrier Agents postcode search area.
	 *
	 * @param array $actions The actions where the Woo Carrier Agents Postcode search should be displayed.
	 *
	 * @return array
	 */
	public function add_carrier_agents_search_output_to_avarda_template( $actions ) {
		$actions['aco_wc_after_order_review'] = 8;
		return $actions;
	}
}
new ACO_Compatibility_WC_Carrier_Agents();
