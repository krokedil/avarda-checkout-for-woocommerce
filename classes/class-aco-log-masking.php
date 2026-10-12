<?php
/**
 * Class for masking sensitive data out of the plugin log.
 *
 * @package Avarda_Checkout/Classes
 */

defined( 'ABSPATH' ) || exit;

use KrokedilAvardaDeps\Krokedil\WpApi\FieldMasker;
use KrokedilAvardaDeps\Krokedil\WpApi\KeyMasker;
use KrokedilAvardaDeps\Krokedil\WpApi\Masking;

/**
 * What the plugin masks out of its logs.
 */
class ACO_Log_Masking {
	/**
	 * The slug passed to the krokedil_wp_api_mask_log_data filter.
	 */
	const SLUG = 'avarda-checkout-for-woocommerce';

	/**
	 * The address fields that are kept readable in the logs.
	 */
	const ADDRESS_KEPT = array( 'zip', 'city', 'country', 'type', 'viewType', 'isCityResolvedFromZipCode', 'phoneCountryTwoLetterIso' );

	/**
	 * The key names that are masked wherever they appear in the logs.
	 *
	 * @var string[]
	 */
	private static $key_names = array(
		'firstName',
		'lastName',
		'address1',
		'address2',
		'email',
		'dateOfBirth',
		'identificationNumber',
		'socialSecurityNumber',
		'jwt',
		// The shipping module in Modules/NamedModules uses snake case.
		'first_name',
		'last_name',
		'identification_number',
	);

	/**
	 * Widen the package key name masking with the names Avarda uses.
	 *
	 * @return void
	 */
	public static function register() {
		KeyMasker::add_keys( self::$key_names );
	}

	/**
	 * The rules for an Avarda request body.
	 *
	 * @return array
	 */
	public static function body_fields() {
		return array(
			'invoicingAddress' => array( 'keep' => self::ADDRESS_KEPT ),
			'deliveryAddress'  => array( 'keep' => self::ADDRESS_KEPT ),
			'userInputs'       => 'mask',
			'search_address'   => array( 'keep' => array( 'city', 'postal_code', 'country' ) ),
			'customer'         => 'mask',
			'customerInfo'     => 'mask',
			// The attachment carries the WooCommerce session customer id used to restore the cart.
			'attachment'       => 'mask',
		);
	}

	/**
	 * The rules for an Avarda response body.
	 *
	 * @return array
	 */
	public static function response_fields() {
		return self::body_fields() + array( 'jwt' => 'mask' );
	}

	/**
	 * The rules for a whole set of request args.
	 *
	 * @return array
	 */
	public static function request_fields() {
		return array(
			'headers' => array( 'Authorization' ),
			'body'    => self::body_fields(),
		);
	}

	/**
	 * Whether the logs are masked, see the krokedil_wp_api_mask_log_data filter.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return Masking::is_enabled( self::SLUG );
	}

	/**
	 * Mask a set of request args.
	 *
	 * @param array $request_args The request args.
	 * @return array|string The masked args, or the failure marker.
	 */
	public static function mask_request( $request_args ) {
		if ( ! self::is_enabled() ) {
			return $request_args;
		}

		try {
			if ( isset( $request_args['body'] ) && is_string( $request_args['body'] ) ) {
				$decoded              = json_decode( $request_args['body'], true );
				$request_args['body'] = is_array( $decoded ) ? $decoded : $request_args['body'];
			}

			return KeyMasker::mask( FieldMasker::mask( $request_args, self::request_fields() ) );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask a decoded response body.
	 *
	 * @param array|string $body The decoded response body, or a message in its place.
	 * @return array|string The masked body, or the failure marker.
	 */
	public static function mask_response( $body ) {
		if ( empty( $body ) || ! self::is_enabled() ) {
			return $body;
		}

		try {
			return KeyMasker::mask( is_array( $body ) ? FieldMasker::mask( $body, self::response_fields() ) : $body );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}
}
