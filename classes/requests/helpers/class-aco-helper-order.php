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
			$formated_order_items[] = $this->get_order_item( $order, $order_item );
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
		return array(
			'description' => substr( $this->get_product_name( $order_item ), 0, 35 ), // String.
			'amount'      => $this->get_product_price( $order_item ), // Float.
			'taxCode'     => $this->get_product_tax_code( $order, $order_item ), // Float.
			'taxAmount'   => $this->get_product_tax_amount( $order, $order_item ), // Float.
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
	 * Gets the products price.
	 *
	 * @param object $order_item The order item.
	 * @return float
	 */
	public function get_product_price( $order_item ) {
		$items_subtotal = ( $order_item->get_total() + $order_item->get_total_tax() );
		return number_format( $items_subtotal, 2, '.', '' );
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
			'description' => substr( $fee->get_name(), 0, 35 ), // String.
			'notes'       => substr( $fee->get_id(), 0, 35 ), // String.
			'amount'      => number_format( (float) $fee->get_amount(), 2, '.', '' ), // String.
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
				return round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] * 100 );
			}
		}
		return 0;
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @param  WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee|WC_Order_Item_Coupon $order_item Order line item.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 */
	public static function get_item_tax_amount( $order_item ) {

		if ( in_array( $order_item->get_type(), array( 'line_item', 'fee', 'shipping' ), true ) ) {
			$item_tax_amount = $order_item->get_total_tax();
		} elseif ( 'coupon' === $order_item->get_type() ) {
			$item_tax_amount = $order_item->get_discount_tax();
		} else {
			$item_tax_amount = 00;
		}
		return number_format( $item_tax_amount, 2, '.', '' );
	}

}
