<?php
/**
 * WooCommerce status page extension
 *
 * @package  ACO/Classes
 * @category Class
 * @author   Krokedil
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Class for WooCommerce status page.
 */
class ACO_Status {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_system_status_report', array( $this, 'add_status_page_box' ) );
	}

	/**
	 * Adds status page box for Avarda Checkout.
	 *
	 * @return void
	 */
	public function add_status_page_box() {
		include_once AVARDA_CHECKOUT_PATH . '/includes/admin/views/status-report.php';
	}
}
$aco_status = new ACO_Status();
