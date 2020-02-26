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
		},

		/*
		 * Check if our gateway is the selected gateway.
		 */
		checkIfSelected: function() {
			if (aco_wc.paymentMethodEl.length > 0) {
				aco_wc.paymentMethod = aco_wc.paymentMethodEl.filter(':checked').val();
				if( 'Avarda_Checkout' === aco_wc.paymentMethod ) {
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
		 * Updates the order comment local storage.
		 */
		updateOrderComment: function() {
			let val = $('#order_comments').val();
			localStorage.setItem( 'aco_wc_order_comment', val );
		},

		/**
		 * Checks for form Data on the page, and sets the checkout fields session storage.
		 */
		checkFormData: function() {
			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
				let requiredFields = [];
				let fieldData = {};
				// Get all form fields.
				for ( i = 0; i < form.length; i++ ) { 
					// Check if the form has a name set.
					if ( form[i]['name'] !== '' ) {
						let name    = form[i]['name'];
						let field = $('*[name="' + name + '"]');
						let required = ( $('p#' + name + '_field').hasClass('validate-required') ? true : false );
						// Only keep track of non standard WooCommerce checkout fields
						if ($.inArray(name, aco_wc_params.standard_woo_checkout_fields) == '-1' && name.indexOf('[qty]') < 0 && name.indexOf( 'shipping_method' ) < 0 && name.indexOf( 'payment_method' ) < 0 ) {
							// Only keep track of required fields for validation.
							if ( required === true ) {
								requiredFields.push(name);
							}
							// Get the value from the field.
							let value = '';
							if( field.is(':checkbox') ) {
								if( field.is(':checked') ) {
									value = form[i].value;
								}
							} else if( field.is(':radio') ) {
								if( field.is(':checked') ) {
									value = $( 'input[name="' + name + '"]:checked').val();
								}
							} else {
								value = form[i].value
							}
							// Set field data with values.
							fieldData[name] = value;
						}
					}
				}
				sessionStorage.setItem( 'acoRequiredFields', JSON.stringify( requiredFields ) );
				sessionStorage.setItem( 'acoFieldData', JSON.stringify( fieldData ) );
				aco_wc.validateRequiredFields();
		},

		/**
		 * Validates the required fields, checks if they have a value set.
		 */
		validateRequiredFields: function() {
			// Get data from session storage.
			let requiredFields = JSON.parse( sessionStorage.getItem( 'acoRequiredFields' ) );
			let fieldData = JSON.parse( sessionStorage.getItem( 'acoFieldData' ) );
			// Check if all data is set for required fields.
			let allValid = true;
			if ( requiredFields !== null ) {
				for( i = 0; i < requiredFields.length; i++ ) {
					fieldName = requiredFields[i];
					if ( '' === fieldData[fieldName] ) {
						allValid = false;
					}
				}
			}
			aco_wc.maybeFreezeIframe( allValid );
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
		 * Maybe prints the validation error message.
		 */
		maybePrintValidationMessage: function() {
			if ( true === aco_wc.blocked && ! $('#aco-required-fields-notice').length ) {
				$('form.checkout').prepend( '<div id="aco-required-fields-notice" class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>' +  aco_wc_params.required_fields_text + '</li></ul></div>' );
				var etop = $('form.checkout').offset().top;
				$('html, body').animate({
					scrollTop: etop
				}, 1000);
			}
		},

		/**
		 * Sets the form fields values from the session storage.
		 */
		setFormFieldValues: function() {
			let form_data = JSON.parse( sessionStorage.getItem( 'acoFieldData' ) );
			if( form_data !== null ) {
				$.each( form_data, function( name, value ) {
					let field = $('*[name="' + name + '"]');
					let saved_value = value;
					// Check if field is a checkbox
					if( field.is(':checkbox') ) {
						if( saved_value !== '' ) {
							field.prop('checked', true);
						}
					} else if( field.is(':radio') ) {
						for ( x = 0; x < field.length; x++ ) {
							if( field[x].value === value ) {
								$(field[x]).prop('checked', true);
							}
						}
					} else {
						field.val( saved_value );
					}

				});
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
			// Check if payson is the selected payment method before we do anything.
			if( aco_wc.checkIfPaysonSelected() ) {
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
