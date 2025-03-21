jQuery(function ($) {
    const aco_settings_page = {
        $shippingBrokerKeyInput: $("#woocommerce_aco_shipping_broker_api_key"),
        $integratedShippingSelect: $("#woocommerce_aco_integrated_shipping"),
        init: function () {
            this.maybeHideShippingBrokerKeyInput();
            this.bindEvents();
        },

        maybeHideShippingBrokerKeyInput: function () {
            const value = this.$integratedShippingSelect.val();

            // If the value is "woocommerce" show the shipping broker key input
            if (value === "woocommerce") {
                this.showRow(this.$shippingBrokerKeyInput);
            } else {
                this.hideRow(this.$shippingBrokerKeyInput);
            }
        },

        bindEvents: function () {
            this.$integratedShippingSelect.on(
                "change",
                this.maybeHideShippingBrokerKeyInput.bind(this)
            );
        },

        getParentRow: function ($element) {
            return $element.closest("tr");
        },

        showRow: function ($element) {
            const $row = this.getParentRow($element);
            $row.show();
        },

        hideRow: function ($element) {
            const $row = this.getParentRow($element);
            $row.hide();
        },
    };

    aco_settings_page.init();
});
