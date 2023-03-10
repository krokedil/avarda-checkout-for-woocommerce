jQuery(function ($) {
	var acoAdmin = {
		init: function () {
			$('body').on('click', '.aco-refund-remaining-btn', acoAdmin.syncOrderBtn );

		},

		syncOrderBtn:function(e) {
			e.preventDefault();
			$('.aco-refund-remaining-btn').addClass( 'disabled' );
			$('.aco-refund-remaining-btn').prop('disabled', true);
			$.ajax({
				type: 'POST',
				data: {
					id: acoParams.order_id,
					nonce: acoParams.aco_refund_remaining_order_nonce,
				},
				dataType: 'json',
				url: acoParams.aco_refund_remaining_order,
				success: function (data) {
					console.log(data);
					if(data.success) {
						window.location.reload();
					} else {
						$('.aco-refund-remaining-btn').removeClass( 'disabled' );
						$('.aco-refund-remaining-btn').prop('disabled', false);
						$('.walley_sync_wrapper').append( '<div><i>' + data.data + '</i></div>' );
						alert( data.data );
					}
				},
				error: function (data) {
					console.log(data);
					console.log(data.statusText);
				},
				complete: function (data) {

				}
			});
		},
	}
	acoAdmin.init();
});