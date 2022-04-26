=== Avarda Checkout for WooCommerce ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, avarda
Requires at least: 5.0
Tested up to: 5.9.3
Requires PHP: 7.0
WC requires at least: 4.0.0
WC tested up to: 6.4.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: 1.4.2

Avarda Checkout for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Avarda.


== DESCRIPTION ==
Avarda Checkout is an e-commerce checkout solution that gives you more than the individual purchase. The checkout is built to provide more repeat customers and an increased average order value.

To get started with Avarda Checkout you need to [sign up](https://www.avarda.com/se/foretag/) for an account.

More information on how to get started can be found in the [plugin documentation](https://docs.krokedil.com/collection/337-avarda-checkout).


== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your Avarda Checkout settings.
6. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/collection/337-avarda-checkout).


== CHANGELOG ==
= 2022.03.31        - version 1.4.2 =
* Tweak             - Adds helper function get_tax_rate and get_item_tax_amount to improve tax calculation for fees in order management.
* Fix               - Unset session and trigger reload of checkout page if GET request in process_payment function fails (usually when Avarda session has timed out).
* Fix               - Use billing address data if shipping address doesn't exist. Fixes issue where shipping first and last name might be missing when order should be created in Woo.

= 2022.03.17        - version 1.4.1 =
* Fix               - Creates a new Avarda session if purchase_id has state TimedOut. Avoids issue when customer don't finalize purchase under 1 hour.

= 2022.03.10        - version 1.4.0 =
* Enhancement       - Adds filter aco_locate_template to be able to load ACO checkout template from other plugins.
* Enhancement       - Adds hook aco_wc_confirm_avarda_order in confirmation step.
* Enhancement       - Adds payment gateway logo displayed in checkout.
* Tweak             - Stores _avarda_payment_method_fee if returned from Avarda.
* Tweak             - Adds calc_shipping_country, calc_shipping_state and calc_shipping_postcode as standard checkout fields (that should not be displayed in checkout when ACO is the selected payment method).
* Tweak             - Moves add_extra_checkout_fields function to aco-functions file. Makes it easier to use remove_cation if checkout design modifications is performed via separate plugin/theme.
* Tweak             - Adds logging to update Avarda order ajax request. For easier trouble shooting.
* Fix               - Saves all Avarda payment info (JWT token and purchase ID) in the same WC session (aco_wc_payment_data). To avoid updating different sessions in frontend and backend.

= 2021.12.17        - version 1.3.0 =
* Tweak             - Create new purchase ID if customer changes currency or language during ongoing session.

= 2021.12.08        - version 1.2.3 =
* Enhancement       - Adds separate filters for create (aco_create_args) and update (aco_update_args) requests sent to Avarda.

= 2021.11.24        - version 1.2.2 =
* Fix               - Save company name correctly to WooCommerce order for B2B purchases.

= 2021.11.17        - version 1.2.1 =
* Fix               - Floating point precision fix (many decimals sent to Avarda) for refund requests.

= 2021.11.11        - version 1.2.0 =
* Tweak             - Standard Woo checkout fields check improvement.
* Tweak             - Adds filter aco_ignored_checkout_fields to be able to modify the checkout form fields that should not be displayed on the Avarda Checkout page.
* Fix               - Adds JWT token time expiry check.
* Fix               - Floating point precision fix (many decimals sent to Avarda) for refund requests.

= 2021.10.26        - version 1.1.3 =
* Fix               - Save Billing company name correct in WooCommerce order for B2B purchases.

= 2021.10.12        - version 1.1.2 =
* Fix               - Improvement of previous fix where to many decimals might get sent in prices to Avarda.

= 2021.10.05        - version 1.1.1 =
* Tweak             - Bumped required PHP version to 7.0.
* Fix               - Solved issue with to many decimals sent to Avarda (could happen in certain dev environments). Change from round to number_format.

= 2021.07.06        - version 1.1.0 =
* Feature           - Adds support for sending in termsAndConditionsUrl to Avarda (if set in WooCommerce).
* Tweak             - Changed Initialize checkout request endpoint to /api/partner/payments/.
* Tweak             - Adds request_url to logging.

= 2021.04.29        - version 1.0.1 =
* Tweak             - Added stack trace to logger.
* Tweak             - Reduce the amount of update requests in checkout.
* Fix               - Delete current purchase id stored in Woo session if GET or PUT request to Avarda fails.

= 2021.01.25        - version 1.0.0 =
* Tweak             - Tweak WC checkout form submission logic. The plugin is no longer reliant on a hashchange to send beforeSubmitContinue reponse to Avarda.
* Tweak             - Adds logging to logfile from frontend actions in checkout (during payment completion).
* Tweak             - Don't load checkout scripts on thankyou page.

= 2020.09.18        - version 0.2.0 =
* Enhancement       - Added support for server side callback url. Handles order status control better for payments where customer not returning to shop after completed Card/Swish payments.
* Tweak             - Increased timeout time to 10 seconds in request to Avarda.
* Tweak             - Move Woo order confirmation process to separate class.
* Fix               - Don't make cancel or activate requests if the WooCommerce order hasn't been paid for.

= 2020.07.10        - version 0.1.9 =
* Enhancement       - Added Swedish translation.

= 2020.07.09        - version 0.1.8 =
* Enhancement       - Avarda payment method title is added to the WooCommerce order.
* Enhancement       - Added support for displaying the languages Norwegian and Danish in Avarda checkout.
* Enhancement       - WooCommerce order number is now saved as the order reference in AvardaOnline.
* Fix               - Fix for validating order correct in checkout.

= 2020.06.25        - version 0.1.7 =
* Fix               - Load correct javascript checkout file depending on if plugin is in testmode or not.
* Fix               - Delete request token transient when credentials is changed.

= 2020.06.17        - version 0.1.6 =
* Tweak             - Initialize payment request is using the Avarda legacy endpoint.
* Enhancement       - Trigger event when Avarda Checkout form is loaded on checkout page.

= 2020.06.03        - version 0.1.5 =
* Enhancement       - Support for Swedish, Finnish and English language in the checkout.

= 2020.06.01        - version 0.1.4 = 
* Enhancement       - Prevent doing update request when payment has state Completed or TimedOut.

= 2020.05.28        - version 0.1.3 =
* Fix               - Fix for extra checkout fields not showing up on checkout page.

= 2020.05.13        - version 0.1.2 =
* Feature           - Added support for customizing the Avarda Checkout through a filter.
* Fix               - Fix for endless spinning wheel at checkout if something went wrong in Avarda.
* Tweak             - Change the way plugin is fetching data from payment status request.
* Enhancement       - Initialize new payment at checkout if current payment timed out.

= 2020.05.08        - version 0.1.1 =
* Tweak             - Updated readme file.

= 2020.05.08        - version 0.1.0 =
* Initial release.
