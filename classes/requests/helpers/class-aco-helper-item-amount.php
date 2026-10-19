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
