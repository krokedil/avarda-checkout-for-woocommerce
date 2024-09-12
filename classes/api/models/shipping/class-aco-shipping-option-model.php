<?php
/**
 * Shipping option model class for the shipping broker api.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package Avarda_Checkout/Classes/API/Models/Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_Shipping_Option_Model
 */
class ACO_Shipping_Option_Model {
	/**
	 * The shipping method.
	 *
	 * @var string
	 */
	public $shippingMethod;

	/**
	 * The delivery type of the shipping option.
	 *
	 * @var string
	 */
	public $deliveryType;

	/**
	 * The carrier for the shipping option.
	 *
	 * @var string
	 */
	public $carrier;

	/**
	 * The shipping product for the shipping option.
	 *
	 * @var string
	 */
	public $shippingProduct;

	/**
	 * The price for the shipping option, including tax.
	 *
	 * @var float
	 */
	public $price;

	/**
	 * The currency for the price.
	 *
	 * @var string
	 */
	public $currency;

	/**
	 * The icon url for the shipping option.
	 *
	 * @var string
	 */
	public $iconUrl;

	/**
	 * The pickup points for the shipping option.
	 *
	 * @var array
	 */
	public $pickupPoints;

	/**
	 * Description for the shipping option if any.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Instantiate a shipping option model from a shipping rate.
	 *
	 * @param WC_Shipping_Rate $shipping_rate The shipping rate.
	 *
	 * @return ACO_Shipping_Option_Model
	 */
	public static function from_shipping_rate( $shipping_rate ) {
		$option = new self();

		$option->shippingMethod  = $shipping_rate->get_id();
		$option->deliveryType    = 'mailbox';
		$option->carrier         = self::get_shipping_method_carrier( $shipping_rate );
		$option->iconUrl         = self::get_shipping_method_icon( $option->carrier, $shipping_rate );
		$option->shippingProduct = $shipping_rate->get_label();
		$option->price           = floatval( $shipping_rate->get_cost() ) + array_sum( $shipping_rate->get_taxes() );
		$option->currency        = get_woocommerce_currency();
		$option->description     = self::get_shipping_method_description( $shipping_rate );

		// Pickup points.
		self::set_pickup_points( $option, $shipping_rate );

		return $option;
	}

	/**
	 * Set pickup points for the shipping method.
	 *
	 * @param object           $option ACO_Shipping_Option_Model.
	 * @param WC_Shipping_Rate $shipping_rate The shipping method rate from WooCommerce.
	 */
	private static function set_pickup_points( &$option, $shipping_rate ) {
		// Get any pickup points for the shipping method.
		$pickup_points         = ACO_WC()->pickup_points->get_pickup_points_from_rate( $shipping_rate ) ?? array();
		$selected_pickup_point = ACO_WC()->pickup_points->get_selected_pickup_point_from_rate( $shipping_rate );
		// Loop through the pickup points and set the pickup point data.
		$secondary_options = array();
		foreach ( $pickup_points as $pickup_point ) {
			// If the id is empty, skip.
			if ( empty( $pickup_point->get_id() ) ) {
				continue;
			}

			$secondary_options[] = array(
				'MerchantReference'   => $pickup_point->get_id(),
				'SelectedPickupPoint' => $selected_pickup_point->id === $pickup_point->get_id() ? true : false,
				'DisplayName'         => $pickup_point->get_name(),
				'Descriptions'        => array( // Can max have 3 lines.
					trim( mb_substr( $pickup_point->get_address()->get_street(), 0, 100 ) ),
					trim( mb_substr( $pickup_point->get_address()->get_postcode() . ' ' . $pickup_point->get_address()->get_city(), 0, 100 ) ),
					trim( mb_substr( $pickup_point->get_description(), 0, 100 ) ),
				),
				'Coordinates'         => array(
					'Lat' => $pickup_point->get_coordinates()->get_latitude(),
					'Lng' => $pickup_point->get_coordinates()->get_longitude(),
				),
				'DeliveryDateInfo'    => array(
					'DateStart' => $pickup_point->get_eta()->get_utc(),
				),
			);
		}
		if ( ! empty( $secondary_options ) ) {
			$option->pickupPoints = $secondary_options;
		}
	}

	/**
	 * Get the icon for the shipping method.
	 *
	 * @param string           $carrier The carrier for the shipping method.
	 * @param WC_Shipping_Rate $shipping_rate The shipping rate.
	 *
	 * @return string
	 */
	private static function get_shipping_method_icon( $carrier, $shipping_rate ) {
		$img_url = self::get_instance_setting( $shipping_rate, 'aco_icon_url' );

		// If its not null, use the icon url.
		if ( ! empty( $img_url ) ) {
			return apply_filters( 'aco_shipping_icon', $img_url, $carrier, $shipping_rate );
		}

		$base_url = AVARDA_CHECKOUT_URL . '/assets/images/shipping';
		switch ( strtolower( $carrier ) ) {
			case 'postnord':
			case 'plab':
				$img_url = "$base_url/icon-postnord.svg";
				break;
			case 'dhl':
			case 'dhl Freight': // Maybe use a separate icon for DHL Freight in the future.
				$img_url = "$base_url/icon-dhl.svg";
				break;
			case 'budbee':
				$img_url = "$base_url/icon-budbee.svg";
				break;
			case 'instabox':
				$img_url = "$base_url/icon-instabox.svg";
				break;
			case 'schenker':
				$img_url = "$base_url/icon-db-schenker.svg";
				break;
			case 'bring':
				$img_url = "$base_url/icon-bring.svg";
				break;
			case 'ups':
				$img_url = "$base_url/icon-ups.svg";
				break;
			case 'fedex':
				$img_url = "$base_url/icon-fedex.svg";
				break;
			case 'local_pickup':
				$img_url = "$base_url/icon-store.svg";
				break;
			case 'deliverycheckout':
				$img_url = "$base_url/icon-neutral.svg";
				break;
			default:
				$img_url = "$base_url/icon-neutral.svg";
				break;
		}

		return apply_filters( 'aco_shipping_icon', $img_url, $carrier, $shipping_rate );
	}

	/**
	 * Get the carrier for the shipping method.
	 *
	 * @param WC_Shipping_Rate $shipping_rate The shipping rate.
	 *
	 * @return string
	 */
	private static function get_shipping_method_carrier( $shipping_rate ) {
		$carrier = apply_filters( 'aco_shipping_method_carrier', '', $shipping_rate );

		if ( ! empty( $carrier ) ) {
			return $carrier;
		}

		if ( $shipping_rate->get_method_id() === 'local_pickup' ) {
			return 'local_pickup';
		}

		$meta_data = $shipping_rate->get_meta_data();
		$carrier   = $meta_data['carrier'] ?? $meta_data['udc_carrier_id'] ?? '';

		return $carrier;
	}

	/**
	 * Get the description for the shipping method if it exists.
	 *
	 * @param WC_Shipping_Rate $shipping_rate The shipping rate.
	 *
	 * @return string
	 */
	private static function get_shipping_method_description( $shipping_rate ) {
		$description = self::get_instance_setting( $shipping_rate, 'aco_description' );
		return apply_filters( 'aco_shipping_method_description', $description, $shipping_rate );
	}

	/**
	 * Get the instance settings for a shipping rate.
	 *
	 * @param WC_Shipping_Rate $shipping_rate The shipping rate.
	 * @param string           $option The option to get.
	 *
	 * @return mixed
	 */
	private static function get_instance_setting( $shipping_rate, $option ) {
		// Get the shipping rates id.
		$method_id   = $shipping_rate->get_method_id();
		$instance_id = $shipping_rate->get_instance_id();

		// Get the shipping method class.
		$shipping_classes = WC()->shipping->get_shipping_method_class_names();

		// Ensure the class exists.
		if ( ! isset( $shipping_classes[ $method_id ] ) || ! class_exists( $shipping_classes[ $method_id ] ) ) {
			return null;
		}

		try {
			/**
			 * Get a new instance of the shipping method.
			 *
			 * @var WC_Shipping_Method $instance The shipping method instance.
			 */
			$instance = new $shipping_classes[ $method_id ]( $instance_id );
			return $instance->get_instance_option( $option );
		} catch ( Exception $e ) {
			return null;
		}
	}
}
