<?php // phpcs:ignore
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Creates Avarda refund data.
 *
 * @class    ACO_Helper_Create_Refund_Data
 * @package  Avarda_Checkout/Classes/Requests/Helpers
 * @category Class
 * @author   Krokedil <info@krokedil.se>
 */
class ACO_Helper_Create_Refund_Data {
	/**
	 * Creates refund data
	 *
	 * @param int    $order_id Order id.
	 * @param int    $refund_order_id Refund order id.
	 * @param int    $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @return array
	 */
	public static function create_refund_data( $order_id, $refund_order_id, $amount, $reason ) {
		if ( ! empty( $reason ) ) {
			$reason = " ({$reason})";
		}

		if ( empty( $refund_order_id ) ) {
			return array();
		}

		// Get refund order data.
		$refund_order      = wc_get_order( $refund_order_id );
		$refunded_items    = $refund_order->get_items();
		$refunded_shipping = $refund_order->get_items( 'shipping' );
		$refunded_fees     = $refund_order->get_items( 'fee' );

		// Set needed variables for refunds.
		$item_refund = array();

		// Item refund.
		if ( $refunded_items ) {
			foreach ( $refunded_items as $item ) {
				$original_order = wc_get_order( $order_id );
				foreach ( $original_order->get_items() as $original_order_item ) {
					if ( $item->get_product_id() == $original_order_item->get_product_id() ) {
						// Found product match, continue.
						break;
					}
				}
				array_push( $item_refund, self::get_refund_item_data( $item ) );
			}
		}

			// Shipping item refund.
		if ( $refunded_shipping ) {
			foreach ( $refunded_shipping as $shipping ) {
				$original_order = wc_get_order( $order_id );
				foreach ( $original_order->get_items( 'shipping' ) as $original_order_shipping ) {
					if ( $shipping->get_name() == $original_order_shipping->get_name() ) {
						// Found product match, continue.
						break;
					}
				}
				array_push( $item_refund, self::get_refund_shipping_data( $shipping, $original_order_shipping ) );
			}
		}

			// Fee item refund.
		if ( $refunded_fees ) {
			foreach ( $refunded_fees as $fee ) {
				$original_order = wc_get_order( $order_id );
				foreach ( $original_order->get_items( 'fee' ) as $original_order_fee ) {
					if ( $fee->get_name() == $original_order_fee->get_name() ) {
						// Found product match, continue.
						break;
					}
				}
				array_push( $item_refund, self::get_refund_fee_data( $fee ) );
			}
		}

		// update_post_meta( $refund_order_id, '_krokedil_refunded', 'true' ); Do we need?
		return $item_refund;
	}

	/**
	 * Gets refunded order
	 *
	 * @param int $order_id Order id.
	 * @return string
	 */
	public static function get_refunded_order( $order_id ) {
		$order   = wc_get_order( $order_id );
		$refunds = $order->get_refunds();
		$refund  = reset( $refunds );

		return $refund->get_id();
	}

	/**
	 * Gets a refund item object.
	 *
	 * @param WC_Order_Item $item WooCommerce Order Item.
	 * @return array
	 */
	private static function get_refund_item_data( $item ) {
		$product            = $item->get_product();
		$title              = $item->get_name();
		$sku                = empty( $product->get_sku() ) ? $product->get_id() : $product->get_sku();
		$tax_rates          = WC_Tax::get_rates( $item->get_tax_class() );
		$tax_rate           = reset( $tax_rates );
		$formatted_tax_rate = $tax_rate['rate'];
		$total_amount       = $item->get_total() + $item->get_total_tax();
		$total_tax          = $item->get_total_tax();

		return array(
			'description' => substr( $title, 0, 34 ),
			'notes'       => substr( $sku, 0, 34 ),
			'amount'      => number_format( abs( $total_amount ), 2, '.', '' ),
			'taxCode'     => strval( round( $formatted_tax_rate ) ),
			'taxAmount'   => number_format( abs( $total_tax ), 2, '.', '' ),
		);
	}

	/**
	 * Gets a refund shipping object.
	 *
	 * @param WC_Order_Item_Shipping $shipping WooCommerce Order shipping.
	 * @param WC_Order_Item_Shipping $original_order_shipping WooCommerce original order shipping.
	 * @return array
	 */
	private static function get_refund_shipping_data( $shipping, $original_order_shipping ) {
		$shipping_reference = 'Shipping';

		if ( null !== $shipping->get_instance_id() ) {
			$shipping_reference = 'shipping|' . $shipping->get_method_id() . ':' . $shipping->get_instance_id();
		} else {
			$shipping_reference = 'shipping|' . $shipping->get_method_id();
		}

		$free_shipping = false;
		if ( 0 === intval( $shipping->get_total() ) ) {
			$free_shipping = true;
		}

		$tax_rate     = ( $free_shipping ) ? 0 : $original_order_shipping->get_total_tax() / $original_order_shipping->get_total() * 100;
		$total_amount = ( $free_shipping ) ? 0 : $shipping->get_total() + $shipping->get_total_tax();
		$total_tax    = ( $free_shipping ) ? 0 : $shipping->get_total_tax();
		$title        = $shipping->get_name();
		return array(
			'description' => substr( $title, 0, 34 ),
			'notes'       => substr( $shipping_reference, 0, 34 ),
			'amount'      => number_format( abs( $total_amount ), 2, '.', '' ),
			'taxCode'     => strval( round( $tax_rate ) ),
			'taxAmount'   => number_format( abs( $total_tax ), 2, '.', '' ),
		);
	}

	/**
	 * Gets a refund fee object.
	 *
	 * @param WC_Order_Item_Fee $fee WooCommerce Order fee.
	 * @return array
	 */
	private static function get_refund_fee_data( $fee ) {
		$sku              = 'Fee';
		$invoice_fee_name = '';

		$fee_name = str_replace( ' ', '-', strtolower( $fee->get_name() ) );
		$sku      = 'fee|' . $fee_name;

		$title              = $fee->get_name();
		$tax_rates          = WC_Tax::get_rates( $fee->get_tax_class() );
		$tax_rate           = reset( $tax_rates );
		$formatted_tax_rate = $tax_rate['rate'];
		$total_amount       = $fee->get_total() + $fee->get_total_tax();
		$total_tax          = $fee->get_total_tax();

		return array(
			'description' => substr( $title, 0, 34 ),
			'notes'       => substr( $sku, 0, 34 ),
			'amount'      => number_format( abs( $total_amount ), 2, '.', '' ),
			'taxCode'     => strval( round( $formatted_tax_rate ) ),
			'taxAmount'   => number_format( abs( $total_tax ), 2, '.', '' ),
		);
	}
}
