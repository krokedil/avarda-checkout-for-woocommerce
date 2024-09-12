<?php
/**
 * Shipping instance settings class that adds instance settings to shipping methods in WooCommerce.
 *
 * @package Avarda_Checkout/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_Shipping_Instance_Settings
 */
class ACO_Shipping_Instance_Settings {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shipping_instance_settings' ) );
	}

	/**
	 * Register the shipping instance settings for Qliro for each shipping method that exists.
	 */
	public function register_shipping_instance_settings() {
		// Only do this if WooCommerce is the shipping integration chosen.
		$settings = get_option( 'woocommerce_aco_settings' );
		if ( ! isset( $settings['integrated_shipping'] ) || 'woocommerce' !== $settings['integrated_shipping'] ) {
			return;
		}

		$available_shipping_methods = WC()->shipping()->load_shipping_methods();
		$disallowed_methods         = array( 'ksc_udc' );
		foreach ( $available_shipping_methods as $shipping_method ) {
			if ( in_array( $shipping_method->id, $disallowed_methods, true ) ) {
				continue;
			}
			$shipping_method_id = $shipping_method->id;
			add_filter( 'woocommerce_shipping_instance_form_fields_' . $shipping_method_id, array( $this, 'add_shipping_method_fields' ), 99999 );
		}
	}

	/**
	 * Add the shipping method fields.
	 *
	 * @param array $fields The fields.
	 * @return array
	 */
	public function add_shipping_method_fields( $fields ) {
		$fields['aco_title'] = array(
			'title' => __( 'Avarda', 'avarda-checkout-for-woocommerce' ),
			'type'  => 'title',
		);

		$fields['aco_description'] = array(
			'title'       => __( 'Description (Avarda)', 'avarda-checkout-for-woocommerce' ),
			'type'        => 'textarea',
			'default'     => '',
			'description' => __( 'This sets the description which the user sees during checkout in the Avarda Checkout.', 'avarda-checkout-for-woocommerce' ),
			'css'         => 'min-width:100%;',
		);

		$fields['aco_icon_url'] = array(
			'title'       => __( 'Icon URL (Avarda)', 'avarda-checkout-for-woocommerce' ),
			'type'        => 'text',
			'default'     => '',
			'description' => __( 'This sets the URL for the icon the shipping method will get in Avarda Checkout. Use a square icon for best results.', 'avarda-checkout-for-woocommerce' ),
		);

		return $fields;
	}
}
