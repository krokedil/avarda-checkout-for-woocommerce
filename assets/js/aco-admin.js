jQuery( function( $ ) {
    var avarda_checkout_admin = {
        toggle_button: $( ".aco_order_sync--toggle .woocommerce-input-toggle" ),
        toggle_advanced_button: $( ".aco-order-advanced-toggle" ),

        openAvardaOrderData: function() {
            $( 'body' ).addClass( 'avarda-order-data-modal-open' );
            $(".avarda-order-data").removeAttr("style").show();
        },
        closeAvardaOrderData: function() {
            $( 'body' ).removeClass( 'avarda-order-data-modal-open' );
            $(".avarda-order-data").removeAttr("style").hide();
        },
        toggleOrderSync: function(e) {
            e.preventDefault();
			//$(avarda_checkout_admin.toggle_button).addClass( 'disabled' );
			// $('.aco_order_sync--toggle .woocommerce-input-toggle').prop('disabled', true);
            var orderSyncStatus = avarda_checkout_admin.toggle_button.data('order-sync-status');
            console.log('orderSyncStatus', orderSyncStatus);
            $('#wpcontent').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } })
            if( 'enabled' === orderSyncStatus ) {
                var newOrderSyncStatus = 'disabled';
            } else {
                var newOrderSyncStatus = 'enabled';
            }

            $.ajax({
				type: 'POST',
				data: {
					nonce: aco_admin_params.aco_order_sync_toggle_nonce,
                    order_id: aco_admin_params.order_id,
                    new_order_sync_status: newOrderSyncStatus,
                    action: 'aco_order_sync_toggle',
				},
				dataType: 'json',
				url: ajaxurl,
				success: function (data) {
					console.log(data);
					if(data.success) {
						window.location.reload();
					} else {
                        $('#wpcontent').unblock();
						avarda_checkout_admin.toggle_button.append( '<div><i>' + data.data + '</i></div>' );
						alert( data.data );
					}
				},
				error: function (data) {
                    $('#wpcontent').unblock();
					console.log(data);
					console.log(data.statusText);
				},
				complete: function (data) {

				}
			});
            //

        },
        toggleAdvancedButton: function(e) {
            e.preventDefault();
			//$(".aco-order-advanced").toggle();

            if( $( ".aco-order-advanced" ).hasClass( "closed" )) {
                console.log('has class closed');
                $( ".aco-order-advanced" ).removeClass( "closed" );
                $( ".aco-order-advanced-toggle" ).removeClass( "closed" )
            } else {
                console.log('do not has class closed');
                $( ".aco-order-advanced" ).addClass( "closed" );
                $( ".aco-order-advanced-toggle" ).addClass( "closed" )
            }
            

        },
    }

    $('body').on('click', '.open-avarda-order-data', function() {
        avarda_checkout_admin.openAvardaOrderData();
    });
    $('body').on('click', '.close-avarda-order-data', function() {
        avarda_checkout_admin.closeAvardaOrderData();
    });

    avarda_checkout_admin.toggle_button.click( function (e) {
        avarda_checkout_admin.toggleOrderSync(e);
    });

    avarda_checkout_admin.toggle_advanced_button.click( function (e) {
        avarda_checkout_admin.toggleAdvancedButton(e);
    });


});
