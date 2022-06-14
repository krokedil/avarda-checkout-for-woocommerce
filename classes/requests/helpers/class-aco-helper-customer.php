<?php // phpcs:ignore
/**
 * Customer helper class.
 *
 * @package Avarda_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for order management.
 */
class ACO_Helper_Customer {
	/**
	 * Gets formated order items.
	 *
	 * @param int $order_id The WooCommerce order object.
	 * @return array Formated order items.
	 */
	public function get_b2b_customer( $order_id ) {
		$customer = array();
		$order    = wc_get_order( $order_id );

		// Billing address.
		if ( $order->get_billing_company() ) {
			$customer['invoicingAddress']['name'] = $order->get_billing_company();
		}
		if ( $order->get_billing_address_1() ) {
			$customer['invoicingAddress']['address1'] = $order->get_billing_address_1();
		}
		if ( $order->get_billing_address_2() ) {
			$customer['invoicingAddress']['address2'] = $order->get_billing_address_2();
		}
		if ( $order->get_billing_postcode() ) {
			$customer['invoicingAddress']['zip'] = $order->get_billing_postcode();
		}
		if ( $order->get_billing_city() ) {
			$customer['invoicingAddress']['city'] = $order->get_billing_city();
		}
		if ( $order->get_billing_country() ) {
			$customer['invoicingAddress']['city'] = $order->get_billing_country();
		}

		// Shipping address.
		if ( $order->get_shipping_first_name() ) {
			$customer['deliveryAddress']['firstName'] = $order->get_shipping_first_name();
		}
		if ( $order->get_shipping_last_name() ) {
			$customer['deliveryAddress']['lastName'] = $order->get_shipping_last_name();
		}
		if ( $order->get_shipping_address_1() ) {
			$customer['deliveryAddress']['address1'] = $order->get_shipping_address_1();
		}
		if ( $order->get_shipping_address_2() ) {
			$customer['deliveryAddress']['address2'] = $order->get_shipping_address_2();
		}
		if ( $order->get_shipping_postcode() ) {
			$customer['deliveryAddress']['zip'] = $order->get_shipping_postcode();
		}
		if ( $order->get_shipping_city() ) {
			$customer['deliveryAddress']['city'] = $order->get_shipping_city();
		}
		if ( $order->get_shipping_country() ) {
			$customer['deliveryAddress']['city'] = $order->get_shipping_country();
		}

		// Phone and email.
		if ( $order->get_billing_phone() ) {
			$customer['userInputs']['phone'] = $order->get_billing_phone();
		}
		if ( $order->get_billing_email() ) {
			$customer['userInputs']['email'] = $order->get_billing_email();
		}

		return $customer;
	}

	public function get_b2c_customer( $order_id ) {
		$customer = array();
		$order    = wc_get_order( $order_id );

		// Billing address.
		if ( $order->get_billing_first_name() ) {
			$customer['invoicingAddress']['firstName'] = $order->get_billing_first_name();
		}
		if ( $order->get_billing_last_name() ) {
			$customer['invoicingAddress']['lastName'] = $order->get_billing_last_name();
		}
		if ( $order->get_billing_address_1() ) {
			$customer['invoicingAddress']['address1'] = $order->get_billing_address_1();
		}
		if ( $order->get_billing_address_2() ) {
			$customer['invoicingAddress']['address2'] = $order->get_billing_address_2();
		}
		if ( $order->get_billing_postcode() ) {
			$customer['invoicingAddress']['zip'] = $order->get_billing_postcode();
		}
		if ( $order->get_billing_city() ) {
			$customer['invoicingAddress']['city'] = $order->get_billing_city();
		}
		if ( $order->get_billing_country() ) {
			$customer['invoicingAddress']['city'] = $order->get_billing_country();
		}

		// Shipping address.
		if ( $order->get_shipping_first_name() ) {
			$customer['deliveryAddress']['firstName'] = $order->get_shipping_first_name();
		}
		if ( $order->get_shipping_last_name() ) {
			$customer['deliveryAddress']['lastName'] = $order->get_shipping_last_name();
		}
		if ( $order->get_shipping_address_1() ) {
			$customer['deliveryAddress']['address1'] = $order->get_shipping_address_1();
		}
		if ( $order->get_shipping_address_2() ) {
			$customer['deliveryAddress']['address2'] = $order->get_shipping_address_2();
		}
		if ( $order->get_shipping_postcode() ) {
			$customer['deliveryAddress']['zip'] = $order->get_shipping_postcode();
		}
		if ( $order->get_shipping_city() ) {
			$customer['deliveryAddress']['city'] = $order->get_shipping_city();
		}
		if ( $order->get_shipping_country() ) {
			$customer['deliveryAddress']['city'] = $order->get_shipping_country();
		}

		// Phone and email.
		if ( $order->get_billing_phone() ) {
			$customer['userInputs']['phone'] = $order->get_billing_phone();
		}
		if ( $order->get_billing_email() ) {
			$customer['userInputs']['email'] = $order->get_billing_email();
		}

		return $customer;
	}


}
