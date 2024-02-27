jQuery( function( $ ) {
    var avarda_checkout_admin = {
       

        openAvardaOrderData: function() {
            $( 'body' ).addClass( 'avarda-order-data-modal-open' );
            $(".avarda-order-data").removeAttr("style").show();
        },
        closeAvardaOrderData: function() {
            $( 'body' ).removeClass( 'avarda-order-data-modal-open' );
            $(".avarda-order-data").removeAttr("style").hide();
        },
    }

    $('body').on('click', '.open-avarda-order-data', function() {
        avarda_checkout_admin.openAvardaOrderData();
    });
    $('body').on('click', '.close-avarda-order-data', function() {
        avarda_checkout_admin.closeAvardaOrderData();
    });

});