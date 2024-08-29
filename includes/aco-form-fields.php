<?php
/**
 * Settings form fields for the gateway.
 *
 * @package Avarda_Checkout/Includes
 */

$token_suggestion = ACO_API_Registry::get_token_suggestion();
$token_suggestion = "<code>{$token_suggestion}</code>";

$settings = array(
	'enabled'                      => array(
		'title'   => __( 'Enable/Disable', 'avarda-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable ' . $this->method_title, 'avarda-checkout-for-woocommerce' ), // phpcs:ignore
		'default' => 'yes',
	),
	'title'                        => array(
		'title'       => __( 'Title', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'avarda-checkout-for-woocommerce' ),
		'default'     => __( $this->method_title, 'avarda-checkout-for-woocommerce' ), // phpcs:ignore
		'desc_tip'    => true,
	),
	'description'                  => array(
		'title'       => __( 'Description', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'textarea',
		'default'     => __( 'Pay with Avarda via invoice, card and direct bank payments.', 'avarda-checkout-for-woocommerce' ),
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'avarda-checkout-for-woocommerce' ),
	),
	'select_another_method_text'   => array(
		'title'       => __( 'Other payment method button text', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Avarda Checkout. Leave blank to use the default (and translatable) text.', 'avarda-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'checkout_flow'                => array(
		'title'       => __( 'Checkout flow', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'select',
		'options'     => array(
			'embedded' => __( 'Embedded', 'avarda-checkout-for-woocommerce' ),
			'redirect' => __( 'Redirect', 'avarda-checkout-for-woocommerce' ),
		),
		'description' => __( 'Select how Avarda Checkout should be integrated in WooCommerce. <strong>Embedded</strong> – the checkout is embedded in the WooCommerce checkout page and partially replaces the checkout form. <strong>Redirect</strong> – the customer is redirected to WooCommerce order pay page where the Avarda Checkout is displayed.', 'avarda-checkout-for-woocommerce' ),
		'default'     => 'embedded',
		'desc_tip'    => false,
	),
	'testmode'                     => array(
		'title'   => __( 'Testmode', 'avarda-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Avarda Checkout testmode', 'avarda-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'age_validation'               => array(
		'title'       => __( 'Age validation', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'number',
		'description' => __( 'Enter an age if you only want to offer purchases from customers older than the specified age. Leave blank or set to 0 to disable age validation.', 'avarda-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => false,
	),
	'order_management'             => array(
		'title'   => __( 'Enable Order Management', 'avarda-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Avarda order capture on WooCommerce order completion and Avarda order cancellation on WooCommerce order cancellation', 'avarda-checkout-for-woocommerce' ),
		'default' => 'yes',
	),
	'debug'                        => array(
		'title'       => __( 'Debug Log', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'avarda-checkout-for-woocommerce' ),
		'default'     => 'no',
		'description' => __( 'Log ' . $this->method_title . ' events in the WooCommerce status logs', 'avarda-checkout-for-woocommerce' ), // phpcs:ignore
	),
	'payment_gateway_icon'         => array(
		'title'       => __( 'Payment gateway icon', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter an URL to the icon you want to display for the payment method. Use <i>default</i> to display the Avarda logo. Leave blank to not show an icon at all.', 'avarda-checkout-for-woocommerce' ),
		'default'     => 'default',
		'desc_tip'    => false,
	),
	'payment_gateway_icon_width'   => array(
		'title'       => __( 'Payment gateway icon width', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'number',
		'description' => __( 'Specify the max width (in px) of the payment gateway icon.', 'avarda-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'two_column_checkout'          => array(
		'title'   => __( 'Two column checkout layout', 'avarda-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Two column checkout layout', 'avarda-checkout-for-woocommerce' ),
		'default' => 'yes',
	),
	// Integrated shipping settings.
	'integrated_shipping_settings' => array(
		'title' => __( 'Shipping', 'avarda-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'integrated_shipping'          => array(
		'title'       => __( 'Integrated Shipping', 'avarda-checkout-for-woocommerce' ),
		'type'        => 'select',
		'description' => __( 'Select which type of shipping you want to integrate in Avarda Checkout. This will display the shipping methods for the customer inside the Avarda Checkout instead of the WooCommerce order summary.', 'avarda-checkout-for-woocommerce' ),
		'options'     => array(
			''            => __( 'None', 'avarda-checkout-for-woocommerce' ),
			'woocommerce' => __( 'WooCommerce Shipping', 'avarda-checkout-for-woocommerce' ),
			'avarda'      => __( 'Partner Shipping', 'avarda-checkout-for-woocommerce' ),
		),
		'default'     => '',
	),
	'shipping_broker_api_key'      => array(
		'title'             => __( 'Shipping Broker API Key', 'avarda-checkout-for-woocommerce' ),
		'type'              => 'password',
		// translators: %s: token suggestion.
		'description'       => sprintf( __( 'Enter the API Key you wish to use for the WooCommerce shipping integration. This will be used by Avarda to communicate with your store to handle the shipping session. You can use this key that has been generated from your store: %s', 'avarda-checkout-for-woocommerce' ), $token_suggestion ),
		'default'           => '',
		'desc_tip'          => false,
		'custom_attributes' => array(
			'autocomplete' => 'off',
		),
	),
	// SE.
	'credentials_se'               => array(
		'title' => 'API Credentials Sweden',
		'type'  => 'title',
	),
	'merchant_id_se'               => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_se'                   => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	// NO.
	'credentials_no'               => array(
		'title' => 'API Credentials Norway',
		'type'  => 'title',
	),
	'merchant_id_no'               => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_no'                   => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	// DK.
	'credentials_dk'               => array(
		'title' => 'API Credentials Denmark',
		'type'  => 'title',
	),
	'merchant_id_dk'               => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_dk'                   => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	// FI.
	'credentials_fi'               => array(
		'title' => 'API Credentials Finland',
		'type'  => 'title',
	),
	'merchant_id_fi'               => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_fi'                   => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	// International.
	'credentials_international'    => array(
		'title' => 'API Credentials International',
		'type'  => 'title',
	),
	'merchant_id_int'              => array(
		'title'    => __( 'Client ID', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
	'api_key_int'                  => array(
		'title'    => __( 'Client Secret', 'avarda-checkout-for-woocommerce' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),
);
return apply_filters( 'aco_settings', $settings );
