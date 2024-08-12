jQuery(function($) {
    const aco_shipping_widget = {
        listeners: {},
        element : null,

        init: (initObject) => {
            const $element = $(initObject.element);
            aco_shipping_widget.element = $element;

            // Json decode the modules.
            const modules = JSON.parse(initObject.config.modules);

            // Loop the modules options and create the options HTML.
            const optionsHtml = aco_shipping_widget.getOptionsHtml(modules.options, modules.selected_option);

            // Append the options HTML to the element.
            $element.append(optionsHtml);

            // Register the change event for the radio buttons.
            $element.on( "change", 'input:radio[name="aco_shipping_method"]:checked', function () {
                const shippingMethod = $(this).val();
                // Set the selected shipping method in WooCommerce by checking the radio button in the form.
                $('input:radio[name="shipping_method[0]"][value="' + shippingMethod + '"]').prop("checked", true).trigger("change");
            }
            );

            // If we have a listener for the shipping_option_changed event, trigger it.
            $('body').on( 'updated_checkout', function() {
                aco_shipping_widget.dispatchEvent("shipping_option_changed");
            });

            // Register the click event for the pickup point select box.
            $element.on( 'click', '.pickup-point-select-header', aco_shipping_widget.onPickupPointSelectClick );
            $element.on( 'click', '.pickup-point-select-item', aco_shipping_widget.onChangePickupPoint );

            // Set the payment method to aco if we have the payment method radio buttons.
            if ( 0 < $('input[name="payment_method"]').length ) {
                aco_shipping_widget.paymentMethod = $('input[name="payment_method"]').filter( ':checked' ).val();
            } else {
                aco_shipping_widget.paymentMethod = 'aco';
            }

            // Display the shipping price in the order review.
            $(document).ready(aco_shipping_widget.maybeDisplayShippingPrice);
            $('body').on( 'updated_checkout', aco_shipping_widget.maybeDisplayShippingPrice );

            // Trigger the loaded event.
            aco_shipping_widget.dispatchEvent("loaded");
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

            aco_shipping_widget.listeners[type] = listenerObject;
        },

        dispatchEvent: (event) => {
            if (aco_shipping_widget.listeners[event]) {
                aco_shipping_widget.listeners[event].listener();
            }
        },

        unmount: () => {
        },

        setLanguage: (language) => {
        },

        sessionHasUpdated: () => {
            // Reload the window to ensure the checkout is loaded with new shipping options.
            window.location.reload();
        },

        getOptionsHtml: (options, selectedOption) => {
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
                    padding-top: 10px;
                    padding-bottom: 10px;
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
                    margin-top: 10px;
                    margin-left: 34px;
                    overflow: hidden;
                }
                .pickup-point-select-header {
                    padding: 10px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                }
                .pickup-point-select-body {
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
            </style>
            <div class="radio-group">
            `;
            options.forEach((option) => {
            html += aco_shipping_widget.getOptionHtml(option, selectedOption);
            });
            html += "</div>";

            return html;
        },

        getOptionHtml: (option, selectedOption) => {
            const price = parseFloat(option.price).toFixed(2);
            const method = option.shippingMethod;
            const formattedMethod = option.shippingMethod.replace(/:/g, "");
            const name = option.shippingProduct;
            const selected = selectedOption === method ? "checked" : "";
            const pickupPoints = option.pickupPoints ? option.pickupPoints : [];
            const iconUrl = option.iconUrl ? option.iconUrl : '';
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
                    />
                    <div class="radio-box">
                        <label class="radio-label" for="${formattedMethod}">
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
                            <div class="details">
                                ${aco_shipping_widget.getPickupPointsHtml(pickupPoints, method)}
                            </div>
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
                    let address =
                      pickupPoint.Descriptions[0] +
                      " " +
                      pickupPoint.Descriptions[1];
                    html += `<div class="pickup-point-select-item" data-rate-id="${method}" data-merchant-reference="${
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
            aco_shipping_widget.dispatchEvent("shipping_option_changed");

            // Copy the selected pickup point to the header.
            $header
              .find(".pickup-point-info")
              .html($select.find(".pickup-point-info").html());

            // Toggle the body element.
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
              words[i] = words[i][0].toUpperCase() + words[i].substr(1);
            }

            return words.join(" ");
        },

    };

    // Make the aco_shipping_widget object available globally under avardaShipping.
    window.avardaShipping = aco_shipping_widget;
});
