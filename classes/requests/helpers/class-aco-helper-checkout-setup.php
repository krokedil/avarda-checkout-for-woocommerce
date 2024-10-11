<?php // phpcs:ignore
/**
 * Checkout Setup helper class.
 *
 * @package Avarda_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for cart management.
 */
class ACO_Helper_Checkout_Setup {
	/**
	 * Gets checkout setup.
	 *
	 * @param int  $order_id The WooCommerce order id.
	 * @param bool $is_international Whether the order is international or not.
	 *
	 * @return array Formatted checkout setup.
	 */
	public function get_checkout_setup( $order_id = null, $is_international = false ) {

		$avarda_settings = get_option( 'woocommerce_aco_settings' );
		$age_validation  = $avarda_settings['age_validation'] ?? '';

		$checkout_setup = array();
		$terms_url      = $this->get_terms_url();

		$checkout_setup['completedNotificationUrl'] = get_home_url() . '/wc-api/ACO_WC_Notification';
		$checkout_setup['language']                 = $this->get_language();

		if ( ! empty( $terms_url ) ) {
			$checkout_setup['termsAndConditionsUrl'] = $terms_url;
		}

		// Don't display separate delivery address info in Avarda if this is a redirect flow order.
		if ( ! empty( $order_id ) ) {
			$checkout_setup['differentDeliveryAddress'] = 'Hidden';
		}

		// B2B or B2C.
		$customer_type = 'B2C';
		if ( ! empty( $order_id ) ) {
			$order = wc_get_order( $order_id );
			if ( $order->get_billing_company() ) {
				$customer_type = 'B2B';
			}
		}
		$checkout_setup['mode'] = $customer_type;

		// Age validation.
		if ( ! empty( $age_validation ) ) {
			$checkout_setup['ageValidation'] = $age_validation;
		}

		// Set the international properties to the checkout setup if the order is international.
		if ( $is_international ) {
			$checkout_setup = $this->set_international_properties( $checkout_setup );
		}

		// Set display items based on the setting.
		$checkout_setup['displayItems'] = 'yes' === ( $avarda_settings['display_items'] ?? 'no' );

		return $checkout_setup;
	}

	/**
	 * Get the language for request.
	 *
	 * @return string $language The language.
	 */
	public function get_language() {
		switch ( get_locale() ) {
			case 'sv_SE':
				$language = 'Swedish';
				break;
			case 'fi':
				$language = 'Finnish';
				break;
			case 'nb_NO':
				$language = 'Norwegian';
				break;
			case 'nn_NO':
				$language = 'Norwegian';
				break;
			case 'da_DK':
				$language = 'Danish';
				break;
			default:
				$language = 'English';
				break;
		}
		return $language;
	}

	/**
	 * Terms URL.
	 *
	 * Required. URL of merchant terms and conditions.
	 *
	 * @return string
	 */
	private function get_terms_url() {
		$terms_url = get_permalink( wc_get_page_id( 'terms' ) );

		return apply_filters( 'aco_wc_terms_url', $terms_url );
	}

	/**
	 * Set international properties.
	 *
	 * @param array $checkout_setup The checkout setup.
	 *
	 * @return array
	 */
	private function set_international_properties( $checkout_setup ) {
		$checkout_setup['enableCountrySelector'] = true;
		$checkout_setup['invoicingCountries']    = array_keys( WC()->countries->get_allowed_countries() );
		$checkout_setup['deliveryCountries']     = array_keys( WC()->countries->get_shipping_countries() );

		return $checkout_setup;
	}
}
