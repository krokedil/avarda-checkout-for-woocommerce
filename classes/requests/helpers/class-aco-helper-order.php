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
			$formatted_order_item = $this->get_order_item( $order, $order_item );
			if ( is_array( $formatted_order_item ) ) {
				$formated_order_items[] = $formatted_order_item;
			}
		}

		// Get order fees.
		$order_fees = $order->get_fees();
		foreach ( $order_fees as $fee ) {
			$formated_order_items[] = $this->get_fee( $order, $fee );
		}

		// Get order shipping.
		if ( $order->get_shipping_method() ) {
			$shipping = $this->get_shipping( $order );
			if ( null !== $shipping ) {
				$formated_order_items[] = $shipping;
			}
		}

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

		$order_line_quantity              = intval( $order_item->get_quantity() - abs( $order->get_qty_refunded_for_item( $order_item->get_id() ) ) );
		$total_refunded_for_item_incl_vat = self::get_total_refunded_for_item_incl_vat( $order, $order_item );
		$total_tax_refunded_for_item      = self::get_total_tax_refunded_for_item( $order, $order_item );
		$item_total_incl_vat              = self::get_item_total_incl_vat( $order_item );
		$item_total_tax                   = self::get_item_total_tax( $order_item );
		$item_price_incl_vat              = self::get_item_price_incl_vat( $order_item );
		$item_tax_amount                  = self::get_item_tax_amount( $order_item );

		// Don't add order item if quantity is 0 and refunded amount is the same as order line total.
		// This means that we have refunded (released the reservation) the entire order line alreadys.
		if ( empty( $order_line_quantity ) && $total_refunded_for_item_incl_vat === $item_total_incl_vat ) {
			return false;
		}

		// If the order line has been refunded (reservation has been released) but not the entire order line amount (e.g. goodwiil refund).
		if ( empty( $order_line_quantity ) && $total_refunded_for_item_incl_vat !== $item_total_incl_vat ) {
			// Let's change this to one item and activate the remaining order line amount and tax amount.
			$order_line_quantity = 1;
			$item_price_incl_vat = number_format( $item_total_incl_vat - $total_refunded_for_item_incl_vat, 2, '.', '' );
			$item_tax_amount     = number_format( $item_total_tax - $total_tax_refunded_for_item, 2, '.', '' );
		}

		// @todo - add logic for situations where the order line has a quantity > 1 and a good will refund has been performed.
		// Also - can a good will refund be done in Woo by adding a price but keep the quantity to 0?

		return array(
			'description' => substr( $this->get_product_name( $order_item ), 0, 35 ), // String.
			'notes'       => substr( $this->get_product_sku( $product ), 0, 35 ), // String.
			'amount'      => $item_price_incl_vat, // Float.
			'taxCode'     => $this->get_product_tax_code( $order, $order_item ), // Float.
			'taxAmount'   => $item_tax_amount, // Float.
			'quantity'    => $order_line_quantity,
		);
	}

	/**
	 * Gets the product name.
	 *
	 * @param object $order_item The order item.
	 * @return string
	 */
	public function get_product_name( $order_item ) {
		$item_name = $order_item->get_name();
		return strip_tags( $item_name );
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
	 * @param object $order WooCommerce order.
	 * @param object $fee A WooCommerce Fee.
	 * @return array
	 */
	public function get_fee( $order, $fee ) {
		return array(
			'description' => substr( $fee->get_name(), 0, 35 ), // String.
			'notes'       => substr( $fee->get_id(), 0, 35 ), // String.
			'amount'      => self::get_item_price_incl_vat( $fee ), // String.
			'taxCode'     => self::get_tax_rate( $order, $fee ), // String.
			'taxAmount'   => self::get_item_tax_amount( $fee ), // String.
		);
	}

	/**
	 * Formats the shipping.
	 *
	 * @param WC_Order $order WC order.
	 * @return array
	 */
	public function get_shipping( $order ) {
		if ( $order->get_shipping_total() > 0 ) {
			return array(
				'description' => substr( $order->get_shipping_method(), 0, 35 ), // String.
				'notes'       => substr( __( 'Shipping', 'avarda-checkout-for-woocommerce' ), 0, 35 ), // String.
				'amount'      => number_format( $order->get_shipping_total() + $order->get_shipping_tax(), 2, '.', '' ), // String.
				'taxCode'     => ( '0' !== $order->get_shipping_tax() ) ? $this->get_product_tax_code( $order, current( $order->get_items( 'shipping' ) ) ) : 0, // Float.
				'taxAmount'   => number_format( $order->get_shipping_tax(), 2, '.', '' ),
			);
		} else {
			return array(
				'description' => substr( $order->get_shipping_method(), 0, 35 ), // String.
				'notes'       => substr( __( 'Shipping', 'avarda-checkout-for-woocommerce' ), 0, 35 ), // String.
				'amount'      => 0, // Float.
				'taxCode'     => '0', // String.
				'taxAmount'   => 0, // Float.
			);
		}
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
		$total_tax_refunded_for_item = self::get_total_tax_refunded_for_item( $order, $order_item );
		$total_refunded_for_item     = $order->get_total_refunded_for_item( $order_item->get_id() ) + $total_tax_refunded_for_item;
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
			$tax_amount += $order->get_tax_refunded_for_item( $order_item->get_id(), $tax_item->get_rate_id() );
		}
		return number_format( $tax_amount, 2, '.', '' );
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
}
