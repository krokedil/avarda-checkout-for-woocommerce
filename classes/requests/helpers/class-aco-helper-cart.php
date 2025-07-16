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
	 * Is integrated shipping used or not.
	 *
	 * @return bool
	 */
	public function is_integrated_shipping() {
		return apply_filters( 'aco_integrated_shipping', false );
	}

	/**
	 * Gets formatted cart items.
	 *
	 * @param object $cart The WooCommerce cart object.
	 * @return array Formatted cart items.
	 */
	public function get_cart_items( $cart = null ) {
		$formatted_cart_items = array();

		if ( null === $cart ) {
			$cart = WC()->cart->get_cart();
		}

		// Get cart items.
		foreach ( $cart as $cart_item ) {
			$formatted_cart_items[] = $this->get_cart_item( $cart_item );
		}

		// Get cart fees.
		$cart_fees = WC()->cart->get_fees();
		foreach ( $cart_fees as $fee ) {
			$formatted_cart_items[] = $this->get_fee( $fee );
		}

		// Get cart shipping.
		if ( WC()->cart->needs_shipping() ) {
			// If integrated shipping is not used, then add shipping as a separate line item.
			if ( ! ACO_WC()->checkout->is_integrated_shipping_enabled() ) {
				$shipping = $this->get_shipping();
				if ( null !== $shipping ) {
					$formatted_cart_items[] = $shipping;
				}
			}
		}

		foreach ( ACO_WC()->krokedil->compatibility()->giftcards() as $giftcards ) {
			if ( false !== ( strpos( get_class( $giftcards ), 'WCGiftCards', true ) ) && ! function_exists( 'WC_GC' ) ) {
				continue;
			}

			$retrieved_giftcards = $giftcards->get_cart_giftcards();
			foreach ( $retrieved_giftcards as $retrieved_giftcard ) {
				$formatted_cart_items[] = array(
					'note'        => $retrieved_giftcard->get_sku(),
					'description' => $retrieved_giftcard->get_name(),
					'quantity'    => $retrieved_giftcard->get_quantity(),
					'amount'      => $retrieved_giftcard->get_total_amount(),
					'taxCode'     => $retrieved_giftcard->get_tax_rate(),
					'taxAmount'   => $retrieved_giftcard->get_total_tax_amount(),
				);
			}
		}

		return $formatted_cart_items;
	}

	/**
	 * Gets formatted cart item.
	 *
	 * @param object $cart_item WooCommerce cart item object.
	 * @return array Formatted cart item.
	 */
	public function get_cart_item( $cart_item ) {
		if ( $cart_item['variation_id'] ) {
			$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			$product = wc_get_product( $cart_item['product_id'] );
		}
		return array(
			'description'        => substr( $this->get_product_name( $cart_item ), 0, 34 ), // String.
			'notes'              => substr( $this->get_product_sku( $product ), 0, 34 ), // String.
			'amount'             => $this->get_product_price( $cart_item ), // Float.
			'taxCode'            => $this->get_product_tax_code( $cart_item ), // String.
			'taxAmount'          => number_format( $cart_item['line_tax'] / $cart_item['quantity'], 2, '.', '' ), // Float.
			'quantity'           => $cart_item['quantity'],
			'shippingParameters' => $this->get_product_shipping_params( $product ),
		);
	}

	/**
	 * Gets the shipping parameters for the product.
	 *
	 * @param WC_Product $product The WooCommerce Product.
	 * @return array
	 */
	public function get_product_shipping_params( $product ) {
		// Get any shipping classes the product has.
		$shipping_class = $product->get_shipping_class();

		$weight = $product->get_weight();
		$length = $product->get_length();
		$width  = $product->get_width();
		$height = $product->get_height();

		// Default empty values to 0.
		$weight = empty( $weight ) ? 0 : $weight;
		$length = empty( $length ) ? 0 : $length;
		$width  = empty( $width ) ? 0 : $width;
		$height = empty( $height ) ? 0 : $height;

		$shipping_params = array(
			'weight' => intval( wc_get_weight( $weight, 'g' ) ),
			'length' => intval( wc_get_dimension( $length, 'mm' ) ),
			'width'  => intval( wc_get_dimension( $width, 'mm' ) ),
			'height' => intval( wc_get_dimension( $height, 'mm' ) ),
		);

		// Only set the attributes if the shipping class is not empty.
		if ( ! empty( $shipping_class ) ) {
			$shipping_params['attributes'] = array( $shipping_class );
		}

		$shipping_params = apply_filters( 'aco_item_shipping_params', $shipping_params, $product );

		return $shipping_params;
	}

	/**
	 * Get the shipping settings for the cart.
	 *
	 * @return array
	 */
	public function get_shipping_settings() {
		$cart       = WC()->cart->get_cart();
		$vouchers   = array();
		$attributes = array();

		// Check if any free shipping coupons are used.
		$has_free_shipping_coupon = false;
		if ( ! empty( WC()->cart->get_applied_coupons() ) ) {
			foreach ( WC()->cart->get_applied_coupons() as $coupon ) {
				$coupon_object = new WC_Coupon( $coupon );
				if ( $coupon_object->get_free_shipping() ) {
					$has_free_shipping_coupon = true;
					break;
				}
			}
		}

		if ( $has_free_shipping_coupon ) {
			$vouchers[] = 'FREESHIPPING';
		}

		// Get all shipping classes applied to the cart items.
		foreach ( $cart as $cart_item ) {
			$product = wc_get_product( $cart_item['product_id'] );
			if ( $product->get_shipping_class() ) {
				$attributes[] = $product->get_shipping_class();
			}
		}

		$shipping_settings = array(
			'vouchers'   => $vouchers,
			'attributes' => $attributes,
		);

		return apply_filters( 'aco_shipping_settings', $shipping_settings, $cart );
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
	 * @return string
	 */
	public function get_product_tax_code( $cart_item ) {
		// Check if 'data' exists and get the tax class, and ensure get_tax_class exists as a method to prevent errors.
		if ( isset( $cart_item['data'] ) && method_exists( $cart_item['data'], 'get_tax_class' ) ) {
			$tax_class = $cart_item['data']->get_tax_class();

			// Get the tax rates from the tax class.
			$rates = WC_Tax::get_rates( $tax_class );
			if ( ! empty( $rates ) ) {
				$first_rate = reset( $rates ); // Only use the first rate returned.
				// Check if 'rate' key exists; otherwise, fallback to 0
				return (string) ( $first_rate['rate'] ?? 0 );
			}
		}

		// Return a default value if no rate is found.
		return '0';
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
	 * @return array|null
	 */
	public function get_shipping() {
		$packages        = WC()->shipping->get_packages();
		$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0] ?? null;

		$formatted_shipping = null;
		foreach ( $packages as $i => $package ) {
			/**
			 * Loop each rate to get the correct one.
			 *
			 * @var WC_Shipping_Rate $rate Shipping rate.
			 */
			foreach ( $package['rates'] as $rate ) {
				if ( ACO_WC()->checkout->is_integrated_shipping_enabled() || ACO_WC()->checkout->is_integrated_wc_shipping_enabled() ) {
					// If we should not show shipping, send the amount as 0.
					$amount = WC()->cart->show_shipping() ? number_format( $rate->get_cost() + array_sum( $rate->get_taxes() ), 2, '.', '' ) : '0';

					return array(
						'description' => substr( $rate->get_label(), 0, 34 ), // String.
						'notes'       => 'SHI001', // Has to be a static string for Avarda to recognize it as the fallback shipping method. @see https://docs.avarda.com/checkout-3/overview/shipping-broker/common-integration-guide/default-shipping-item/.
						'amount'      => $amount,
						'taxCode'     => (string) ( $rate->get_cost() != 0 ? array_sum( $rate->get_taxes() ) / $rate->get_cost() * 100 : 0 ), // String.
						'taxAmount'   => number_format( array_sum( $rate->get_taxes() ), 2, '.', '' ), // Float.
					);
				}

				$rate_id = method_exists( $rate, 'get_id' ) ? $rate->get_id() : ( $rate->get_method_id() . ':' . $rate->get_instance_id() );
				if ( $chosen_shipping === $rate_id ) {
					$formatted_shipping = ( $rate->get_cost() > 0 ) ? array(
						'description' => substr( $rate->get_label(), 0, 34 ), // String.
						'notes'       => substr( 'shipping|' . $rate_id, 0, 34 ), // String.
						'amount'      => number_format( $rate->get_cost() + array_sum( $rate->get_taxes() ), 2, '.', '' ), // String.
						'taxCode'     => (string) ( array_sum( $rate->get_taxes() ) / $rate->get_cost() * 100 ), // String.
						'taxAmount'   => number_format( array_sum( $rate->get_taxes() ), 2, '.', '' ), // Float.
					) : array(
						'description' => substr( $rate->get_label(), 0, 34 ), // String.
						'notes'       => substr( 'shipping|' . $rate_id, 0, 34 ), // String.
						'amount'      => 0, // Float.
						'taxCode'     => '0', // String.
						'taxAmount'   => 0, // Float.
					);
				}
			}
		}

		return $formatted_shipping;
	}

	/**
	 * Get the cart attachment as a json encoded string.
	 *
	 * @return string
	 */
	public function get_cart_attachment() {
		$attachment = array();

		if ( ACO_WC()->checkout->is_integrated_wc_shipping_enabled() ) {
			// Add the attachment with session information so we can use it in the API.
			$attachment['shipping'] = array(
				'customerId'            => WC()->session->get_customer_unique_id(),
				'chosenShippingMethods' => WC()->session->get( 'chosen_shipping_methods' ),
			);
		}

		return empty( $attachment ) ? '' : wp_json_encode( $attachment );
	}
}
