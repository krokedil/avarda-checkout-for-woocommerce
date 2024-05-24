<?php // phpcs:ignore
/**
 * Get order helper class.
 *
 * @package Avarda_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for order management.
 */
class ACO_Helper_Order {
	/**
	 * Gets formated order items.
	 *
	 * @param int $order_id The WooCommerce order object.
	 * @return array Formated order items.
	 */
	public function get_order_items( $order_id ) {
		$formated_order_items = array();
		$order                = wc_get_order( $order_id );

		// Get order items.
		$order_items = $order->get_items();
		foreach ( $order_items as $order_item ) {
			// Regular order item.
			$formatted_order_item = $this->get_order_item( $order, $order_item );
			if ( is_array( $formatted_order_item ) ) {
				$formated_order_items[] = $formatted_order_item;
			}
			// Refunded order item (if Avardas refund endpoint has been used before the order was charged).
			$formatted_refunded_order_item = $this->get_refunded_order_item( $order, $order_item );
			if ( is_array( $formatted_refunded_order_item ) ) {
				$formated_order_items[] = $formatted_refunded_order_item;
			}
		}

		// Get order fees.
		$order_fees = $order->get_fees();
		foreach ( $order_fees as $fee ) {
			$formated_order_items[] = $this->get_fee( $order, $fee );
			// Refunded order item (if Avardas refund endpoint has been used before the order was charged).
			$formatted_refunded_order_item = $this->get_refunded_order_item( $order, $fee );
			if ( is_array( $formatted_refunded_order_item ) ) {
				$formated_order_items[] = $formatted_refunded_order_item;
			}
		}

		// Get order shipping.
		foreach ( $order->get_items( 'shipping' ) as $order_item ) {
			$formated_order_items[] = self::process_order_item_shipping( $order, $order_item );
			// Refunded order item (if Avardas refund endpoint has been used before the order was charged).
			$formatted_refunded_order_item = $this->get_refunded_order_item( $order, $order_item );
			if ( is_array( $formatted_refunded_order_item ) ) {
				$formated_order_items[] = $formatted_refunded_order_item;
			}
		}

		// Process gift cards.
		$formated_order_items = self::process_gift_cards( $order_id, $order, $formated_order_items );

		return $formated_order_items;
	}

	/**
	 * Gets formated order item.
	 *
	 * @param WC_Order $order WC order.
	 * @param object   $order_item WooCommerce order item object.
	 * @return array Formated order item.
	 */
	public function get_order_item( $order, $order_item ) {
		if ( $order_item['variation_id'] ) {
			$product = wc_get_product( $order_item['variation_id'] );
		} else {
			$product = wc_get_product( $order_item['product_id'] );
		}

		return array(
			'description' => substr( self::get_item_name( $order_item ), 0, 34 ), // String.
			'notes'       => substr( self::get_reference( $order_item ), 0, 34 ), // String.
			'amount'      => self::get_item_price_incl_vat( $order_item ), // Float.
			'taxCode'     => $this->get_product_tax_code( $order, $order_item ), // Float.
			'taxAmount'   => self::get_item_tax_amount( $order_item ), // Float.
			'quantity'    => $order_item->get_quantity(),
		);
	}

	/**
	 * Gets formated refunded order item.
	 *
	 * @param WC_Order $order WC order.
	 * @param object   $order_item WooCommerce order item object.
	 * @return array Formated order item.
	 */
	public function get_refunded_order_item( $order, $order_item ) {

		$total_refunded_for_item_incl_vat = self::get_total_refunded_for_item_incl_vat( $order, $order_item );
		$total_tax_refunded_for_item      = self::get_total_tax_refunded_for_item( $order, $order_item );

		// If no refund has been performed - return.
		if ( empty( floatval( $total_refunded_for_item_incl_vat ) ) ) {
			return false;
		}

		return array(
			'description' => substr( 'Refunded: ' . self::get_item_name( $order_item ), 0, 34 ), // String.
			'notes'       => substr( self::get_reference( $order_item ), 0, 34 ), // String.
			'amount'      => $total_refunded_for_item_incl_vat, // string.
			'taxCode'     => $this->get_product_tax_code( $order, $order_item ), // Float.
			'taxAmount'   => $total_tax_refunded_for_item, // Float.
			'quantity'    => 1,
		);
	}

	/**
	 * Gets the product name.
	 *
	 * @param object $order_item The order item.
	 * @return string
	 */
	public static function get_item_name( $order_item ) {
		$item_name = $order_item->get_name();
		return wp_strip_all_tags( $item_name );
	}

	/**
	 * Gets the tax code for the product.
	 *
	 * @param object $order The order item.
	 * @param object $order_item The WooCommerce order item.
	 * @return float
	 */
	public function get_product_tax_code( $order, $order_item ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			if ( key( $order_item->get_taxes()['total'] ) === $rate_id ) {
				return (string) round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'], 2 );
			}
		}
	}

	/**
	 * Gets the tax amount for the product.
	 *
	 * @param object $order The order item.
	 * @param object $order_item The WooCommerce order item.
	 * @return float
	 */
	public function get_product_tax_amount( $order, $order_item ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$tax_amount = $tax_item->get_tax_total();
			return number_format( $tax_amount, 2, '.', '' );
		}
	}

	/**
	 * Formats the fee.
	 *
	 * @param object $order WooCommerce order.
	 * @param object $fee A WooCommerce Fee.
	 * @return array
	 */
	public function get_fee( $order, $fee ) {
		return array(
			'description' => substr( $fee->get_name(), 0, 34 ), // String.
			'notes'       => substr( $fee->get_id(), 0, 34 ), // String.
			'amount'      => self::get_item_price_incl_vat( $fee ), // String.
			'taxCode'     => self::get_tax_rate( $order, $fee ), // String.
			'taxAmount'   => self::get_item_tax_amount( $fee ), // String.
		);
	}

	/**
	 * Gets the formated order line shipping.
	 *
	 * @param WC_Order|null          $order The WooCommerce order.
	 * @param WC_Order_Item_Shipping $order_item The WooCommerce order line item.
	 * @return array
	 */
	public static function process_order_item_shipping( $order, $order_item ) {
		return array(
			'description' => substr( $order_item->get_name(), 0, 34 ), // String.
			'notes'       => substr( __( 'Shipping', 'avarda-checkout-for-woocommerce' ), 0, 34 ), // String.
			'amount'      => self::get_item_price_incl_vat( $order_item ), // String.
			'taxCode'     => self::get_tax_rate( $order, $order_item ), // Float.
			'taxAmount'   => self::get_item_tax_amount( $order_item ),
		);
	}

	/**
	 * Gets the item price including vat.
	 *
	 * @param object $order_item The order item.
	 * @return float
	 */
	public static function get_item_price_incl_vat( $order_item ) {
		$items_subtotal = ( ( $order_item->get_total() + $order_item->get_total_tax() ) / $order_item->get_quantity() );
		return number_format( $items_subtotal, 2, '.', '' );
	}

	/**
	 * Gets the item tax amount.
	 *
	 * @param object $order_item The order item.
	 * @return float
	 */
	public static function get_item_tax_amount( $order_item ) {
		return number_format( $order_item->get_total_tax() / $order_item->get_quantity(), 2, '.', '' );
	}

	/**
	 * Get the tax rate.
	 *
	 * @param WC_Order                                                       $order The WooCommerce order.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The WooCommerce order item.
	 * @return int
	 */
	public static function get_tax_rate( $order, $order_item ) {
		// If we don't have any tax, return 0.
		if ( '0' === $order_item->get_total_tax() ) {
			return 0;
		}

		$tax_items = $order->get_items( 'tax' );
		/**
		 * Process the tax items.
		 *
		 * @var WC_Order_Item_Tax $tax_item The WooCommerce order tax item.
		 */
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			if ( key( $order_item->get_taxes()['total'] ) === $rate_id ) {
				return (string) round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] );
			}
		}
		return 0;
	}

	/**
	 * Gets the item price including vat.
	 *
	 * @param object $order WooCommerce order.
	 * @param object $order_item The order item.
	 * @return string
	 */
	public static function get_total_refunded_for_item_incl_vat( $order, $order_item ) {
		$total_tax_refunded_for_item = self::get_total_tax_refunded_for_item( $order, $order_item ); // returned negative.
		$total_refunded_for_item     = -$order->get_total_refunded_for_item( $order_item->get_id(), $order_item->get_type() ) + $total_tax_refunded_for_item; // negative number.
		return number_format( $total_refunded_for_item, 2, '.', '' );
	}

	/**
	 * Gets the item price including vat.
	 *
	 * @param object $order WooCommerce order.
	 * @param object $order_item The order item.
	 * @return string
	 */
	public static function get_total_tax_refunded_for_item( $order, $order_item ) {
		$tax_amount = 0;
		foreach ( $order->get_taxes() as $tax_item ) {
			$tax_amount += $order->get_tax_refunded_for_item( $order_item->get_id(), $tax_item->get_rate_id(), $order_item->get_type() );
		}
		return number_format( -$tax_amount, 2, '.', '' );
	}

	/**
	 * Gets the item price including vat.
	 *
	 * @param object $order_item The order item.
	 * @return string
	 */
	public static function get_item_total_incl_vat( $order_item ) {
		$items_subtotal = ( ( $order_item->get_total() + $order_item->get_total_tax() ) );
		return number_format( $items_subtotal, 2, '.', '' );
	}

	/**
	 * Gets the item price including vat.
	 *
	 * @param object $order_item The order item.
	 * @return string
	 */
	public static function get_item_total_tax( $order_item ) {
		return number_format( $order_item->get_total_tax(), 2, '.', '' );
	}

	/**
	 * Gets the reference for the order line.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_reference( $order_item ) {
		if ( 'line_item' === $order_item->get_type() ) {
			$product = $order_item['variation_id'] ? wc_get_product( $order_item['variation_id'] ) : wc_get_product( $order_item['product_id'] );
			if ( $product->get_sku() ) {
				$reference = $product->get_sku();
			} else {
				$reference = $product->get_id();
			}
		} elseif ( 'shipping' === $order_item->get_type() ) {
			$reference = $order_item->get_method_id() . ':' . $order_item->get_instance_id();
		} else {
			$reference = $order_item->get_id();
		}

		return $reference;
	}

	/**
	 * Process gift cards.
	 *
	 * @param string $order_id The WooCommerce order ID.
	 * @param object $order The WooCommerce order.
	 * @param array  $items The items about to be sent to Avarda.
	 * @return array
	 */
	public static function process_gift_cards( $order_id, $order, $items ) {
		// Smart coupons.
		$apply_before_tax = 'yes' === get_option( 'woocommerce_smart_coupon_apply_before_tax', 'no' );
		foreach ( $order->get_items( 'coupon' ) as $item_id => $coupon ) {

			$discount_type = ( new WC_Coupon( $coupon->get_name() ) )->get_discount_type();
			$discount      = ! empty( $coupon ) && method_exists( $coupon, 'get_discount' ) ? $coupon->get_discount() : $coupon['discount_amount'];
			if ( 'smart_coupon' === $discount_type && ! empty( $discount ) ) {
				if ( wc_tax_enabled() && $apply_before_tax ) {
					// The discount is applied directly to the cart item. Send gift card amount as zero for bookkeeping.
					$coupon_amount = 0;
				} else {
					$coupon_amount = $coupon->get_discount() * -1;
				}

				$coupon_amount = number_format( $coupon_amount, 2, '.', '' );
				// translators: %s is the gift card reference.
				$label        = apply_filters( 'aco_smart_coupon_gift_card_label', esc_html( sprintf( __( 'Gift card: %s', 'avarda-checkout-for-woocommerce' ), $coupon->get_code() ) ), $coupon );
				$giftcard_sku = apply_filters( 'aco_smart_coupon_gift_card_sku', esc_html( $coupon->get_id() ), $coupon );
				$gift_card    = array(
					'notes'       => $giftcard_sku,
					'description' => substr( $label, 0, 35 ),
					'quantity'    => 1,
					'amount'      => $coupon_amount,
					'taxCode'     => 0,
					'taxAmount'   => 0,
				);

				$items[] = $gift_card;
			}
		}

		return $items;
	}
}
