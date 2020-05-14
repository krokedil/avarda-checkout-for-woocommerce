<?php
class Test_ACO_Cart_Helpers extends AKrokedil_Unit_Test_Case {
	/**
	 * WooCommerce simple product.
	 *
	 * @var WC_Product
	 */
	public $simple_product = null;

	/**
	 * Tax rate ids.
	 *
	 * @var array
	 */
	public $tax_rate_ids = array();

	/**
	 * Test KCO_Request_Cart::get_product_price
	 *
	 * @return void
	 */
	public function test_get_product_price() {
		// Create tax rates.
		$this->tax_rate_ids[] = $this->create_tax_rate( '25' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '12' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '6' );
		$this->tax_rate_ids[] = $this->create_tax_rate( '0' );

		update_option( 'woocommerce_prices_include_tax', 'yes' );
		// 25% inc tax.
		$this->simple_product->set_tax_class( '25percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_total_amount_25_inc = ( new ACO_Helper_Cart() )->get_product_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 12% inc tax.
		$this->simple_product->set_tax_class( '12percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_total_amount_12_inc = ( new ACO_Helper_Cart() )->get_product_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 6% inc tax.
		$this->simple_product->set_tax_class( '6percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_total_amount_6_inc = ( new ACO_Helper_Cart() )->get_product_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 0% inc tax.
		$this->simple_product->set_tax_class( '0percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_total_amount_0_inc = ( new ACO_Helper_Cart() )->get_product_price( $cart_item );
		}
		WC()->cart->empty_cart();

		update_option( 'woocommerce_prices_include_tax', 'no' );
		// 25% exc tax.
		$this->simple_product->set_tax_class( '25percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_total_amount_25_exc = ( new ACO_Helper_Cart() )->get_product_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 12% exc tax.
		$this->simple_product->set_tax_class( '12percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_total_amount_12_exc = ( new ACO_Helper_Cart() )->get_product_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 6% exc tax.
		$this->simple_product->set_tax_class( '6percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_total_amount_6_exc = ( new ACO_Helper_Cart() )->get_product_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// 0% exc tax.
		$this->simple_product->set_tax_class( '0percent' );
		$this->simple_product->save();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$cart_items = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$item_total_amount_0_exc = ( new ACO_Helper_Cart() )->get_product_price( $cart_item );
		}
		WC()->cart->empty_cart();

		// Clear data.
		foreach ( $this->tax_rate_ids as $tax_rate_id ) {
			WC_Tax::_delete_tax_rate( $tax_rate_id );
		}
		$this->tax_rate_ids = null;

		// Assertions.
		$this->assertEquals( 100, $item_total_amount_25_inc, 'get_product_price 25% inc tax' );
		$this->assertEquals( 100, $item_total_amount_12_inc, 'get_product_price 12% inc tax' );
		$this->assertEquals( 100, $item_total_amount_6_inc, 'get_product_price 6% inc tax' );
		$this->assertEquals( 100, $item_total_amount_0_inc, 'get_product_price 0% inc tax' );

		$this->assertEquals( 125, $item_total_amount_25_exc, 'get_product_price 25% exc tax' );
		$this->assertEquals( 112, $item_total_amount_12_exc, 'get_product_price 12% exc tax' );
		$this->assertEquals( 106, $item_total_amount_6_exc, 'get_product_price 6% exc tax' );
		$this->assertEquals( 100, $item_total_amount_0_exc, 'get_product_price 0% exc tax' );
	}

	/**
	 * Force set shipping country.
	 *
	 * @return string
	 */
	public function set_shipping_country() {
		return 'SE';
	}

	/**
	 * Force set shipping postcode.
	 *
	 * @return string
	 */
	public function set_shipping_postcode() {
		return '12345';
	}

	/**
	 * Helper to create tax rates and class.
	 *
	 * @param string $rate The tax rate.
	 * @return int
	 */
	public function create_tax_rate( $rate ) {
		// Create the tax class.
		WC_Tax::create_tax_class( "${rate}percent", "${rate}percent" );

		// Set tax data.
		$tax_data = array(
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate'          => $rate,
			'tax_rate_name'     => "Vat $rate",
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 1,
			'tax_rate_class'    => "${rate}percent",
		);
		return WC_Tax::_insert_tax_rate( $tax_data );
	}

	public function create() {
		$settings = array(
			'enabled'                    => 'yes',
			'title'                      => 'Avarda Checkout',
			'description'                => '',
			'select_another_mehtod_text' => '',
			'testmode'                   => 'yes',
			'order_management'           => 'yes',
			'debug'                      => 'yes',
			'two_column_checkout'        => 'yes',
			'credentials_se'             => '',
			'merchant_id_se'             => '',
			'api_key_se'                 => '',
			'credentials_no'             => '',
			'merchant_id_no'             => '',
			'api_key_no'                 => '',
			'credentials_dk'             => '',
			'merchant_id_dk'             => '',
			'api_key_dk'                 => '',
			'credentials_fi'             => '',
			'merchant_id_fi'             => '',
			'api_key_fi'                 => '',
		);

		$this->simple_product = ( new Krokedil_Simple_Product() )->create();

		// Default settings.
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_aco_settings', $settings );
		update_option( 'woocommerce_currency', 'SEK' );
		update_option( 'woocommerce_allowed_countries', 'specific' );
		update_option( 'woocommerce_specific_allowed_countries', array( 'SE' ) );

		// Create cart.
		add_filter( 'woocommerce_customer_get_shipping_country', array( $this, 'set_shipping_country' ) );
		add_filter( 'woocommerce_customer_get_shipping_postcode', array( $this, 'set_shipping_postcode' ) );

		WC()->customer->set_billing_country( 'SE' );
	}
	public function update() {
		return;
	}
	public function view() {
		return;
	}
	public function delete() {
		$this->simple_product->delete();
		$this->simple_product = null;
	}

}
