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
	 * Gets formated cart items.
	 *
	 * @param object $cart The WooCommerce cart object.
	 * @return array Formated cart items.
	 */
	public function get_checkout_setup( $cart = null ) {
		$checkout_setup = array();
		$terms_url      = $this->get_terms_url();

		$checkout_setup['mode']                     = 'B2C'; // TODO: Logic for using correct value depending on customer type.
		$checkout_setup['completedNotificationUrl'] = get_home_url() . '/wc-api/ACO_WC_Notification';
		$checkout_setup['language']                 = $this->get_language();

		if ( ! empty( $terms_url ) ) {
			$checkout_setup['termsAndConditionsUrl'] = $terms_url;
		}

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

}
