jQuery(function($) {
	const aco_utility = {
        bodyEl: $('body'),
        checkoutFormSelector: 'form.checkout',

		// When payment method is changed to ACO in regular WC Checkout page.
		maybeChangeToACO: function() {
			if ( 'aco' === $(this).val() ) {

				$(aco_utility.checkoutFormSelector).block({
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
						nonce: aco_utility_params.change_payment_method_nonce
					},
					dataType: 'json',
					url: aco_utility_params.change_payment_method_url,
					success: function (data) {},
					error: function (data) {},
					complete: function (data) {
						window.location.href = data.responseJSON.data.redirect;
					}
				});
			}
		},



		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			aco_utility.bodyEl.on('change', 'input[name="payment_method"]', aco_utility.maybeChangeToACO);
		},
	}
	aco_utility.init();
});
