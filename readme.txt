=== Avarda Checkout for WooCommerce ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, avarda
Requires at least: 5.0
Tested up to: 6.7.2
Requires PHP: 7.4
WC requires at least: 5.6.0
WC tested up to: 9.7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: 1.16.3

Avarda Checkout for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Avarda.


== DESCRIPTION ==
Avarda Checkout is an e-commerce checkout solution that gives you more than the individual purchase. The checkout is built to provide more repeat customers and an increased average order value.

To get started with Avarda Checkout you need to [sign up](https://www.avarda.com/se/foretag/) for an account.

More information on how to get started can be found in the [plugin documentation](https://docs.krokedil.com/avarda-checkout-for-woocommerce/).


== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your Avarda Checkout settings.
6. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/avarda-checkout-for-woocommerce/).


== CHANGELOG ==
= 2025.04.15        - version 1.16.3 =
* Fix               - Fixed an issue where tax rounding for rates with decimals (Finland's 25.5% VAT, for example) did not work correctly for refunds.
* Fix               - Limit the max size of a log message from the frontend to 1000 characters, to prevent large logs from being created.

= 2025.03.31        - version 1.16.2 =
* Enhancement       - Added tabs and a sidebar to the settings page with documentation and support links.
* Enhancement       - Defaults to the first shipping method to be the selected shipping method, if no selected shipping method was found when getting the shipping session for the Avarda shipping widget.
* Fix               - Fixed an issue where the checkout page sometimes appeared blank when using the Woo Subscriptions plugin.
* Fix               - Added checks to prevent a JavaScript error if the shipping session JSON could not be parsed.
* Fix               - Fixed a potential JavaScript error when trying to get the selected option from the module, if no module could be found in the shipping widget.
* Fix               - Set the default values of the checkout fields as blank instead of '.'.

= 2025.02.16        - version 1.16.1 =
* Enhancement       - Added a spinner to show while shipping methods are being calculated when using the WooCommerce shipping methods inside Avarda Checkout.
* Enhancement       - Changed how we get the tax rate of order lines to prevent issues when calculating the rate.
* Fix               - Fixed an issue with sending tax rates to Avarda that included decimals. For example for Finish 25.5% VAT rate.
* Fix               - Fixed an issue that could cause customer to see an error message saying the purchase id is missing.

= 2025.01.15        - version 1.16.0 =
* Feature           - Added a sync to load shipping methods from the frontend when using the WooCommerce shipping methods inside Avarda Checkout.
* Feature           - Added the ability to send a no shipping option to Avarda Checkout when using the WooCommerce shipping methods, if no shipping methods could be found.
* Fix               - Improved loading of shipping methods from WooCommerce into the Avarda Checkout to lower the loading time.
* Fix               - Fixed issues with address data not being set properly when showing shipping methods in the Avarda Checkout in some cases.
* Fix               - Fixed issues with the order review on the checkout not always updating properly when changing shipping methods in the Avarda Checkout.
* Fix               - Fixed an issue when saving the recurring token to a order or subscription when updating them in the WooCommerce admin.
* Fix               - Fixed a deprecation notice when loading the plugin text domain.

= 2024.10.14        - version 1.15.0 =
* Feature           - Added support for core WooCommerce shipping.
* Feature           - Integrated support for the [Post Purchase Upsell](https://krokedil.com/product/post-purchase-upsell-for-woocommerce/) plugin.
* Feature           - Introduced a setting to control the display of the item list within the checkout form.
* Tweak             - Added an order note when the WooCommerce order number cannot be saved to the Avarda order.
* Tweak             - Enhanced logging for failed API requests.
* Fix               - Resolved compatibility issues with currency switchers.
* Fix               - Fixed a missing template issue for coupons.
* Fix               - Corrected a critical error related to retrieving the shipping method ID.

= 2024.06.13        - version 1.14.1 =
* Fix               - Fixes compatibility with Smart Coupons plugin when using Apply Before taxes on a coupon.

= 2024.06.11        - version 1.14.0 =
* Feature           - Adds support for international checkout using the international credentials setting fields. If a currency the customer is using does not have their own credentials in Avarda the international credentials will be used instead.
* Feature           - Adds filters for the credentials used in the requests. These are aco_credentials, aco_client_id and aco_client_secret.
* Feature           - Add support for integrated shipping methods in Avarda Checkout for nShift and Ingrid with Pickup point support.
* Enhancement       - Improved session handling with Avarda to reduce the amount of requests needed for each order.
* Enhancement       - Added log levels to different log messages to allow limiting of the messages logged.
* Enhancement       - Auth tokens created for the requests are now stored individually based on currency as transients to prevent new tokens being generated each time a new currency is used. Should reduce the amount of requests made on stores using multiple currencies with Avarda.
* Enhancement       - Adds a validation for the cart total against the Avarda session before WooCommerce creates an order. This should prevent on-hold orders from being created when a mismatch is detected.
* Fix               - Fixes an issue with token validation when loading the Javascript that caused us to not properly handle orders that had been completed, and had their session expired in Avarda.
* Fix               - Fixed PHP 8.2 deprecation warnings.

= 2024.04.24        - version 1.13.1 =
* Tweak             - Delete meta data fields _wc_avarda_jwt & _wc_avarda_expiredUtc in Woo order when deleting _wc_avarda_purchase_id (if Avarda session expires and a new one is created).

= 2024.03.21        - version 1.13.0 =
* Feature           - Adds metabox that fetches Avarda payment and displays current status when viewing a single order in Woo admin.
* Feature           - Add support for disabling order management connection between Woo and Avarda on a single order.
* Enhancement       - Adds aco_wc_confirm_failed hook so other plugins can listen to and take action if confirm order step fails.
* Tweak             - Respond with 404 if no Woo order is found during callbacks from Avarda.
* Fix               - Make sure plugin only sends 35 characters in product names, also for refunds.
* Fix               - Check for Avarda TimedOut step/status even when making GET avarda order when fetching customer data and submitting WC checkout form.
* Fix               - Fix issue with the _wc_avarda_purchase_id meta data field not being deleted correctly in pending Woo order (if one exist) if a new Avarda payment session being created.

= 2024.01.30        - version 1.12.0 =
* Feature           - Adds logic to trigger activate and cancel order request to Avarda without changing order status. This is done via WooCommerce Order action logic.

= 2024.01.24        - version 1.11.5 =
* Enhancement       - Adds aco_om_failed hook so other plugins can listen to and take action if activation and cancellation logic fails.
* Fix               - Use transaction id in activate and cancel requests if _wc_avarda_purchase_id is missing in order.
* Fix               - Add note and set order status to On hold if order totals do not match between Woo and Avarda during confirmation step.

= 2024.01.16        - version 1.11.4 =
* Fix               - Use update_status instead of set_status so order status changes are saved correctly when cancel and activate requests fails.

= 2024.01.03        - version 1.11.3 =
* Fix               - Limit name of Smart coupon gift card names sent to Avarda to 35 characters.

= 2023.11.29        - version 1.11.2 =
* Fix               - Fixed an incorrect meta query during the confirmation step if the order id was missing from the url.
* Fix               - Improved checks before we confirm an order to ensure the payment id from Avarda matches the stored payment id in the order.
* Fix               - Fixed a potential fatal error when handling a callback from Avarda, that happened due to logging the order id before ensuring we had an order.

= 2023.11.21        - version 1.11.1 =
* Fix               - Delete meta data fix for redirect flow. Could cause fatal error.

= 2023.11.21        - version 1.11.0 =
* Feature           - Adds support for recurring payments via WooCommerce Subscriptions plugin.
* Feature           - Adds support for WooCommerce "High-Performance Order Storage" (HPOS) feature.
* Feature           - Add setting and logic for age validation.
* Tweak             - Improvements in how and when customer full address data is fetched from Avarda during creation of Woo order.

= 2023.10.12        - version 1.10.2 =
* Fix               - Fix compatibility with Smart Coupons.

= 2023.06.28        - version 1.10.1 =
* Fix               - Trigger update to Avarda on a later priority (999999) in woocommerce_after_calculate_totals action to be compatible with newer versions of Smart Coupons.

= 2023.06.27        - version 1.10.0 =
* Feature           - Adds support for gift cards via Smart Coupons plugin.

= 2023.06.07        - version 1.9.0 =
* Tweak             - Updates plugin to use new endpoint URLs for Avarda API.

= 2022.12.13        - version 1.8.0 =
* Feature           - Adds support for Avardas refund order logic (refund/release reserved amount before the payment is captured).
* Feature           - Adds settings for custom payment gateway icon.
* Tweak             - Specifying quantity on order lines sent to Avarda.
* Fix               - PHP 8.1 deprecated notice fix.

= 2022.11.01        - version 1.7.1 =
* Fix               - Improvement in check that current Avarda session step can be updated before sending update request. Solves potential issue with deleted/missing payment session.
* Fix               - Solve issue with fee amount and fee tax amount in activate order request.

= 2022.10.03        - version 1.7.0 =
* Feature		    - Adds last 15 requests to Avarda that had an API error and display them on the WooCommerce status page. These will also be in the status report that you can send to Krokedil for support tickets.
* Fix               - Do not try to make update payment session request to Avarda if session is in redirected to payment method state.

= 2022.09.26        - version 1.6.2 =
* Tweak             - Adds functionality to enable custom payment method names. Can be filtered/tweaked via aco_order_set_payment_method_title.
* Tweak             - Set orderReference on init call to Avarda when Woo order is vailable (used for redirect flow).
* Tweak             - Move enqueue of JS and CSS to assets class.
* Tweak             - Only run wp_localize_script & wp_enqueue_script if ACO is used. Results in Avarda purchase session only created if Avarda Checkout is about to be displayed.
* Tweak             - Move maybeChangeToACO JS function into separate utility JS file.
* Fix               - Fix bug related to "Avarda JWT token issue" error message displayed in checkout. Could happen if checkout flow settings wasn't saved in plugin settings page.

= 2022.09.06        - version 1.6.1 =
* Fix               - Confirm that the JWT token used in frontend is the same as the one stored in backend when sending session updates to Avarda.

= 2022.09.06        - version 1.6.0 =
* Tweak             - Move update request to Avarda from ajax function to woocommerce_after_calculate_totals.
* Tweak             - Improve request class logic.
* Tweak             - Improve error message notice display in checkout.
* Tweak             - Query orders 5 days back in confirm_order sequence.
* Tweak             - Remove wc_print_notices in template files. Not needed anymore. WooCommerce handles this.
* Tweak             - Callback notifications now handled 2 minutes after purchase if needed.

= 2022.08.16        - version 1.5.2 =
* Tweak             - Logging improvements.

= 2022.07.14        - version 1.5.1 =
* Fix               - Send correct data to customer city & country for redirect flow.

= 2022.06.14        - version 1.5.0 =
* Feature           - Adds support for pay for order logic. Merchant can now create an order in admin and send a pay link to a customer, where they can finish the payment using Avarda Checkout.
* Feature           - Adds support for redirect checkout flow. Regular Woo checkout page is used and Avarda Checkout is instead rendered on order recipt page.
* Tweak             - Adds compatibility support with Woo Carrier Agents plugin.
* Tweak             - Logging improvements.
* Fix               - Redirects customer to thankyou page directly instead of rendering Avarda Checkout and then redirecting customer. Avoids potential issues if customer is redirected back from 3DS after Avarda session time expired.

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
