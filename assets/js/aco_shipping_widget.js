jQuery(function($) {
    const aco_shipping_widget = {
        listeners: {},
        element : null,
        modules: null,
        paymentMethod: null,
        hasFullAddress: false,
        changedShippingOption: false,
        cartNeedsShipping: false,

        registerEvents: () => {
            // Set the payment method to aco if we have the payment method radio buttons.
            if (0 < $('input[name="payment_method"]').length) {
                aco_shipping_widget.paymentMethod = $('input[name="payment_method"]').filter(':checked').val();
            } else {
                aco_shipping_widget.paymentMethod = 'aco';
            }

            // Display the shipping prices in the order review.
            $(document).ready(aco_shipping_widget.maybeDisplayShippingPrice);
            $(document.body).on('updated_checkout', aco_shipping_widget.maybeDisplayShippingPrice);
        },

        init: (initObject) => {
            const { element, session_id, config } = initObject;
            const $element = $(element);
            aco_shipping_widget.element = $element;
            aco_shipping_widget.cartNeedsShipping = aco_wc_shipping_params.cart_needs_shipping;

            // Json decode the modules.
            aco_shipping_widget.modules = JSON.parse(config.modules);
            aco_shipping_widget.hasFullAddress = aco_shipping_widget.hasFullAddressData();

            // Loop the modules options and create the options HTML.
            const optionsHtml = aco_shipping_widget.getOptionsHtml();

            // Append the options HTML to the element.
            $element.html(optionsHtml);

            // Register the change event for the radio buttons.
            $element.on( "change", 'input:radio[name="aco_shipping_method"]:checked', function () {
                aco_shipping_widget.blockElement("body");
                const shippingMethod = $(this).val();

                // Set the selected shipping method in WooCommerce by checking the radio button in the form.
                $('input:radio[name="shipping_method[0]"][value="' + shippingMethod + '"]').prop("checked", true).trigger("change").trigger("click");

                // Trigger a custom event to let other scripts know that the shipping option has changed.
                if (aco_shipping_widget.modules.selected_option !== shippingMethod) {
                    aco_shipping_widget.changedShippingOption = true;
                    // Trigger the update_checkout event.
                    $(document.body).trigger("update_checkout");
                }

                // Update the select shipping option inside the modules object.
                aco_shipping_widget.modules.selected_option = shippingMethod;
            });

            // Register the click event for the pickup point select box.
            $element.on( 'click', '.pickup-point-select-header', aco_shipping_widget.onPickupPointSelectClick );
            $element.on( 'click', '.pickup-point-select-item', aco_shipping_widget.onChangePickupPoint );

            $(document.body).on('updated_checkout', () => {
                if(aco_shipping_widget.changedShippingOption) {
                    aco_shipping_widget.dispatchEvent("shipping_option_changed");
                    aco_shipping_widget.changedShippingOption = false;
                }

                aco_shipping_widget.getShippingOptions();
            });

            // Dispatch the loaded event once as soon as it is registered by avarda.
            const loadedTimeout = setTimeout(() => {
                if (aco_shipping_widget.listeners["loaded"]) {
                    aco_shipping_widget.dispatchEvent("loaded");
                    clearTimeout(loadedTimeout);
                }
            }, 0);
        },

        hasFullAddressData: () => {
            // Check if we have a value, and thats its not empty for country, postcode, city and address 1. Start with shipping and then check billing.
            const country = ( $('#shipping_country').val() || $('#billing_country').val() );
            const postcode = ( $('#shipping_postcode').val() || $('#billing_postcode').val() );
            const city = ( $('#shipping_city').val() || $('#billing_city').val() );
            const address = ( $('#shipping_address_1').val() || $('#billing_address_1').val() );

            // If any are undefined, or empty strings, return false.
            return country && postcode && city && address;
        },

        getShippingOptions: () => {
            try {
                // Read the .aco-shipping-session field and parse the json from the value.
                const {modules} = JSON.parse($('.aco-shipping-session').val());

                // Set the current selected option so we can check if it has changed when getting them from the server.
                const previousSelectedOption = aco_shipping_widget.modules ? aco_shipping_widget.modules.selected_option : null;

                // Update the modules object with the new shipping options.
                aco_shipping_widget.modules = JSON.parse(modules);

                // Update the options HTML with the new shipping options.
                const optionsHtml = aco_shipping_widget.getOptionsHtml();

                // Replace the options HTML in the element.
                aco_shipping_widget.element.html(optionsHtml);

                // If the modules is null, undefined or and empty object, return.
                if (!aco_shipping_widget.modules || $.isEmptyObject(aco_shipping_widget.modules)) {
                    aco_shipping_widget.unblockElement("body");
                    return;
                }

               // If the selected option has changed, trigger the shipping_option_changed event.
               if (aco_shipping_widget.modules.selected_option !== previousSelectedOption) {
                   aco_shipping_widget.dispatchEvent("shipping_option_changed");
                }
            } catch (error) {
                console.error("Error when getting shipping options for Avarda Checkout.", error);
            }

            aco_shipping_widget.unblockElement("body");
        },

        suspend: () => {
        },

        resume: () => {
        },

        on: (type, listener, options) => {
            if (!aco_shipping_widget.listeners[type]) {
                aco_shipping_widget.listeners[type] = [];
            }

            const listenerObject = {
                listener: listener,
                options: options,
            };

            aco_shipping_widget.listeners[type].push(listenerObject);
        },

        dispatchEvent: (event) => {
            if (aco_shipping_widget.listeners[event]) {
                // Loop each listener and trigger them
                aco_shipping_widget.listeners[event].forEach((listener) => {
                    listener.listener();
                });
            }
        },

        unmount: () => {
        },

        setLanguage: (language) => {
        },

        sessionHasUpdated: () => {
            // Block the body.
            //aco_shipping_widget.blockElement("body");

            // Get the updated shipping options.
            //aco_shipping_widget.getShippingOptions();
        },

        getOptionsHtml: () => {
            const {options, selected_option} = aco_shipping_widget.modules;
            let html = `<style>
                .radio-group {
                    display: flex;
                    flex-direction: column;
                    font-size: 16px;
                    color: #000000;
                    border: 1px solid #ccc;
                    border-radius: 12px;
                }

                .radio-input {
                    display: none;
                }
                .radio-control {
                    padding: 0 20px;
                }

                .radio-box {
                    display: block;
                    padding: 16px 0;
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }

                .radio-label {
                    cursor: pointer;
                }

                .radio-button-wrapper {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                }

                .details {
                    max-height: 0;
                    overflow: hidden;
                    transition: max-height 0.3s ease, padding 0.3s ease;
                }

                .radio-input:checked + .radio-box .details {
                    max-height: fit-content;
                }

                .radio-input:checked + .radio-box .outer-circle {
                    stroke: #000000;
                }

                .radio-input:checked + .radio-box .inner-circle {
                    fill: #000000;
                }

                .outer-circle {
                    stroke: #b2b2b2;
                    fill: none;
                }

                .inner-circle {
                    fill: transparent;
                }

                .details p {
                    margin: 20px 0 0 0;
                }

                .radio-control:not(:last-child) .radio-box {
                    border-bottom: 1px solid #ccc;
                }

                .left-column {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .main-column {
                    flex: 1;
                }
                .right-column {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .price {
                    font-weight: bold;
                }
                .aco-carrier-icon {
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    object-fit: cover;
                }
                .pickup-point-select {
                    border: 1px solid #ccc;
                    border-radius: 12px;
                    margin-top: 20px;
                    overflow: hidden;
                }
                .pickup-point-select-header {
                    padding: 10px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                }
                .pickup-point-select-body {
                    border-top: 1px solid #ccc;
                    display: none;
                }
                .pickup-point-select-item {
                    padding: 10px;
                    border-bottom: 1px solid #ccc;
                    cursor: pointer;
                }
                .pickup-point-select-item:hover {
                    background-color: #f9f9f9;
                }
                .pickup-point-select-item.selected {
                    background-color: #eee;
                }
                .pickup-point-select-item:last-child {
                    border-bottom: none;
                }
                .pickup-point-info {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }
                .pickup-point-name {
                    font-weight: bold;
                    font-size: 13px;
                    color: #000000;
                }
                .pickup-point-address {
                    font-size: 13px;
                    color: #4C4C4C;
                }
                .pickup-point-select .arrow {
                    margin-left: auto;
                    transition: transform 0.1s ease;
                }
                .pickup-point-select.open .arrow {
                    transform: rotate(90deg);
                }
                .pickup-point-select.closed .arrow {
                    transform: rotate(0deg);
                }
                p.description {
                    font-size: 13px;
                    margin-top: 13px;
                    color: #4C4C4C;
                }
                label.disabled {
                    position: relative;
                    cursor: not-allowed;
                    pointer-events: none;
                }
                label.disabled::before {
                    content: "";
                    background-color: rgba(255, 255, 255, 0.5);
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                }
                .aco-spinner {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 200px;
                    flex-direction: column;
                }
                .aco-spinner::before {
                    content: "";
                    width: 40px;
                    height: 40px;
                    border: 5px solid #ccc;
                    border-top-color: #000;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                @keyframes spin {
                    to {
                        transform: rotate(360deg);
                    }
                }
            </style>
            `;

            // If the cart needs shipping, but we don't have any options, show a spinner instead.
            if (aco_shipping_widget.cartNeedsShipping && ( options.length === 0 || options[0].shippingMethod === 'no_shipping' )) {
                html += `<div class='aco-spinner'><p class='aco-no-shipping-methods'>${aco_wc_shipping_params.spinner_text}</p></div>`;
                return html;
            }

            html += "<div class='radio-group'>";
            options.forEach((option) => {
            html += aco_shipping_widget.getOptionHtml(option, selected_option);
            });
            html += "</div>";

            return html;
        },

        getOptionHtml: (option, selected_option) => {
            const price = parseFloat(option.price).toFixed(2);
            const method = option.shippingMethod;
            const formattedMethod = option.shippingMethod.replace(/:/g, "");
            const name = option.shippingProduct;
            const selected = selected_option === method ? "checked" : "";
            const pickupPoints = option.pickupPoints ? option.pickupPoints : [];
            const iconUrl = option.iconUrl ? option.iconUrl : '';
            const description = option.description ? option.description : '';
            const disabled = aco_shipping_widget.hasFullAddress ? "" : "disabled";

            var iconHtml = '';
            if(iconUrl) {
                iconHtml = `<img src="${iconUrl}" alt="Carrier icon" class="aco-carrier-icon">`;
            }

            const html = `
                <div class="radio-control">
                    <input type="radio"
                        id="${formattedMethod}"
                        class="radio-input"
                        name="aco_shipping_method"
                        value="${method}"
                        ${selected ? "checked" : ""}
                        ${disabled}
                    />
                    <div class="radio-box">
                        <label class="radio-label ${disabled}" for="${formattedMethod}">
                            <div class="radio-button-wrapper">
                                <div class="left-column">
                                    <svg width="24" height="24" role="radio">
                                        <circle class="outer-circle" cx="12" cy="12" r="10" stroke-width="2"></circle>
                                        <circle class="inner-circle" cx="12" cy="12" r="5.5"></circle>
                                    </svg>
                                </div>
                                <div class="main-column">${name}</div>
                                <div class="right-column"><span class="price">${aco_shipping_widget.formatPrice(price)}</span>${iconHtml}</div>
                            </div>
                            <div class="details">${description !== '' ? `<p class="description">${description}</p>` : ''}${aco_shipping_widget.getPickupPointsHtml(pickupPoints, method)}</div>
                        </label>
                    </div>
                </div>
            `;
            return html;
        },

        getPickupPointsHtml: (pickupPoints, method) => {
            let html = "";
            if(pickupPoints.length > 0) {
                // Get the selected pickup point from the pickup points.
                const selectedPickupPoint = pickupPoints.find((pickupPoint) => pickupPoint.SelectedPickupPoint === true);
                const selectedAddress = selectedPickupPoint.Descriptions[0] + " " + selectedPickupPoint.Descriptions[1];

                // Output a select box card that has the pickup points as options to show when its clicked.
                html += `<div class="pickup-point-select closed">
                    <div class="pickup-point-select-header">
                        <div class="pickup-point-info">
                            <span class="pickup-point-name">${aco_shipping_widget.formatString(
                              selectedPickupPoint.DisplayName
                            )}</span>
                            <span class="pickup-point-address">${aco_shipping_widget.formatString(
                              selectedAddress
                            )}</span>
                        </div>
                        <svg class="arrow" fill="#000000" height="24px" width="24px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="-33 -33 396.00 396.00" xml:space="preserve" stroke="#000000" stroke-width="6.6"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="1.98"></g><g id="SVGRepo_iconCarrier"> <path id="XMLID_222_" d="M250.606,154.389l-150-149.996c-5.857-5.858-15.355-5.858-21.213,0.001 c-5.857,5.858-5.857,15.355,0.001,21.213l139.393,139.39L79.393,304.394c-5.857,5.858-5.857,15.355,0.001,21.213 C82.322,328.536,86.161,330,90,330s7.678-1.464,10.607-4.394l149.999-150.004c2.814-2.813,4.394-6.628,4.394-10.606 C255,161.018,253.42,157.202,250.606,154.389z"></path> </g></svg>
                    </div>
                    <div class="pickup-point-select-body">
                `;
                pickupPoints.forEach((pickupPoint) => {
                    let selected = pickupPoint.SelectedPickupPoint ? "selected" : "";
                    let address =
                      pickupPoint.Descriptions[0] +
                      " " +
                      pickupPoint.Descriptions[1];
                    html += `<div class="pickup-point-select-item  ${selected}" data-rate-id="${method}" data-merchant-reference="${
                      pickupPoint.MerchantReference
                    }">
                        <div class="pickup-point-info">
                            <span class="pickup-point-name">${aco_shipping_widget.formatString(
                              pickupPoint.DisplayName
                            )}</span>
                            <span class="pickup-point-address">${aco_shipping_widget.formatString(
                              address
                            )}</span>
                        </div>
                    </div>`;
                });

                html += `</div></div>`;
            }

            return html;
        },

        onPickupPointSelectClick: (event) => {
            const $header = $(event.target);
            // Get the wrapper element from the header or headers child elements.
            const $wrapper = $header.hasClass("pickup-point-select") ? $header : $header.parents(".pickup-point-select");
            const $body = $wrapper.find(".pickup-point-select-body");

            // Toggle the body element.
            $wrapper.toggleClass("open");
            $wrapper.toggleClass("closed");
            $body.slideToggle();
        },

        onChangePickupPoint: (event) => {
            const $select = $(event.target).hasClass("pickup-point-select-item") ? $(event.target) : $(event.target).parents(".pickup-point-select-item");
            const $body = $select.parents(".pickup-point-select-body");
            const $header = $body.siblings(".pickup-point-select-header");
            const $wrapper = $header.parents(".pickup-point-select");
            const merchantReference = $select.data('merchant-reference');

            // Set the selected pickup point in WooCommerce by selecting the option in the form.
            aco_shipping_widget.syncWithKrokedilShippingSelect(merchantReference);
            aco_shipping_widget.maybeSyncWithWebshipper(merchantReference);
            aco_shipping_widget.dispatchEvent("shipping_option_changed");

            // Copy the selected pickup point to the header.
            $header
              .find(".pickup-point-info")
              .html($select.find(".pickup-point-info").html());

            // Toggle the body element.
            $select.addClass("selected");
            $body.find(".pickup-point-select-item").not($select).removeClass("selected");
            $body.slideToggle();
            $wrapper.toggleClass("open");
            $wrapper.toggleClass("closed");
        },

        syncWithKrokedilShippingSelect: (value) => {
            // If we don't have a value, just return and do nothing.
            if (!value) {
                return;
            }

            aco_shipping_widget.blockElement(".woocommerce-checkout-review-order-table");
            aco_shipping_widget.blockElement("#aco-iframe");

            // Set the selected pickup point in WooCommerce by selecting the option in the form.
            $("#krokedil_shipping_pickup_point").val(value).trigger("change");

            aco_shipping_widget.unblockElement(".woocommerce-checkout-review-order-table");
            aco_shipping_widget.unblockElement("#aco-iframe");
        },

        maybeSyncWithWebshipper: (value) => {
            const $select = $('select[name="ws_drop_point_blob"]');
            if ($select.length === 0) {
                return;
            }

            // Set the option that contains the value in the option value.
            const $option = $select.find('option').filter(function() {
                return this.value.includes(value);
            });

            // If we have an option, set the select box to the value of the option.
            if ($option.length > 0) {
                $select.val($option.val()).trigger("change");
            }
        },

        /**
		 * Display Shipping Price in order review if Display shipping methods in iframe settings is active.
		 */
		maybeDisplayShippingPrice: function() {
			// Check if we already have set the price. If we have, return.
			if( $('.aco-woo-shipping').length ) {
				return;
			}
			if ( 'aco' === aco_shipping_widget.paymentMethod && 'yes' === aco_wc_shipping_params.integrated_shipping_woocommerce ) {
				if ( $( '#shipping_method input[type=\'radio\']' ).length ) {
					// Multiple shipping options available.
					$( '#shipping_method input[type=\'radio\']:checked' ).each( function() {
						var idVal = $( this ).attr( 'id' );
						var shippingPrice = $( 'label[for=\'' + idVal + '\']' ).text();
						$( '.woocommerce-shipping-totals td' ).append( shippingPrice );
						$( '.woocommerce-shipping-totals td' ).addClass( 'aco-woo-shipping' );
					});
				} else {
					// Only one shipping option available.
					var idVal = $( '#shipping_method input[name=\'shipping_method[0]\']' ).attr( 'id' );
					var shippingPrice = $( 'label[for=\'' + idVal + '\']' ).text();
					$( '.woocommerce-shipping-totals td' ).append( shippingPrice );
					$( '.woocommerce-shipping-totals td' ).addClass( 'aco-woo-shipping' );
				}
			}
		},

        /**
         * Blocks the element with the given selector.
         *
         * @param {string} selector
         */
        blockElement: (selector) => {
            $(selector).block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            });
        },

        /**
         * Unblocks the element with the given selector.
         *
         * @param {string} selector
         */
        unblockElement: (selector) => {
            $(selector).unblock();
        },

        /**
         * Format the price with the currency symbol according to the WooCommerce settings.
         *
         * @param {string|number} price
         * @returns {string}
         */
        formatPrice: (price) => {
            const priceFormat = aco_wc_shipping_params.price_format;
            const format = priceFormat.format;
            const symbol = priceFormat.symbol;
            return format.replace("%2$s", price).replace("%1$s", symbol);
        },

        /**
         * Format a string to have the first letter of each word capitalized.
         *
         * @param {string} string
         * @returns {string}
         */
        formatString: (string) => {
            string = string.toLowerCase();
            const words = string.split(" ");

            for (let i = 0; i < words.length; i++) {
                // If the string is empty, continue to the next iteration.
                if (words[i] === "") {
                    continue;
                }

              words[i] = words[i][0].toUpperCase() + words[i].substr(1);
            }

            return words.join(" ");
        },

        /**
         * Set that we now have full address details, and show the shipping methods again.
         *
         * @param {boolean} hasFullAddress
         * @returns {void}
         */
        setHasFullAddress: (hasFullAddress) => {
            // Only if the value has changed.
            if (aco_shipping_widget.hasFullAddress === hasFullAddress) {
                return;
            }

            aco_shipping_widget.hasFullAddress = hasFullAddress;
            aco_shipping_widget.getShippingOptions();
        }

    };

    // Make the aco_shipping_widget object available globally under avardaShipping.
    window.avardaShipping = aco_shipping_widget;

    // Trigger the load function to register events that does not need the init object.
    aco_shipping_widget.registerEvents();
});
