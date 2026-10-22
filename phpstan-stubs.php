<?php
/**
 * Class stubs for PHPStan static analysis only.
 *
 * Declares third-party classes the plugin guards with class_exists(), which no stub package provides.
 *
 * @package Avarda_Checkout
 *
 * @phpcs:disable
 */

/**
 * The Webshipper plugin's API client.
 */
class WebshipperAPI {
	/**
	 * @param string $rate_id      The shipping rate ID.
	 * @param string $address      The street address.
	 * @param string $zip          The postcode.
	 * @param string $city         The city.
	 * @param string $country_code The country code.
	 * @return mixed
	 */
	public function searchDropPoint( $rate_id, $address, $zip, $city, $country_code ) {}
}
