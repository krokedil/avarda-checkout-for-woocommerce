jQuery(function($) {
	const ACO_wc = {
		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			// Set extra fields values.
			ACO_wc.setFormFieldValues();
			
			// Set the WooCommerce order comment.
			ACO_wc.setOrderComment();

			// Submit the form.
			ACO_wc.submit();
		},

		/*
		 * Prepares and submits the form. 
		 */
		submit: function() {
			// Check any terms checkboxes.
			$('input#terms').prop('checked', true);
			// Submit the form.
			$('form[name="checkout"]').submit();
		},

		checkoutError: function() {
			let error_message = $( ".woocommerce-NoticeGroup-checkout" ).text();
			$.ajax({
				type: 'POST',
				url: ACO_wc_params.checkout_error_url,
				data: {
					error_message: error_message,
					nonce: ACO_wc_params.checkout_error_nonce,
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					if (true === data.responseJSON.success) {
						window.location.href = data.responseJSON.data;
					}
				}
			});
		},

		/**
		 * Sets the order comment, and removes the local storage after.
		 */
		setOrderComment: function() {
			$('#order_comments').val( localStorage.getItem( 'ACO_wc_order_comment' ) );
			localStorage.removeItem( 'ACO_wc_order_comment' );
		},

		/**
		 * Sets the form fields values from the session storage.
		 */
		setFormFieldValues: function() {
			var form_data = JSON.parse( sessionStorage.getItem( 'ACOFieldData' ) );
			$.each( form_data, function( name, value ) {
				var field = $('*[name="' + name + '"]');
				var saved_value = value;
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
		},

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			$(document).ready( ACO_wc.documentReady() );

			// On checkout error.
			//$(document).on( 'checkout_error', ACO_wc.checkoutError() );
			$( document ).on( 'checkout_error', function () {
				ACO_wc.checkoutError();
			});
		},
	}
	ACO_wc.init();
	let ACO_process_text = ACO_wc_params.modal_text;
	$( 'body' ).append( $( '<div class="ACO-modal"><div class="ACO-modal-content">' + ACO_process_text + '</div></div>' ) );
});