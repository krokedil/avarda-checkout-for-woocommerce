jQuery(function($) {
	const aco_wc = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',

		// Order notes.
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),

		// Payment method
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#avarda-checkout-select-other',

		// Address data.
		addressData: [],

		// Extra checkout fields.
		blocked: false,
		extraFieldsSelectorText: 'div#aco-extra-checkout-fields input[type="text"], div#aco-extra-checkout-fields input[type="password"], div#aco-extra-checkout-fields textarea, div#aco-extra-checkout-fields input[type="email"], div#aco-extra-checkout-fields input[type="tel"]',
		extraFieldsSelectorNonText: 'div#aco-extra-checkout-fields select, div#aco-extra-checkout-fields input[type="radio"], div#aco-extra-checkout-fields input[type="checkbox"], div#aco-extra-checkout-fields input.checkout-date-picker, input#terms input[type="checkbox"]',


		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			aco_wc.ACOCheckoutForm();

			// Add two column class to checkout if Avarda setting in Woo is set.
			if ( true === aco_wc_params.aco_checkout_layout.two_column ) {
				$('form.checkout.woocommerce-checkout').addClass('aco-two-column-checkout-left');
				$('#aco-iframe').addClass('aco-two-column-checkout-right');
			}
		},

		ACOCheckoutForm: function() {
			(function(e,t,n,a,s,c,o,i,r){e[a]=e[a]||function(){(e[a].q=e[a].q||[
			]).push(arguments)};e[a].i=s;i=t.createElement(n);i.async=1
			;i.src=o+"?v="+c+"&ts="+1*new Date;r=t.getElementsByTagName(n)[0]
			;r.parentNode.insertBefore(i,r)})(window,document,"script",
			"avardaCheckoutInit","avardaCheckout","1.0.0",
			"https://avdonl0s0checkout0fe.blob.core.windows.net/frontend/static/js/main.js"
			);
	
			window.avardaCheckoutInit({
				"accessToken": aco_wc_params.aco_jwt_token,
				"rootElementId": "checkout-form",
				"redirectUrl": aco_wc_params.aco_redirect_url,
				"styles": aco_wc_params.aco_checkout_style,
				"disableFocus": true,
				"beforeSubmitCallback": aco_wc.handleBeforeSubmitCallback,
				"completedPurchaseCallback": aco_wc.handleCompletedPurchaseCallback,
				"deliveryAddressChangedCallback": aco_wc.handleDeliveryAddressChangedCallback,
			});
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
					$('form.checkout').submit();
					return true;
				}
			});
		},

		setCustomerData: function( data ) {
			console.log(data);

			$( '#billing_first_name' ).val( data.customer_data.invoicingAddress.firstName ? data.customer_data.invoicingAddress.firstName : '.' );
			$( '#billing_last_name' ).val( data.customer_data.invoicingAddress.lastName ? data.customer_data.invoicingAddress.lastName : '.' );
			$( '#billing_company' ).val( ( data.customer_data.companyName ? data.customer_data.companyName : '' ) );
			$( '#billing_address_1' ).val( data.customer_data.invoicingAddress.address1 ? data.customer_data.invoicingAddress.address1 : '.' );
			$( '#billing_address_2' ).val( ( data.customer_data.invoicingAddress.address2 ? data.customer_data.invoicingAddress.address2 : '' ) );
			$( '#billing_city' ).val( data.customer_data.invoicingAddress.city ? data.customer_data.invoicingAddress.city : '.' );
			$( '#billing_postcode' ).val( data.customer_data.invoicingAddress.zip ? data.customer_data.invoicingAddress.zip : '11111' );
			$( '#billing_phone' ).val( data.customer_data.phone ? data.customer_data.phone : '.' );
			$( '#billing_email' ).val( data.customer_data.email ? data.customer_data.email : '.' );

			if ( null !== data.customer_data.deliveryAddress ) {
				// Check Ship to different address, if it exists.
				if ($("form.checkout #ship-to-different-address-checkbox").length > 0) {
					$("form.checkout #ship-to-different-address-checkbox").prop("checked", true);
				}
				$( '#shipping_first_name' ).val( data.customer_data.deliveryAddress.firstName ? data.customer_data.deliveryAddress.firstName : '.' );
				$( '#shipping_last_name' ).val( data.customer_data.deliveryAddress.lastName ? data.customer_data.deliveryAddress.lastName : '.' );
				$( '#shipping_address_1' ).val( data.customer_data.deliveryAddress.address1 ? data.customer_data.deliveryAddress.address1 : '.' );
				$( '#shipping_address_2' ).val( ( data.customer_data.deliveryAddress.address2 ? data.customer_data.deliveryAddress.address2 : '' ) );
				$( '#shipping_city' ).val( data.customer_data.deliveryAddress.city ? data.customer_data.deliveryAddress.city : '.' );
				$( '#shipping_postcode' ).val( data.customer_data.invoicingAddress.zip ? data.customer_data.invoicingAddress.zip : '11111' );
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
					console.log(data.responseJSON);
					if (true === data.responseJSON.success) {
						window.avardaCheckout.refreshForm();
						$('.woocommerce-checkout-review-order-table').unblock();							
					} else {
						console.log('error');
						if( '' !== data.responseJSON.data.redirect_url ) {
							console.log('Cart do not need payment. Reloading checkout.');
							window.location.href = data.responseJSON.data.redirect_url;
						}
					}
				}
			});
		},

		hashChange: function() {
			console.log('hashchange');
			var currentHash = location.hash;
            var splittedHash = currentHash.split("=");
            console.log(splittedHash[0]);
            console.log(splittedHash[1]);
            if(splittedHash[0] === "#avarda-success"){
				$( 'body' ).trigger( 'aco_order_validation', true );
				var response = JSON.parse( atob( splittedHash[1] ) );
                console.log('response.redirect_url');
                console.log(response.redirect_url);
				sessionStorage.setItem( 'avardaRedirectUrl', response.redirect_url );
				$('form.checkout').removeClass( 'processing' ).unblock();
            }
		},

		errorDetected: function() {
			$( 'body' ).trigger( 'aco_order_validation', false );
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
			$('.woocommerce-additional-fields').appendTo('#aco-extra-checkout-fields');

			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
			for ( i = 0; i < form.length; i++ ) {
				let name = form[i]['name'];
				// Check if this is a standard field.
				if ( $.inArray( name, aco_wc_params.standard_woo_checkout_fields ) === -1 ) {
					// This is not a standard Woo field, move to our div.
					$('p#' + name + '_field').appendTo('#aco-extra-checkout-fields');
				}
			}
		},

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			// Check if Avarda is the selected payment method before we do anything.
			if( aco_wc.checkIfSelected() ) {
				$(document).ready( aco_wc.documentReady() );
			
				// Change from ACO.
				aco_wc.bodyEl.on('click', aco_wc.selectAnotherSelector, aco_wc.changeFromACO);

				// Catch changes to order notes.
				aco_wc.bodyEl.on('change', '#order_comments', aco_wc.updateOrderComment);

				// Update avarda payment.
				aco_wc.bodyEl.on('updated_checkout', aco_wc.updateAvardaPayment);
				
				// Hashchange.
				$( window ).on('hashchange', aco_wc.hashChange);
				// Error detected.
				$( document.body ).on( 'checkout_error', aco_wc.errorDetected );
			}
			aco_wc.bodyEl.on('change', 'input[name="payment_method"]', aco_wc.maybeChangeToACO);
			aco_wc.bodyEl.on( 'click', aco_wc.selectAnotherSelector, aco_wc.changeFromACO );
		},
	}
	aco_wc.init();
});
