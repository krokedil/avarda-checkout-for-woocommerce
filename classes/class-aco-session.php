<?php
/**
 * Class for handling the checkout sessions with Avarda Checkout.
 *
 * @package Avarda_Checkout/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * ACO_Session class.
 */
class ACO_Session {
	/**
	 * Avarda order returned from the Avarda API.
	 *
	 * @var array
	 */
	protected $avarda_order;

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	private function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Nope', 'avarda-checkout-for-woocommerce' ), '1.0' );
	}
	/**
	 * Public unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Nope', 'avarda-checkout-for-woocommerce' ), '1.0' );
	}

	/**
	 * Get the Avarda order. If it's not set, try to set it, and if that fails, return false.
	 *
	 * @return array|WP_Error
	 */
	public function get_avarda_order() {
		if ( ! $this->avarda_order ) {
			$purchase_id = aco_get_purchase_id_from_session();

			if ( ! $purchase_id ) {
				return new WP_Error( 'avarda_checkout_missing_purchase_id', __( 'Missing Avarda Checkout purchase id.', 'avarda-checkout-for-woocommerce' ) );
			}

			ACO_WC()->api->request_get_payment( $purchase_id );
		}

		return $this->avarda_order;
	}

	/**
	 * Set the Avarda order.
	 *
	 * @param array $avarda_order Avarda order.
	 *
	 * @return void
	 */
	public function set_avarda_order( $avarda_order ) {
		$this->avarda_order = $avarda_order;
	}

	/**
	 * Update the Avarda order items after a update request has been successfully made.
	 *
	 * @param array $items Avarda order.
	 *
	 * @return void
	 */
	public function update_avarda_order_items( $items ) {
		if ( ! $this->avarda_order ) {
			$this->get_avarda_order();
		}

		$this->avarda_order['items'] = $items;
	}
}
