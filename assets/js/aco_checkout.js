jQuery(function($) {
	const ACO_wc = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',

		// Order notes.
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),

		// Payment method
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#ACO-select-other',

		// Address data.
		addressData: [],

		// Extra checkout fields.
		blocked: false,
		extraFieldsSelectorText: 'div#ACO-extra-checkout-fields input[type="text"], div#ACO-extra-checkout-fields input[type="password"], div#ACO-extra-checkout-fields textarea, div#ACO-extra-checkout-fields input[type="email"], div#ACO-extra-checkout-fields input[type="tel"]',
		extraFieldsSelectorNonText: 'div#ACO-extra-checkout-fields select, div#ACO-extra-checkout-fields input[type="radio"], div#ACO-extra-checkout-fields input[type="checkbox"], div#ACO-extra-checkout-fields input.checkout-date-picker, input#terms input[type="checkbox"]',


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
			if (ACO_wc.paymentMethodEl.length > 0) {
				ACO_wc.paymentMethod = ACO_wc.paymentMethodEl.filter(':checked').val();
				if( 'Avarda_Checkout' === ACO_wc.paymentMethod ) {
					return true;
				}
			} 
			return false;
		},

		// When "Change to another payment method" is clicked.
		changeFromACO: function(e) {
			e.preventDefault();

			$(ACO_wc.checkoutFormSelector).block({
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
					ACO: false,
					nonce: ACO_wc_params.change_payment_method_nonce
				},
				url: ACO_wc_params.change_payment_method_url,
				success: function (data) {},
				error: function (data) {},
				complete: function (data) {
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},

		// When payment method is changed to ACO in regular WC Checkout page.
		maybeChangeToACO: function() {
			if ( 'ACO' === $(this).val() ) {

				$(ACO_wc.checkoutFormSelector).block({
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
						ACO: true,
						nonce: ACO_wc_params.change_payment_method_nonce
					},
					dataType: 'json',
					url: ACO_wc_params.change_payment_method_url,
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
			localStorage.setItem( 'ACO_wc_order_comment', val );
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
						if ($.inArray(name, ACO_wc_params.standard_woo_checkout_fields) == '-1' && name.indexOf('[qty]') < 0 && name.indexOf( 'shipping_method' ) < 0 && name.indexOf( 'payment_method' ) < 0 ) {
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
				sessionStorage.setItem( 'ACORequiredFields', JSON.stringify( requiredFields ) );
				sessionStorage.setItem( 'ACOFieldData', JSON.stringify( fieldData ) );
				ACO_wc.validateRequiredFields();
		},

		/**
		 * Validates the required fields, checks if they have a value set.
		 */
		validateRequiredFields: function() {
			// Get data from session storage.
			let requiredFields = JSON.parse( sessionStorage.getItem( 'ACORequiredFields' ) );
			let fieldData = JSON.parse( sessionStorage.getItem( 'ACOFieldData' ) );
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
			ACO_wc.maybeFreezeIframe( allValid );
		},

		/**
		 * Maybe freezes the iframe to prevent anyone from completing the order before filling in all required fields.
		 * 
		 * @param {boolean} allValid 
		 */
		maybeFreezeIframe: function( allValid ) {
			if ( true === allValid ) {
				ACO_wc.blocked = false;
				$('#ACO-required-fields-notice').remove();
				// Unblock iframe
			} else 	if( ! $('#ACO-required-fields-notice').length ) { // Only if we dont have an error message already.
				ACO_wc.blocked = true;
				ACO_wc.maybePrintValidationMessage();
				// Block iframe
			}
		},

		/**
		 * Maybe prints the validation error message.
		 */
		maybePrintValidationMessage: function() {
			if ( true === ACO_wc.blocked && ! $('#ACO-required-fields-notice').length ) {
				$('form.checkout').prepend( '<div id="ACO-required-fields-notice" class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>' +  ACO_wc_params.required_fields_text + '</li></ul></div>' );
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
			let form_data = JSON.parse( sessionStorage.getItem( 'ACOFieldData' ) );
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
			$('.woocommerce-additional-fields').appendTo('#ACO-extra-checkout-fields');

			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
			for ( i = 0; i < form.length; i++ ) {
				let name = form[i]['name'];
				// Check if this is a standard field.
				if ( $.inArray( name, ACO_wc_params.standard_woo_checkout_fields ) === -1 ) {
					// This is not a standard Woo field, move to our div.
					$('p#' + name + '_field').appendTo('#ACO-extra-checkout-fields');
				}
			}
		},

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			// Check if payson is the selected payment method before we do anything.
			if( ACO_wc.checkIfPaysonSelected() ) {
				$(document).ready( ACO_wc.documentReady() );
			
				// Change from ACO.
				ACO_wc.bodyEl.on('click', ACO_wc.selectAnotherSelector, ACO_wc.changeFromACO);

				// Catch changes to order notes.
				ACO_wc.bodyEl.on('change', '#order_comments', ACO_wc.updateOrderComment);

				// Extra checkout fields.
				ACO_wc.bodyEl.on('blur', ACO_wc.extraFieldsSelectorText, ACO_wc.checkFormData);
				ACO_wc.bodyEl.on('change', ACO_wc.extraFieldsSelectorNonText, ACO_wc.checkFormData);
				ACO_wc.bodyEl.on('click', 'input#terms', ACO_wc.checkFormData);

			}
			ACO_wc.bodyEl.on('change', 'input[name="payment_method"]', ACO_wc.maybeChangeToACO);
		},
	}
	ACO_wc.init();
});
