<?php // phpcs:ignore
/**
 * Item amount helper class.
 *
 * @package Avarda_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Formats line amounts so that Avarda reproduces WooCommerce's line totals exactly.
 */
class ACO_Helper_Item_Amount {

	/**
	 * Gets the description, amount, taxAmount and quantity for a line.
	 *
	 * WooCommerce rounds tax per line, so the line is sent as a single item when per item amounts can't add up to it.
	 *
	 * @param string    $description The item description.
	 * @param float     $total_incl_tax The line total including tax.
	 * @param float     $total_tax The line tax total.
	 * @param float|int $quantity The line quantity.
	 * @return array
	 */
	public static function get_item( $description, $total_incl_tax, $total_tax, $quantity ) {
		$raw_quantity = floatval( $quantity );
		$quantity     = intval( $raw_quantity );
		$total_minor  = self::to_minor_units( $total_incl_tax );
		$tax_minor    = self::to_minor_units( $total_tax );

		// Keep the quantity only for whole quantities whose per item amounts multiply back exactly.
		if ( $quantity > 1 && floatval( $quantity ) === $raw_quantity && 0 === $total_minor % $quantity && 0 === $tax_minor % $quantity ) {
			return array(
				'description' => self::truncate_description( $description ),
				'amount'      => self::from_minor_units( intdiv( $total_minor, $quantity ) ),
				'taxAmount'   => self::from_minor_units( intdiv( $tax_minor, $quantity ) ),
				'quantity'    => $quantity,
			);
		}

		// Send a single item with the exact line total, keeping the quantity in the description.
		if ( $raw_quantity > 0 && 1.0 !== $raw_quantity ) {
			$description = (string) $raw_quantity . ' x ' . $description;
		}

		return array(
			'description' => self::truncate_description( $description ),
			'amount'      => self::from_minor_units( $total_minor ),
			'taxAmount'   => self::from_minor_units( $tax_minor ),
			'quantity'    => 1,
		);
	}

	/**
	 * Gets one or more rows for a line, keeping the real quantity while matching WooCommerce's line totals exactly.
	 *
	 * Whole quantities are split into at most three rows whose per item amounts differ by a cent.
	 *
	 * @param string    $description The item description.
	 * @param float     $total_incl_tax The line total including tax.
	 * @param float     $total_tax The line tax total.
	 * @param float|int $quantity The line quantity.
	 * @return array[] List of rows with description, amount, taxAmount and quantity.
	 */
	public static function get_items( $description, $total_incl_tax, $total_tax, $quantity ) {
		$raw_quantity = floatval( $quantity );
		$quantity     = intval( $raw_quantity );
		$total_minor  = self::to_minor_units( $total_incl_tax );
		$tax_minor    = self::to_minor_units( $total_tax );

		// Fractional quantities, single items and negative lines keep the single row format.
		if ( $quantity <= 1 || floatval( $quantity ) !== $raw_quantity || $total_minor < 0 || $tax_minor < 0 ) {
			return array( self::get_item( $description, $total_incl_tax, $total_tax, $raw_quantity ) );
		}

		$base_total = intdiv( $total_minor, $quantity );
		$rest_total = $total_minor % $quantity;
		$base_tax   = intdiv( $tax_minor, $quantity );
		$rest_tax   = $tax_minor % $quantity;

		// The first units get the remaining cents, so group units by which remainders they take.
		$groups = array(
			array( $base_total + 1, $base_tax + 1, min( $rest_total, $rest_tax ) ),
			array( $base_total + 1, $base_tax, max( 0, $rest_total - $rest_tax ) ),
			array( $base_total, $base_tax + 1, max( 0, $rest_tax - $rest_total ) ),
			array( $base_total, $base_tax, $quantity - max( $rest_total, $rest_tax ) ),
		);

		$items = array();
		foreach ( $groups as list( $amount, $tax, $count ) ) {
			if ( $count > 0 ) {
				$items[] = array(
					'description' => self::truncate_description( $description ),
					'amount'      => self::from_minor_units( $amount ),
					'taxAmount'   => self::from_minor_units( $tax ),
					'quantity'    => $count,
				);
			}
		}

		return $items;
	}

	/**
	 * Truncates the description to the length used by the other item fields, counting characters rather than bytes.
	 *
	 * @param string $description The description.
	 * @return string
	 */
	private static function truncate_description( $description ) {
		return rtrim( mb_substr( (string) $description, 0, 34 ) );
	}

	/**
	 * Converts a price to minor units.
	 *
	 * @param float $amount The price in major units.
	 * @return int
	 */
	private static function to_minor_units( $amount ) {
		return intval( round( floatval( $amount ) * 100 ) );
	}

	/**
	 * Formats a price in minor units as a two decimal string in major units.
	 *
	 * @param int $amount The price in minor units.
	 * @return string
	 */
	private static function from_minor_units( $amount ) {
		return number_format( $amount / 100, 2, '.', '' );
	}
}
