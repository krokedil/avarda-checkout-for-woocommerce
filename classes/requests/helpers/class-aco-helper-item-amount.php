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
 * Converts WooCommerce line totals into an item representation that Avarda can reproduce exactly.
 *
 * Avarda derives the order total from amount * quantity, where amount is the price of a single
 * item including tax. @see https://docs.avarda.com/checkout-3/api-reference/checkout-3-api-types/
 *
 * WooCommerce rounds tax at line level, so a line total divided by its quantity is not always
 * representable in two decimals. A line of 5 x 4.31 at 24% VAT totals 26.72 including tax, which
 * gives 5.344 per item - and neither 5.34 nor 5.35 multiplied by 5 returns 26.72. The per item
 * value is not even stable, since WooCommerce re-rounds the line tax for every quantity.
 *
 * When the per item amounts round trip exactly we keep them together with the quantity. When they
 * do not, the line is sent as a single item carrying the exact line total, which is the same
 * representation the refund endpoint already uses. Lines with a fractional quantity always take
 * that route, since the quantity field cannot express one.
 */
class ACO_Helper_Item_Amount {

	/**
	 * Builds the description, amount, taxAmount and quantity for an order line.
	 *
	 * @param string $description The untruncated item description.
	 * @param float  $total_incl_tax The line total including tax.
	 * @param float  $total_tax The line tax total.
	 * @param int    $quantity The line quantity.
	 * @return array
	 */
	public static function get_item( $description, $total_incl_tax, $total_tax, $quantity ) {
		$raw_quantity = floatval( $quantity );
		$is_whole     = floor( $raw_quantity ) === $raw_quantity;
		$quantity     = max( 1, intval( $quantity ) );
		$total_minor  = self::to_minor_units( $total_incl_tax );
		$tax_minor    = self::to_minor_units( $total_tax );

		// A fractional quantity cannot be carried by the quantity field, so such a line has to be
		// collapsed even when the per item amounts would divide evenly.
		if ( $quantity > 1 && $is_whole ) {
			$unit_minor     = intval( round( $total_minor / $quantity ) );
			$unit_tax_minor = intval( round( $tax_minor / $quantity ) );

			// Only keep the quantity if Avarda can reproduce both line totals from the per item amounts.
			if ( $unit_minor * $quantity === $total_minor && $unit_tax_minor * $quantity === $tax_minor ) {
				return array(
					'description' => self::truncate_description( $description ),
					'amount'      => self::from_minor_units( $unit_minor ),
					'taxAmount'   => self::from_minor_units( $unit_tax_minor ),
					'quantity'    => $quantity,
				);
			}
		}

		// The per item amounts would lose money, so send the line as a single exact item. Keep the
		// quantity visible in the description, since it is no longer carried by the quantity field.
		// This covers fractions below one too, such as 0.5 for goods sold by weight. Quantities that
		// are zero or negative are left out, as prefixing those would only describe the line wrongly.
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
	 * Truncates a description to the length Avarda accepts for the field, which their API
	 * reference documents as 35 characters.
	 *
	 * Counting characters rather than bytes keeps a multibyte character from being cut in half,
	 * which would otherwise produce invalid UTF-8 in the request body.
	 *
	 * @param string $description The untruncated description.
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
