jQuery(function($) {
	const aco_wc = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',

		// Order notes.
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),

		// Payment method.
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#avarda-checkout-select-other',

		// Address data.
		addressData: [],

		// Extra checkout fields.
		blocked: false,
		extraFieldsSelectorText: 'div#aco-extra-checkout-fields input[type="text"], div#aco-extra-checkout-fields input[type="password"], div#aco-extra-checkout-fields textarea, div#aco-extra-checkout-fields input[type="email"], div#aco-extra-checkout-fields input[type="tel"]',
		extraFieldsSelectorNonText: 'div#aco-extra-checkout-fields select, div#aco-extra-checkout-fields input[type="radio"], div#aco-extra-checkout-fields input[type="checkbox"], div#aco-extra-checkout-fields input.checkout-date-picker, input#terms input[type="checkbox"]',

		// Mutation observer.
		observer: new MutationObserver(function(mutationsList) {
			for ( var mutation of mutationsList ) {
				if ( mutation.type == 'childList' ) {
					if( mutation.addedNodes[0] ) {
						if( 'avarda-checkout-custom-element' === mutation.target.localName ) {
							console.log(mutation.target.localName);
							$('body').trigger('aco_checkout_loaded');
						}
					}
				}
			}
		}),
		
		config: {
			attributes: false, childList: true, characterData: false, subtree:true,
		},

		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			aco_wc.moveExtraCheckoutFields();
			aco_wc.ACOCheckoutForm();

			// Add two column class to checkout if Avarda setting in Woo is set.
			if ( true === aco_wc_params.aco_checkout_layout.two_column ) {
				$('form.checkout.woocommerce-checkout').addClass('aco-two-column-checkout-left');
				$('#aco-iframe').addClass('aco-two-column-checkout-right');
			}
		},

		ACOCheckoutForm: function() {
			// Stage or Prod javascript file url.
			var acoJsUrl = ( aco_wc_params.aco_test_mode ) ? "https://avdonl0s0checkout0fe.blob.core.windows.net/frontend/static/js/main.js" : "https://avdonl0p0checkout0fe.blob.core.windows.net/frontend/static/js/main.js";

			(function(e,t,n,a,s,c,o,i,r){e[a]=e[a]||function(){(e[a].q=e[a].q||[
			]).push(arguments)};e[a].i=s;i=t.createElement(n);i.async=1
			;i.src=o+"?v="+c+"&ts="+1*new Date;r=t.getElementsByTagName(n)[0]
			;r.parentNode.insertBefore(i,r)})(window,document,"script",
			"avardaCheckoutInit","avardaCheckout","1.0.0",
			acoJsUrl
			);
	
			window.avardaCheckoutInit({
				"purchaseJwt": aco_wc_params.aco_jwt_token,
				"rootElementId": "checkout-form",
				"redirectUrl": aco_wc_params.aco_redirect_url,
				"styles": aco_wc_params.aco_checkout_style,
				"disableFocus": true,
				"beforeSubmitCallback": aco_wc.handleBeforeSubmitCallback,
				"completedPurchaseCallback": aco_wc.handleCompletedPurchaseCallback,
				"deliveryAddressChangedCallback": aco_wc.handleDeliveryAddressChangedCallback,
				"sessionTimedOutCallback": aco_wc.handleSessionTimedOutCallback,
			});
		},

		handleSessionTimedOutCallback: function(callback) {
			console.log( 'session_timed_out' );
			window.location.reload();
		},

		handleDeliveryAddressChangedCallback: function(address, callback) {
			console.log( 'shipping_address_change' );
			$( '.woocommerce-checkout-review-order-table' ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$.ajax(
				{
					url: aco_wc_params.iframe_shipping_address_change_url,
					type: 'POST',
					dataType: 'json',
					data: {
						address: address,
						nonce: aco_wc_params.iframe_shipping_address_change_nonce
					},
					success: function( response ) {
						console.log( response );

						aco_wc.setCustomerDeliveryData( response.data );
						
						if ( 'yes' === response.data.update_needed ) {
							// All good refresh aco form and trigger update_checkout event.
							callback.refreshForm();
							$( 'body' ).trigger( 'update_checkout' );
						} else {
							callback.deliveryAddressChangedContinue();
						}

					},
					error: function( response ) {
						console.log( response );
					},
					complete: function( response ) {
						$( '.woocommerce-checkout-review-order-table' ).unblock();
					}
				}
			);
		},

		setCustomerDeliveryData: function( data ) {
			console.log(data);
			$( '#billing_postcode' ).val( data.customer_zip ? data.customer_zip : '' );
			$( '#billing_country' ).val( data.customer_country ? data.customer_country : '' );

			$( '#shipping_postcode' ).val( data.customer_zip ? data.customer_zip : '' );
			$( '#shipping_country' ).val( data.customer_country ? data.customer_country : '' ); 
		},

		handleCompletedPurchaseCallback: function(callback){
			console.log('avarda-payment-completed');
            var redirectUrl = sessionStorage.getItem( 'avardaRedirectUrl' );
            console.log(redirectUrl);
            if( redirectUrl ) {
               window.location.href = redirectUrl;
			}
			callback.unmount();
		},

		handleBeforeSubmitCallback: function(data, callback) {
			aco_wc.logToFile( 'Received "beforeSubmitCallback" from Avarda' );
			aco_wc.getAvardaPayment();

			$( 'body' ).on( 'aco_order_validation', function( event, bool ) {
				if ( false === bool ) {
					// Fail.
					callback.beforeSubmitAbort();
				} else {
					// Success.
					callback.beforeSubmitContinue();
				}
			});
		},

		getAvardaPayment: function() {
			$.ajax({
				type: 'POST',
				url: aco_wc_params.get_avarda_payment_url,
				data: {
					nonce: aco_wc_params.get_avarda_payment_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
					return false;
				},
				complete: function(data) {
					aco_wc.setCustomerData( data.responseJSON.data );
					// Check Terms checkbox, if it exists.
					if ($("form.checkout #terms").length > 0) {
						$("form.checkout #terms").prop("checked", true);
					}
					//$('form.checkout').submit();
					//return true;
					// Submit wc order.
					aco_wc.submitForm();
				}
			});
		},

		setCustomerData: function( data ) {
			console.log(data);

			if ( 'B2C' === data.customer_data.mode ) {
				userInputs = data.customer_data.b2C.userInputs;
				invoicingAddress = data.customer_data.b2C.invoicingAddress;
				deliveryAddress = data.customer_data.b2C.deliveryAddress;
			} else if ( 'B2B' === data.customer_data.mode ) {
				userInputs = data.customer_data.b2B.userInputs;
				invoicingAddress = data.customer_data.b2B.invoicingAddress;
				deliveryAddress = data.customer_data.b2B.deliveryAddress;
				$( '#billing_company' ).val( ( invoicingAddress.name ? invoicingAddress.name : '' ) );
			}

			$( '#billing_first_name' ).val( '.' );
			$( '#billing_last_name' ).val( '.' );
			$( '#billing_address_1' ).val( '.' );
			$( '#billing_address_2' ).val( '' );
			$( '#billing_city' ).val( '.' );
			$( '#billing_postcode' ).val( invoicingAddress.zip ? invoicingAddress.zip : '11111' );
			$( '#billing_phone' ).val( '.' );
			$( '#billing_email' ).val( 'krokedil@krokedil.se' );

			

			if ( null !== deliveryAddress ) {
				// Check Ship to different address, if it exists.
				if ($("form.checkout #ship-to-different-address-checkbox").length > 0) {
					$("form.checkout #ship-to-different-address-checkbox").prop("checked", true);
				}
				$( '#shipping_first_name' ).val( '.' );
				$( '#shipping_last_name' ).val( '.' );
				$( '#shipping_address_1' ).val( '.' );
				$( '#shipping_address_2' ).val( '' );
				$( '#shipping_city' ).val( '.' );
				$( '#shipping_postcode' ).val( invoicingAddress.zip ? invoicingAddress.zip : '11111' );
			} 
		},

		updateAvardaPayment: function() {
			$('.woocommerce-checkout-review-order-table').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$.ajax({
				type: 'POST',
				url: aco_wc_params.update_payment_url,
				data: {
					nonce: aco_wc_params.update_payment_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					console.log('updateAvardaPayment complete');
					console.log(data);
					console.log(data.responseJSON);

					if ( data.responseJSON && true === data.responseJSON.success ) {

						if( data.responseJSON.data && data.responseJSON.data.refreshZeroAmount ) {
							window.location.reload();
						}

						window.avardaCheckout.refreshForm();
						$('.woocommerce-checkout-review-order-table').unblock();

					} else {
						console.log('updateAvardaPayment error');
						if( data.responseJSON && data.responseJSON.data && '' !== data.responseJSON.data.redirect_url ) {
							console.log('Cart do not need payment. Reloading checkout.');
							window.location.href = data.responseJSON.data.redirect_url;
						}
						$('.woocommerce-checkout-review-order-table').unblock();
					}

					
				}
			});
		},

		/*
		 * Check if our gateway is the selected gateway.
		 */
		checkIfSelected: function() {
			if (aco_wc.paymentMethodEl.length > 0) {
				aco_wc.paymentMethod = aco_wc.paymentMethodEl.filter(':checked').val();
				if( 'aco' === aco_wc.paymentMethod ) {
					return true;
				}
			} 
			return false;
		},

		// When "Change to another payment method" is clicked.
		changeFromACO: function(e) {
			e.preventDefault();

			$(aco_wc.checkoutFormSelector).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				type: 'POST',
				dataType: 'json',
				data: {
					aco: false,
					nonce: aco_wc_params.change_payment_method_nonce
				},
				url: aco_wc_params.change_payment_method_url,
				success: function (data) {},
				error: function (data) {},
				complete: function (data) {
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},

		// When payment method is changed to ACO in regular WC Checkout page.
		maybeChangeToACO: function() {
			if ( 'aco' === $(this).val() ) {

				$(aco_wc.checkoutFormSelector).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				$('.woocommerce-info').remove();

				$.ajax({
					type: 'POST',
					data: {
						aco: true,
						nonce: aco_wc_params.change_payment_method_nonce
					},
					dataType: 'json',
					url: aco_wc_params.change_payment_method_url,
					success: function (data) {},
					error: function (data) {},
					complete: function (data) {
						window.location.href = data.responseJSON.data.redirect;
					}
				});
			}
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
		 * Submit the order using the WooCommerce AJAX function.
		 */
		submitForm: function() {
			$( '.woocommerce-checkout-review-order-table' ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$.ajax({
				type: 'POST',
				url: aco_wc_params.submit_order,
				data: $('form.checkout').serialize(),
				dataType: 'json',
				success: function( data ) {
					try {
						if ( 'success' === data.result ) {
							aco_wc.logToFile( 'Successfully placed order. Sending "beforeSubmitContinue" true to Avarda' );

							$( 'body' ).trigger( 'aco_order_validation', true );
							console.log('data.redirect_url');
							console.log(data.redirect_url);
							sessionStorage.setItem( 'avardaRedirectUrl', data.redirect_url );
							$('form.checkout').removeClass( 'processing' ).unblock();
						} else {
							throw 'Result failed';
						}
					} catch ( err ) {
						if ( data.messages )  {
							aco_wc.logToFile( 'Checkout error | ' + data.messages );
							aco_wc.failOrder( 'submission', data.messages );
						} else {
							aco_wc.logToFile( 'Checkout error | No message' );
							aco_wc.failOrder( 'submission', '<div class="woocommerce-error">' + 'Checkout error' + '</div>' );
						}
					}
				},
				error: function( data ) {
					aco_wc.logToFile( 'AJAX error | ' + data );
					aco_wc.failOrder( 'ajax-error', data );
				}
			});
		},

		failOrder: function( event, error_message ) {
			// Send false and cancel
			$( 'body' ).trigger( 'aco_order_validation', false );
		
			// Re-enable the form.
			$( 'body' ).trigger( 'updated_checkout' );
			$( aco_wc.checkoutFormSelector ).unblock();
			$( '.woocommerce-checkout-review-order-table' ).unblock();

			// Print error messages, and trigger checkout_error, and scroll to notices.
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			$( 'form.checkout' ).prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' ); // eslint-disable-line max-len
			$( 'form.checkout' ).removeClass( 'processing' ).unblock();
			$( 'form.checkout' ).find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			$( document.body ).trigger( 'checkout_error' , [ error_message ] );
			$( 'html, body' ).animate( {
				scrollTop: ( $( 'form.checkout' ).offset().top - 100 )
			}, 1000 );
		},


		/**
		 * Logs the message to the Avarda log in WooCommerce.
		 * @param {string} message 
		 */
		logToFile: function( message ) {
			$.ajax(
				{
					url: aco_wc_params.log_to_file_url,
					type: 'POST',
					dataType: 'json',
					data: {
						message: message,
						nonce: aco_wc_params.log_to_file_nonce
					}
				}
			);
		},

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			// Check if Avarda is the selected payment method before we do anything.
			if( aco_wc.checkIfSelected() ) {
				$(document).ready( aco_wc.documentReady() );

				aco_wc.observer.observe( document.querySelector( '#aco-iframe' ), aco_wc.config );

				// Change from ACO.
				aco_wc.bodyEl.on('click', aco_wc.selectAnotherSelector, aco_wc.changeFromACO);

				// Update avarda payment.
				aco_wc.bodyEl.on('updated_checkout', aco_wc.updateAvardaPayment);
 				
			}
			aco_wc.bodyEl.on('change', 'input[name="payment_method"]', aco_wc.maybeChangeToACO);
			aco_wc.bodyEl.on( 'click', aco_wc.selectAnotherSelector, aco_wc.changeFromACO );
		},
	}
	aco_wc.init();
});
