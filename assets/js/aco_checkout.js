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
		selectAnotherSelector: '#aco-select-other',

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
				"redirectUrl": "redirectUrlToOrderReceived", // TODO: Get the order received url from hash change url param.
				"styles": {},
				"disableFocus": true,
				"beforeSubmitCallback": aco_wc.handleCallback
			});
		},

		handleCallback: function(callback) {
			aco_wc.getAvardaPayment();
			// Sucess.
			//callback.beforeSubmitContinue();

			// Fail.
			// callback.beforeSubmitAbort();
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
			// do stuff
			console.log('customer dataa');
			console.log(data);
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

				// Extra checkout fields.
				aco_wc.bodyEl.on('blur', aco_wc.extraFieldsSelectorText, aco_wc.checkFormData);
				aco_wc.bodyEl.on('change', aco_wc.extraFieldsSelectorNonText, aco_wc.checkFormData);
				aco_wc.bodyEl.on('click', 'input#terms', aco_wc.checkFormData);

			}
			aco_wc.bodyEl.on('change', 'input[name="payment_method"]', aco_wc.maybeChangeToACO);
		},
	}
	aco_wc.init();
});
