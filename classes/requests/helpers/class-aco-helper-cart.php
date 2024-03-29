<?php // phpcs:ignore
/**
 * Get cart helper class.
 *
 * @package Avarda_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for cart management.
 */
class ACO_Helper_Cart {
	/**
	 * Gets formated cart items.
	 *
	 * @param object $cart The WooCommerce cart object.
	 * @return array Formated cart items.
	 */
	public function get_cart_items( $cart = null ) {
		$formated_cart_items = array();

		if ( null === $cart ) {
			$cart = WC()->cart->get_cart();
		}

		// Get cart items.
		foreach ( $cart as $cart_item ) {
			$formated_cart_items[] = $this->get_cart_item( $cart_item );
		}

		// Get cart fees.
		$cart_fees = WC()->cart->get_fees();
		foreach ( $cart_fees as $fee ) {
			$formated_cart_items[] = $this->get_fee( $fee );
		}

		// Get cart shipping.
		if ( WC()->cart->needs_shipping() ) {
			$shipping = $this->get_shipping();
			if ( null !== $shipping ) {
				$formated_cart_items[] = $shipping;
			}
		}

		// Smart coupons.
		if ( ! empty( WC()->cart->get_coupons() ) ) {
			$apply_before_tax = 'yes' === get_option( 'woocommerce_smart_coupon_apply_before_tax', 'no' );
			foreach ( WC()->cart->get_coupons() as $coupon_key => $coupon ) {
				if ( 'smart_coupon' === $coupon->get_discount_type() ) {
					if ( wc_tax_enabled() && $apply_before_tax ) {
						// The discount is applied directly to the cart item. Send gift card amount as zero for bookkeeping.
						$coupon_amount = 0;
					} else {
						$coupon_amount = $coupon->get_amount() * -1;
					}
					$coupon_amount = number_format( $coupon_amount, 2, '.', '' );
					// translators: %s is the gift card reference.
					$label        = apply_filters( 'aco_smart_coupon_gift_card_label', esc_html( sprintf( __( 'Gift card: %s', 'avarda-checkout-for-woocommerce' ), $coupon->get_code() ) ), $coupon );
					$giftcard_sku = apply_filters( 'aco_smart_coupon_gift_card_sku', esc_html( $coupon->get_id() ), $coupon );
					$gift_card    = array(
						'notes'       => $giftcard_sku,
						'description' => substr( $label, 0, 34 ),
						'quantity'    => 1,
						'amount'      => $coupon_amount,
						'taxCode'     => 0,
						'taxAmount'   => 0,
					);

					$formated_cart_items[] = $gift_card;
				}
			}
		}

		return $formated_cart_items;
	}

	/**
	 * Gets formated cart item.
	 *
	 * @param object $cart_item WooCommerce cart item object.
	 * @return array Formated cart item.
	 */
	public function get_cart_item( $cart_item ) {
		if ( $cart_item['variation_id'] ) {
			$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			$product = wc_get_product( $cart_item['product_id'] );
		}
		return array(
			'description' => substr( $this->get_product_name( $cart_item ), 0, 34 ), // String.
			'notes'       => substr( $this->get_product_sku( $product ), 0, 34 ), // String.
			'amount'      => $this->get_product_price( $cart_item ), // Float.
			'taxCode'     => $this->get_product_tax_code( $cart_item ), // String.
			'taxAmount'   => number_format( $cart_item['line_tax'] / $cart_item['quantity'], 2, '.', '' ), // Float.
			'quantity'    => $cart_item['quantity'],
		);
	}

	/**
	 * Gets the product name.
	 *
	 * @param object $cart_item The cart item.
	 * @return string
	 */
	public function get_product_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$cart_item_name = $cart_item_data->get_name();
		$item_name      = apply_filters( 'pco_cart_item_name', $cart_item_name, $cart_item );
		return strip_tags( $item_name );
	}

	/**
	 * Gets the products price.
	 *
	 * @param object $cart_item The cart item.
	 * @return float
	 */
	public function get_product_price( $cart_item ) {
		$items_subtotal = ( $cart_item['line_total'] + $cart_item['line_tax'] ) / $cart_item['quantity'];
		return number_format( $items_subtotal, 2, '.', '' );
	}

	/**
	 * Gets the tax rate for the product.
	 *
	 * @param object $cart_item The cart item.
	 * @return float
	 */
	public function get_product_tax_rate( $cart_item ) {
		if ( 0 === intval( $cart_item['line_total'] ) ) {
			return 0;
		}
		return number_format( $cart_item['line_tax'] / $cart_item['line_total'], 2, '.', '' );
	}

	/**
	 * Gets the tax code for the product.
	 *
	 * @param object $cart_item The cart item.
	 * @return float
	 */
	public function get_product_tax_code( $cart_item ) {
		return (string) round( $this->get_product_tax_rate( $cart_item ) * 100 );
	}

	/**
	 * Gets the products SKU.
	 *
	 * @param object $product The WooCommerce Product.
	 * @return string
	 */
	public function get_product_sku( $product ) {
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} else {
			$item_reference = $product->get_id();
		}

		return $item_reference;
	}

	/**
	 * Formats the fee.
	 *
	 * @param object $fee A WooCommerce Fee.
	 * @return array
	 */
	public function get_fee( $fee ) {
		return array(
			'description' => substr( $fee->name, 0, 34 ), // String.
			'notes'       => substr( 'fee|' . $fee->id, 0, 34 ), // String.
			'amount'      => number_format( $fee->amount + $fee->tax, 2, '.', '' ), // String.
			'taxCode'     => (string) ( $fee->tax / $fee->amount * 100 ), // String.
			'taxAmount'   => number_format( $fee->tax, 2, '.', '' ), // String.
		);
	}

	/**
	 * Formats the shipping.
	 *
	 * @return array
	 */
	public function get_shipping() {
		$packages        = WC()->shipping->get_packages();
		$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0];
		foreach ( $packages as $i => $package ) {
			foreach ( $package['rates'] as $method ) {
				if ( $chosen_shipping === $method->id ) {
					if ( $method->cost > 0 ) {
						return array(
							'description' => substr( $method->label, 0, 34 ), // String.
							'notes'       => substr( 'shipping|' . $method->id, 0, 34 ), // String.
							'amount'      => number_format( $method->cost + array_sum( $method->taxes ), 2, '.', '' ), // String.
							'taxCode'     => (string) ( array_sum( $method->taxes ) / $method->cost * 100 ), // String.
							'taxAmount'   => number_format( array_sum( $method->taxes ), 2, '.', '' ), // Float.
						);
					} else {
						return array(
							'description' => substr( $method->label, 0, 34 ), // String.
							'notes'       => substr( 'shipping|' . $method->id, 0, 34 ), // String.
							'amount'      => 0, // Float.
							'taxCode'     => '0', // String.
							'taxAmount'   => 0, // Float.
						);
					}
				}
			}
		}
	}
}
