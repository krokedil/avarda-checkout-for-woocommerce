jQuery(function($) {
	const aco_wc = {
    bodyEl: $("body"),
    checkoutFormSelector: "form.checkout",
    customerData: {
      email: "",
      phone: "",
      mode: "",
      invoicingAddress: {
        firstName: "",
        lastName: "",
        address1: "",
        address2: "",
        city: "",
        zip: "",
        country: "",
      },
      deliveryAddress: {
        firstName: "",
        lastName: "",
        address1: "",
        address2: "",
        city: "",
        zip: "",
        country: "",
      },
    },
    // Order notes.
    orderNotesValue: "",
    orderNotesSelector: "textarea#order_comments",
    orderNotesEl: $("textarea#order_comments"),

    // Payment method.
    paymentMethodEl: $('input[name="payment_method"]'),
    paymentMethod: "",
    selectAnotherSelector: "#avarda-checkout-select-other",

    // Address data.
    addressData: [],

    // Extra checkout fields.
    blocked: false,
    extraFieldsSelectorText:
      'div#aco-extra-checkout-fields input[type="text"], div#aco-extra-checkout-fields input[type="password"], div#aco-extra-checkout-fields textarea, div#aco-extra-checkout-fields input[type="email"], div#aco-extra-checkout-fields input[type="tel"]',
    extraFieldsSelectorNonText:
      'div#aco-extra-checkout-fields select, div#aco-extra-checkout-fields input[type="radio"], div#aco-extra-checkout-fields input[type="checkbox"], div#aco-extra-checkout-fields input.checkout-date-picker, input#terms input[type="checkbox"]',

    // Mutation observer.
    observer: new MutationObserver(function (mutationsList) {
      for (var mutation of mutationsList) {
        if (mutation.type == "childList") {
          if (mutation.addedNodes[0]) {
            if (
              "avarda-checkout-custom-element" === mutation.target.localName
            ) {
              console.log(mutation.target.localName);
              $("body").trigger("aco_checkout_loaded");
            }
          }
        }
      }
    }),

    config: {
      attributes: false,
      childList: true,
      characterData: false,
      subtree: true,
    },

    /*
     * Document ready function.
     * Runs on the $(document).ready event.
     */
    documentReady: function () {
      aco_wc.moveExtraCheckoutFields();
      aco_wc.ACOCheckoutForm();
    },

    ACOCheckoutForm: function () {
      // Do not try to display the checkout if JWT token is missing.
      if ("" === aco_wc_params.aco_jwt_token) {
        return;
      }
      // Stage or Prod javascript file url.
      var acoJsUrl = aco_wc_params.aco_test_mode
        ? "https://stage.checkout-cdn.avarda.com/cdn/static/js/main.js"
        : "https://checkout-cdn.avarda.com/cdn/static/js/main.js";

      (function (e, t, n, a, s, c, o, i, r) {
        e[a] =
          e[a] ||
          function () {
            (e[a].q = e[a].q || []).push(arguments);
          };
        e[a].i = s;
        i = t.createElement(n);
        i.async = 1;
        i.src = o + "?v=" + c + "&ts=" + 1 * new Date();
        r = t.getElementsByTagName(n)[0];
        r.parentNode.insertBefore(i, r);
      })(
        window,
        document,
        "script",
        "avardaCheckoutInit",
        "avardaCheckout",
        "1.0.0",
        acoJsUrl
      );

      var acoInit = {
        purchaseJwt: aco_wc_params.aco_jwt_token,
        rootElementId: "checkout-form",
        redirectUrl: aco_wc_params.aco_redirect_url,
        styles: aco_wc_params.aco_checkout_style,
        disableFocus: true,
        completedPurchaseCallback: aco_wc.handleCompletedPurchaseCallback,
        sessionTimedOutCallback: aco_wc.handleSessionTimedOutCallback,
      };
      if (aco_wc_params.is_aco_action === "no") {
        acoInit.deliveryAddressChangedCallback =
          aco_wc.handleDeliveryAddressChangedCallback;
        acoInit.beforeSubmitCallback = aco_wc.handleBeforeSubmitCallback;
        acoInit.shippingOptionChangedCallback =
          aco_wc.handleShippingOptionChangedCallback;
      }
      window.avardaCheckoutInit(acoInit);
    },

    handleShippingOptionChangedCallback: function (
      { price, currency },
      checkout
    ) {
      console.log("shipping_option_change");

      $(".woocommerce-checkout-review-order-table").block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });

      console.log(
        "iframe_shipping_option_change_url",
        aco_wc_params.iframe_shipping_option_change_url
      );
      console.log(
        "iframe_shipping_option_change_nonce",
        aco_wc_params.iframe_shipping_option_change_nonce
      );

      // Trigger update checkout event and force shipping to be recalculated.
      $.ajax({
        url: aco_wc_params.iframe_shipping_option_change_url,
        type: "POST",
        dataType: "json",
        data: {
          nonce: aco_wc_params.iframe_shipping_option_change_nonce,
        },
        success: function (response) {
          if (!response.success) {
            // Fail. TODO: Handle error.
            return;
          }

          // Get the fragments and replace the HTML content of each fragment.
          const fragments = response.data.fragments;
          if (fragments) {
            $.each(fragments, function (key) {
              $(key).replaceWith(fragments[key]);
            });
          }
        },
        error: function (response) {
          console.log(response);
        },
        complete: function (response) {
          $(".woocommerce-checkout-review-order-table").unblock();
        },
      });
    },

    handleSessionTimedOutCallback: function (callback) {
      console.log("session_timed_out");
      window.location.reload();
    },

    handleDeliveryAddressChangedCallback: function (address, callback) {
      console.log("shipping_address_change");
      $(".woocommerce-checkout-review-order-table").block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });
      $.ajax({
        url: aco_wc_params.iframe_shipping_address_change_url,
        type: "POST",
        dataType: "json",
        data: {
          address: address,
          nonce: aco_wc_params.iframe_shipping_address_change_nonce,
        },
        success: function (response) {
          console.log(response);

          aco_wc.setCustomerDeliveryData(response.data);

          if ("yes" === response.data.update_needed) {
            // All good refresh aco form and trigger update_checkout event.
            callback.refreshForm();
            $("body").trigger("update_checkout");
          } else {
            callback.deliveryAddressChangedContinue();
          }
        },
        error: function (response) {
          console.log(response);
        },
        complete: function (response) {
          $(".woocommerce-checkout-review-order-table").unblock();
        },
      });
    },

    setCustomerDeliveryData: function (data) {
      console.log(data);
      $("#billing_postcode").val(
        data.customer_zip ? data.customer_zip.replace(/\s/g, "") : ""
      );
      $("#billing_country").val(
        data.customer_country ? data.customer_country : ""
      );

      $("#shipping_postcode").val(
        data.customer_zip ? data.customer_zip.replace(/\s/g, "") : ""
      );
      $("#shipping_country").val(
        data.customer_country ? data.customer_country : ""
      );
    },

    handleCompletedPurchaseCallback: function (callback) {
      if (
        aco_wc_params.confirmation_url !== null &&
        aco_wc_params.confirmation_url.length > 1
      ) {
        var confirmation_url = aco_wc_params.confirmation_url;
        if (confirmation_url) {
          window.location.href = confirmation_url;
        }
        callback.unmount();
      } else {
        var redirectUrl = sessionStorage.getItem("avardaRedirectUrl");
        if (redirectUrl) {
          window.location.href = redirectUrl;
        }
        callback.unmount();
      }
    },

    handleBeforeSubmitCallback: function (data, callback) {
      aco_wc.logToFile('Received "beforeSubmitCallback" from Avarda');

      // Get address data from Avarda payment.
      aco_wc.getAvardaPayment();

      $("body").on("aco_order_validation", function (event, bool) {
        if (false === bool) {
          // Fail.
          callback.beforeSubmitAbort();
        } else {
          // Success.
          callback.beforeSubmitContinue();
        }
      });
    },

    fillForm: function (customerAddress) {
      console.log("fillForm", customerAddress);
      var billing_first_name = customerAddress.billing.first_name
        ? customerAddress.billing.first_name
        : ".";
      var billing_last_name = customerAddress.billing.last_name
        ? customerAddress.billing.last_name
        : ".";
      var billing_company = customerAddress.billing.company
        ? customerAddress.billing.company
        : "";
      var billing_address_1 = customerAddress.billing.address1;
      var billing_address_2 = customerAddress.billing.address2;
      var billing_city = customerAddress.billing.city
        ? customerAddress.billing.city
        : ".";
      var billing_postcode = customerAddress.billing.zip
        ? customerAddress.billing.zip.replace(/\s/g, "")
        : "";
      var billing_phone = customerAddress.billing.phone
        ? customerAddress.billing.phone
        : ".";
      var billing_email = customerAddress.billing.email
        ? customerAddress.billing.email
        : "krokedil@krokedil.se";

      $("#billing_first_name").val(billing_first_name);
      $("#billing_last_name").val(billing_last_name);
      $("#billing_company").val(billing_company);

      $("#billing_address_1").val(billing_address_1);
      $("#billing_address_2").val(billing_address_2);
      $("#billing_city").val(billing_city);
      $("#billing_postcode").val(billing_postcode);
      $("#billing_phone").val(billing_phone);
      $("#billing_email").val(billing_email);

      if ($("form.checkout #ship-to-different-address-checkbox").length > 0) {
        $("form.checkout #ship-to-different-address-checkbox").prop(
          "checked",
          true
        );
      }
      $("#shipping_first_name").val(
        customerAddress.shipping.first_name
          ? customerAddress.shipping.first_name
          : billing_first_name
      );
      $("#shipping_last_name").val(
        customerAddress.shipping.last_name
          ? customerAddress.shipping.last_name
          : billing_last_name
      );
      $("#shipping_company").val(
        customerAddress.shipping.company
          ? customerAddress.shipping.company
          : billing_company
      );
      $("#shipping_address_1").val(
        customerAddress.shipping.address1
          ? customerAddress.shipping.address1
          : billing_address_1
      );
      $("#shipping_address_2").val(
        customerAddress.shipping.address2
          ? customerAddress.shipping.address2
          : billing_address_2
      );
      $("#shipping_city").val(
        customerAddress.shipping.city
          ? customerAddress.shipping.city
          : billing_city
      );
      $("#shipping_postcode").val(
        customerAddress.shipping.zip
          ? customerAddress.shipping.zip.replace(/\s/g, "")
          : billing_postcode
      );
    },

    updateAvardaPayment: function () {
      if (window.avardaCheckout) {
        window.avardaCheckout.refreshForm();
      }
    },

    /*
     * Check if our gateway is the selected gateway.
     */
    checkIfSelected: function () {
      if (aco_wc.paymentMethodEl.length > 0) {
        aco_wc.paymentMethod = aco_wc.paymentMethodEl.filter(":checked").val();
        if ("aco" === aco_wc.paymentMethod) {
          return true;
        }
      }
      if (aco_wc_params.is_aco_action === "yes") {
        return true;
      }
      return false;
    },

    // When "Change to another payment method" is clicked.
    changeFromACO: function (e) {
      e.preventDefault();

      $(aco_wc.checkoutFormSelector).block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });

      $.ajax({
        type: "POST",
        dataType: "json",
        data: {
          aco: false,
          nonce: aco_wc_params.change_payment_method_nonce,
        },
        url: aco_wc_params.change_payment_method_url,
        success: function (data) {},
        error: function (data) {},
        complete: function (data) {
          window.location.href = data.responseJSON.data.redirect;
        },
      });
    },

    /**
		 * Maybe freezes the iframe to prevent anyone from completing the order before filling in all required fields.
		 *
		 * @param {boolean} allValid
		 */
		maybeFreezeIframe: function( allValid ) {
			if ( true === allValid ) {
				aco_wc.blocked = false;
				$('#aco-required-fields-notice').remove();
				// Unblock iframe
			} else 	if( ! $('#aco-required-fields-notice').length ) { // Only if we dont have an error message already.
				aco_wc.blocked = true;
				aco_wc.maybePrintValidationMessage();
				// Block iframe
			}
		},

		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields: function() {
			// Move order comments.
			$( '.woocommerce-additional-fields' ).appendTo( '#aco-extra-checkout-fields' );

			var form = $( 'form[name="checkout"] input, form[name="checkout"] select, textarea' );
			for ( i = 0; i < form.length; i++ ) {
				var name = form[i].name;

				// Do not try to move field names that include [ in the name.
				if( name.includes('[')) {
					continue;
				}

				// Check if this is a standard field.
				if ( -1 === $.inArray( name, aco_wc_params.standard_woo_checkout_fields ) ) {

					// This is not a standard Woo field, move to our div.
					if ( 0 < $( 'p#' + name + '_field' ).length ) {
						$( 'p#' + name + '_field' ).appendTo( '#aco-extra-checkout-fields' );
					} else {
						$( 'input[name="' + name + '"]' ).closest( 'p' ).appendTo( '#aco-extra-checkout-fields' );
					}
				}
			}
		},

		/**
		 * Get address data from Avarda payment.
		 */
		getAvardaPayment: function() {
			console.log( 'get_avarda_payment' );
			$( '.woocommerce-checkout-review-order-table' ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$.ajax(
				{
					url: aco_wc_params.get_avarda_payment_url,
					type: 'POST',
					dataType: 'json',
					data: {
						nonce: aco_wc_params.get_avarda_payment_nonce
					},
					success: function( response ) {
						console.log( 'getAvardaPayment', response );

						if (false === response.success) {
							if (response.data.redirect) {
								window.location.href = response.data.redirect;
							} else {
								aco_wc.logToFile( 'Checkout error | ' + response.data.error );
								aco_wc.failOrder( 'getAvardaPayment', '<div class="woocommerce-error">' + response.data.error + '</div>' );
							}
						} else {
							if (response.data.hasOwnProperty('customer_data')) {
								// Set data from wc form
								aco_wc.fillForm(response.data.customer_data);
								// Submit wc order.
								aco_wc.submitForm();
							} else {
								$( '.woocommerce-checkout-review-order-table' ).unblock();
								aco_wc.logToFile( 'getAvardaPayment | Customer data missing in response from Avarda.' );
								aco_wc.failOrder( 'getAvardaPayment', '<div class="woocommerce-error">' + 'Customer data missing in response from Avarda.' + '</div>' );
							}
						}

					},
					error: function( response ) {
						console.log('getAvardaPayment error',  response );
					},
					complete: function( response ) {
					}
				}
			);
		},


    /**
     * Submit the order using the WooCommerce AJAX function.
     */
    submitForm: function () {
      if (0 < $("form.checkout #terms").length) {
        $("form.checkout #terms").prop("checked", true);
      }

      $(".woocommerce-checkout-review-order-table").block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });
      $.ajax({
        type: "POST",
        url: aco_wc_params.submit_order,
        data: $("form.checkout").serialize(),
        dataType: "json",
        success: function (data) {
          try {
            if ("success" === data.result) {
              aco_wc.logToFile(
                'Successfully placed order. Sending "beforeSubmitContinue" true to Avarda'
              );

              $("body").trigger("aco_order_validation", true);
              console.log("data.redirect_url");
              console.log(data.redirect_url);
              sessionStorage.setItem("avardaRedirectUrl", data.redirect_url);
              $("form.checkout").removeClass("processing").unblock();
            } else {
              throw "Result failed";
            }
          } catch (err) {
            if (data.messages) {
              aco_wc.logToFile("Checkout error | " + data.messages);
              aco_wc.failOrder("submission", data.messages);
            } else {
              aco_wc.logToFile("Checkout error | No message");
              aco_wc.failOrder(
                "submission",
                '<div class="woocommerce-error">' + "Checkout error" + "</div>"
              );
            }
          }
        },
        error: function (data) {
          aco_wc.logToFile("AJAX  error | " + data);
          aco_wc.failOrder("ajax-error", data);
        },
      });
    },

    failOrder: function (event, error_message) {
      // Send false and cancel
      $("body").trigger("aco_order_validation", false);

      // Re-enable the form.
      $("body").trigger("updated_checkout");
      $(aco_wc.checkoutFormSelector).unblock();
      $(".woocommerce-checkout-review-order-table").unblock();

      // Print error messages, and trigger checkout_error, and scroll to notices.
      $(
        ".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message"
      ).remove();
      $("form.checkout").prepend(
        '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
          error_message +
          "</div>"
      ); // eslint-disable-line max-len
      $("form.checkout").removeClass("processing").unblock();
      $("form.checkout")
        .find(".input-text, select, input:checkbox")
        .trigger("validate")
        .blur();
      $(document.body).trigger("checkout_error", [error_message]);
      $("html, body").animate(
        {
          scrollTop: $("form.checkout").offset().top - 100,
        },
        1000
      );
    },

    /**
     * Logs the message to the Avarda log in WooCommerce.
     * @param {string} message
     */
    logToFile: function (message) {
      $.ajax({
        url: aco_wc_params.log_to_file_url,
        type: "POST",
        dataType: "json",
        data: {
          message: message,
          nonce: aco_wc_params.log_to_file_nonce,
        },
      });
    },

    /*
     * Initiates the script and sets the triggers for the functions.
     */
    init: function () {
      // Check if Avarda is the selected payment method before we do anything.
      if (aco_wc.checkIfSelected()) {
        $(document).ready(aco_wc.documentReady());

        aco_wc.observer.observe(
          document.querySelector("#aco-iframe"),
          aco_wc.config
        );

        // Change from ACO.
        aco_wc.bodyEl.on(
          "click",
          aco_wc.selectAnotherSelector,
          aco_wc.changeFromACO
        );

        // Update avarda payment.
        aco_wc.bodyEl.on("updated_checkout", aco_wc.updateAvardaPayment);
      }
      aco_wc.bodyEl.on(
        "click",
        aco_wc.selectAnotherSelector,
        aco_wc.changeFromACO
      );
    },
  };
	aco_wc.init();
});
