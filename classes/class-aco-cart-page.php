<?php
/**
 * Class for managing the cart page actions and filters for Avarda Checkout.
 *
 * @package Avarda_Checkout_For_WooCommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing the cart page actions and filters for Avarda Checkout.
 */
class ACO_Cart_Page {
	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'wc_get_template', array( $this, 'override_shipping_template' ), 999, 2 );
	}

	/**
	 * Overrides the default cart shipping template.
	 *
	 * @param string $template The absolute template path.
	 * @param string $template_name The name of the template.
	 *
	 * @return string
	 */
	public function override_shipping_template( $template, $template_name ) {
		// If its not the cart, or the integrated shipping is not enabled.
		if ( ! is_cart() || ! ACO_WC()->checkout->is_integrated_shipping_enabled() ) {
			return $template;
		}

		// Only if Avarda Checkout is the selected payment method, or the first available payment method.
		$chosen_payment_method     = WC()->session->get( 'chosen_payment_method' );
		$available_payment_methods = WC()->payment_gateways->get_available_payment_gateways();
		if ( 'aco' !== $chosen_payment_method && 'aco' !== key( $available_payment_methods ) ) {
			return $template;
		}

		// If its not the cart/cart-shipping.php file, return.
		if ( 'cart/cart-shipping.php' !== $template_name ) {
			return $template;
		}

		if ( locate_template( 'woocommerce/avarda-cart-shipping.php' ) ) {
			$template = locate_template( 'woocommerce/avarda-cart-shipping.php' );
		} else {
			$template = AVARDA_CHECKOUT_PATH . '/templates/avarda-cart-shipping.php';
		}

		return $template;
	}
}
