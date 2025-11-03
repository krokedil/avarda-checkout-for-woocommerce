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
	 * Get the B2B customer details.
	 *
	 * @param int $order_id The WooCommerce order id. Null if we are using the cart.
	 *
	 * @return array
	 */
	public function get_b2b_customer( $order_id = null ) {
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
			$customer['invoicingAddress']['country'] = $order->get_billing_country();
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
			$customer['deliveryAddress']['country'] = $order->get_shipping_country();
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

	/**
	 * Get the B2C customer details.
	 *
	 * @param int $order_id The WooCommerce order id. Null if we are using the cart.
	 *
	 * @return array
	 */
	public function get_b2c_customer( $order_id = null ) {
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
			$customer['invoicingAddress']['country'] = $order->get_billing_country();
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
			$customer['deliveryAddress']['country'] = $order->get_shipping_country();
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

	/**
	 * Get address details from WooCommerce from an order or the customer session.
	 *
	 * @param WC_Order|bool $order The WooCommerce order id. False if we are using the cart.
	 * @param bool          $b2b If the customer is a B2B customer.
	 *
	 * @return array
	 */
	public function get_customer( $order = false, $b2b = false ) {
		$item = $order ? $order : WC()->customer;

		$customer = array_filter(
			array(
				'invoicingAddress' => self::get_invoicing_address( $item, $b2b ),
				'deliveryAddress'  => self::get_delivery_address( $item ),
				'userInputs'       => self::get_user_inputs( $item ),
			)
		);

		return $customer;
	}

	/**
	 * Get the invoicing address.
	 *
	 * @param WC_Order|WC_Customer $item Either a WC_Order or WC_Customer object.
	 * @param bool                 $b2b If the customer is a B2B customer.
	 *
	 * @return array
	 */
	public static function get_invoicing_address( $item, $b2b = false ) {
		$invoicing_address = array_filter(
			array(
				'name'      => $b2b ? $item->get_billing_company() : '',
				'firstName' => $item->get_billing_first_name(),
				'lastName'  => $item->get_billing_last_name(),
				'address1'  => $item->get_billing_address_1(),
				'address2'  => $item->get_billing_address_2(),
				'zip'       => $item->get_billing_postcode(),
				'city'      => $item->get_billing_city(),
				'country'   => $item->get_billing_country(),
			)
		);

		return $invoicing_address;
	}

	/**
	 * Get the delivery address.
	 *
	 * @param WC_Order|WC_Customer $item Either a WC_Order or WC_Customer object.
	 *
	 * @return array
	 */
	public static function get_delivery_address( $item ) {
		$delivery_countries = WC()->countries->get_shipping_countries();
		$store_country      = WC()->countries->get_base_country();
		$country            = $item->get_shipping_country();

		( isset( $delivery_countries[ $country ] ) ? $country : $store_country );

		$delivery_address = array_filter(
			array(
				'firstName' => $item->get_shipping_first_name(),
				'lastName'  => $item->get_shipping_last_name(),
				'address1'  => $item->get_shipping_address_1(),
				'address2'  => $item->get_shipping_address_2(),
				'zip'       => $item->get_shipping_postcode(),
				'city'      => $item->get_shipping_city(),
				'country'   => $item->get_shipping_country(),
			)
		);

		return self::filter_fields( $delivery_address );
	}

	/**
	 * Get the user inputs.
	 *
	 * @param WC_Order|WC_Customer $item Either a WC_Order or WC_Customer object.
	 *
	 * @return array
	 */
	public static function get_user_inputs( $item ) {
		$user_input = array_filter(
			array(
				'phone' => $item->get_billing_phone(),
				'email' => $item->get_billing_email(),
			)
		);

		return self::filter_fields( $user_input );
	}

	/**
	 * Helper to filter fields, removing empty and values containing '*'.
	 *
	 * @param array $fields Fields to filter.
	 * @return array Filtered array.
	 */
	protected static function filter_fields( $fields ) {
		return array_filter(
			$fields,
			function ( $value ) {
				return ! empty( $value ) && false === strpos( $value, '*' );
			}
		);
	}
}
