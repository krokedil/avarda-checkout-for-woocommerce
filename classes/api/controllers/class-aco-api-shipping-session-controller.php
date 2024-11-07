<?php
/**
 * The shipping session controller class for the Avarda Checkout API.
 *
 * @package Avarda_Checkout/Classes/API/Controllers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ACO_API_Shipping_Session_Controller
 */
class ACO_API_Shipping_Session_Controller extends ACO_API_Controller_Base {
	/**
	 * The path of the controller.
	 *
	 * @var string
	 */
	protected $path = 'shipping';
	/**
	 * Register the routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Register the create session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'create-session' ),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_session' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);

		// Register the update session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'update-session/(?P<id>[a-zA-Z0-9-]+)' ),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_session' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);

		// Register the complete session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'complete-session/(?P<id>[a-zA-Z0-9-]+)' ),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'complete_session' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);

		// Register the get session path.
		register_rest_route(
			$this->namespace,
			$this->get_request_path( 'get-session/(?P<id>[a-zA-Z0-9-]+)' ),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_session' ),
				'permission_callback' => array( $this, 'verify_request' ),
			)
		);
	}

	/**
	 * Get the customer session from the customer unique id.
	 *
	 * @param string $customer_id The customer unique id.
	 * @param array  $body The body from the Avarda request.
	 *
	 * @return array
	 */
	private function get_customer_session( $customer_id, $body ) {
		$this->setup_customer_session( $customer_id, $body );

		$shipping                = WC()->session->get( 'shipping_for_package_0' ) ?? array();
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' ) ?? array();

		return array(
			'shipping'               => $shipping,
			'chosen_shipping_method' => is_array( $chosen_shipping_methods ) ? reset( $chosen_shipping_methods ) : '',
		);
	}

	/**
	 * Setup the session data for the customer.
	 *
	 * @param string $customer_id The customer unique id.
	 * @param array  $avarda_order The order from Avarda.
	 *
	 * @return void
	 */
	private function setup_customer_session( $customer_id, $avarda_order ) {
		if ( null !== WC()->session && method_exists( WC()->session, 'get_session' ) ) {
			$session = WC()->session->get_session( $customer_id );
		} else {
			WC()->session = new WC_Session_Handler();
			$session      = WC()->session->get_session( $customer_id );
		}

		$customer_address = aco_format_address_data( $avarda_order );
		$customer         = array();

		// Loop the session and set it as the current user session data.
		foreach ( $session as $key => $value ) {
			$value = maybe_unserialize( $value );

			// If its the customer session, set the address data from the body.
			if ( 'customer' === $key ) {
				$customer = self::process_customer_address( $value, $customer_address );
			}

			WC()->session->set( $key, $value );
		}

		// Set the customer from the customer session data.
		WC()->customer = new WC_Customer( $session['customer_id'] ?? 0, true );

		// Set the customers shipping location, use shipping address if it exists, otherwise use billing address.
		// phpcs:disable Universal.Operators.DisallowShortTernary
		WC()->customer->set_shipping_location(
			$customer['shipping_country'] ?: $customer['customer'],
			$customer['shipping_state'] ?: $customer['state'],
			$customer['shipping_postcode'] ?: $customer['postcode'],
			$customer['shipping_city'] ?: $customer['city']
		);
		// phpcs:enable Universal.Operators.DisallowShortTernary

		// Calculate shipping for the session.
		WC()->cart = new WC_Cart();
		WC()->cart->get_cart_from_session();
		WC()->cart->calculate_shipping();
	}

	/**
	 * Create or update a shipping session.
	 *
	 * @param array $avarda_order The order from Avarda.
	 *
	 * @return ACO_Shipping_Session_Model|null
	 */
	private function create_or_update_session( $avarda_order ) {
		if ( empty( $avarda_order ) ) {
			return null;
		}

		$purchase_id         = $avarda_order['purchaseId'] ?? '';
		$attachments         = json_decode( $avarda_order['extraIdentifiers']['attachment'], true ) ?? array();
		$shipping_attachment = $attachments['shipping'] ?? array();

		if ( empty( $purchase_id ) || empty( $attachments ) || empty( $shipping_attachment ) || ! isset( $shipping_attachment['customerId'] ) ) {
			return null;
		}

		$wc_session = $this->get_customer_session( $attachments['shipping']['customerId'], $avarda_order );
		$session    = ACO_Shipping_Session_Model::from_shipping_rates( $wc_session['shipping']['rates'] ?? array(), $wc_session['chosen_shipping_method'], $purchase_id );

		return $session;
	}

	/**
	 * Process the address from Avarda.
	 *
	 * @param array $address The address from Avarda.
	 *
	 * @return array
	 */
	private function process_address( $address ) {
		return array(
			'destination' => array(
				'country'   => $address['country'] ?? '',
				'state'     => $address['state'] ?? '',
				'postcode'  => $address['zip'] ?? '',
				'city'      => $address['city'] ?? '',
				'address'   => $address['address1'] ?? '',
				'address_1' => $address['address1'] ?? '',
				'address_2' => $address['address2'] ?? '',
			),
		);
	}

	/**
	 * Process the items from Avarda.
	 *
	 * @param array $items The items from Avarda.
	 *
	 * @return array
	 */
	private function process_items( $items ) {
		$cart_total    = 0;
		$content_total = 0;
		$contents      = array();

		// Loop each item in the order and set the total amount, and the content needed for the shipping.
		foreach ( $items as $item ) {
			$cart_total += $item['amount'] * $item['quantity'];
			$product     = wc_get_product( wc_get_product_id_by_sku( $item['notes'] ) ) ?? wc_get_product( $item['notes'] ) ?? null;
			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}

			$content_total += $item['amount'] * $item['quantity'];

			// Check if the product is a variation or not.
			$is_variation = $product->is_type( 'variation' );

			// Add the product to the contents array using the product id as the key.
			$contents[ $product->get_id() ] = array(
				'key'          => $product->get_id(),
				'product_id'   => $is_variation ? $product->get_parent_id() : $product->get_id(),
				'variation_id' => $is_variation ? $product->get_id() : 0,
				'quantity'     => $item['quantity'],
				'data'         => $product,
				'data_hash'    => wc_get_cart_item_data_hash( $product ),
			);
		}

		return array(
			'cart_subtotal' => $cart_total,
			'contents_cost' => $content_total,
			'contents'      => $contents,
		);
	}

	/**
	 * Create a shipping session.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return void
	 */
	public function create_session( $request ) {
		try {
			$body = $request->get_json_params();

			// Get the purchase id from the request url.
			$purchase_id = $body['purchaseId'];

			// Get the avarda order.
			$avarda_order = ACO_WC()->api->request_get_payment( $purchase_id );

			if ( is_wp_error( $avarda_order ) ) {
				$this->send_response( new WP_Error( 404, 'Not found' ) );
			}

			$session = $this->create_or_update_session( $avarda_order );

			if ( ! $session ) {
				$this->send_response( new WP_Error( 400, 'Bad request' ) );
			}

			$this->send_response( $session, 201 );
		} catch ( Exception $e ) {
			$this->send_response( new WP_Error( 500, 'Server error' ) );
		}
	}

	/**
	 * Update a shipping session.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return void
	 */
	public function update_session( $request ) {
		try {
			$body = $request->get_json_params();

			// Get the purchase id from the request url.
			$purchase_id = $body['purchaseId'] ?? $request->get_param( 'id' );

			// Get the avarda order.
			$avarda_order = ACO_WC()->api->request_get_payment( $purchase_id );

			if ( is_wp_error( $avarda_order ) ) {
				$this->send_response( new WP_Error( 404, 'Not found' ) );
			}

			$session = $this->create_or_update_session( $avarda_order );

			if ( ! $session ) {
				$this->send_response( new WP_Error( 400, 'Bad request' ) );
			}

			$this->send_response( $session, 200 );
		} catch ( Exception $e ) {
			$this->send_response( new WP_Error( 500, 'Server error' ) );
		}
	}

	/**
	 * Complete a shipping session.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return void
	 */
	public function complete_session( $request ) {
		$this->send_response( array() );
	}

	/**
	 * Get a shipping session.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return void
	 */
	public function get_session( $request ) {
		try {
			// Get the purchase id from the request url.
			$purchase_id = $request->get_param( 'id' );

			// Get the avarda order.
			$avarda_order = ACO_WC()->api->request_get_payment( $purchase_id );

			if ( is_wp_error( $avarda_order ) ) {
				$this->send_response( new WP_Error( 404, 'Not found' ) );
			}

			// Get the shipping session from the order.
			$shipping_session = $this->create_or_update_session( $avarda_order );

			if ( ! $shipping_session ) {
				$this->send_response( new WP_Error( 404, 'Not found' ) );
			}

			$this->send_response( $shipping_session );
		} catch ( Exception $e ) {
			$this->send_response( new WP_Error( 500, 'Server error' ) );
		}
	}

	/**
	 * Process the customer address data and set the correct customer data.
	 *
	 * @param array $value The customer data.
	 * @param array $address The address data.
	 *
	 * @return array
	 */
	private static function process_customer_address( $value, $address ) {
		// Set the billing address data.
		foreach ( $address['billing'] as $address_key => $address_value ) {
			// If the key is date_of_birth, continue.
			if ( 'date_of_birth' === $address_key ) {
				continue;
			}

			// Change some keys to match WooCommerce names.
			if ( 'address1' === $address_key ) {
				$address_key = 'address_1';
			} elseif ( 'address2' === $address_key ) {
				$address_key = 'address_2';
			} elseif ( 'zip' === $address_key ) {
				$address_key = 'postcode';
			}

			$value[ $address_key ] = $address_value;
		}

		// Set the shipping address data.
		foreach ( $address['shipping'] as $address_key => $address_value ) {
			// If the key is date_of_birth, continue.
			if ( 'date_of_birth' === $address_key ) {
				continue;
			}

			// If the key is address1, change to address_1.
			if ( 'address1' === $address_key ) {
				$address_key = 'address_1';
			} elseif ( 'address2' === $address_key ) {
				$address_key = 'address_2';
			} elseif ( 'zip' === $address_key ) {
				$address_key = 'postcode';
			}

			$value[ 'shipping_' . $address_key ] = $address_value;
		}

		return $value;
	}
}
