jQuery(function($) {
    const aco_shipping_widget = {
      listeners: {},
      init: (initObject) => {
        const $element = $(initObject.element);

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

        // Register the change event for changed pickup point.
        $element.on( 'change', 'select[name="aco_pickup_point"]', function( event) {
            //aco_shipping_widget.onChangedPickupPoint(event);
            aco_shipping_widget.syncWithKrokedilShippingSelect(event);
        });

        // If we have a listener for the shipping_option_changed event, trigger it.
        $('body').on( 'updated_checkout', function() {
            if (aco_shipping_widget.listeners["shipping_option_changed"] !== undefined) {
                aco_shipping_widget.listeners["shipping_option_changed"].listener()
            }
        });

        // Set the payment method to aco if we have the payment method radio buttons.
        if ( 0 < $('input[name="payment_method"]').length ) {
            aco_shipping_widget.paymentMethod = $('input[name="payment_method"]').filter( ':checked' ).val();
        } else {
            aco_shipping_widget.paymentMethod = 'aco';
        }

        // Display the shipping price in the order review.
        $(document).ready(aco_shipping_widget.maybeDisplayShippingPrice);
        $('body').on( 'updated_checkout', aco_shipping_widget.maybeDisplayShippingPrice );
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
      },
      unmount: () => {
      },
      setLanguage: (language) => {
      },
      sessionHasUpdated: () => {
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
                max-height: 100px;
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
                font-weight: normal;
            }
            .aco-carrier-icon {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                object-fit: cover;
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
        const currency = option.currency;
        const carrier = option.carrier;
        const pickupPoints = option.pickupPoints ? option.pickupPoints : [];
        const iconUrl = option.iconUrl ? option.iconUrl : '';
        var iconHtml = '';
        if(iconUrl) {
            iconHtml = `<img src="${iconUrl}" alt="Carrier icon" class="aco-carrier-icon">`;
        }
        console.log('option', option);

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
                            <div class="right-column"><span class="price">${price} ${currency}</span>${iconHtml}</div>
                            
                            
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
            
            html += `<select name="aco_pickup_point" data-rate-id=${method} id="aco_pickup_point">`;
            pickupPoints.forEach((pickupPoint) => {
                let SelectedStatus = pickupPoint.SelectedPickupPoint === true ? "selected" : "";
                html += `<option value="${pickupPoint.MerchantReference}" ${SelectedStatus}>${pickupPoint.DisplayName}</option>`;
            });
            html += "</select>";
        }

        return html;
      },

      onChangedPickupPoint: (event) => {
        const select = $(event.target);
        const value = select.val();
        const rateId = select.data("rate-id");
        console.log('onChangedPickupPoint');
        console.log('value', value);
        console.log('rateId', rateId);

        // If we don't have a value, just return and do nothing.
        if (!value) {
            return;
        }

        const ajaxParams = ks_pp_params.ajax.setPickupPoint;

        aco_shipping_widget.blockElement(".woocommerce-checkout-review-order-table");
        aco_shipping_widget.blockElement("#aco-iframe");

        // Make a ajax request to the server to update the pickup point.
        $.ajax({
            type: "POST",
            url: ajaxParams.url,
            data: {
            nonce: ajaxParams.nonce,
            rateId: rateId,
            pickupPointId: value,
            },
            success: (response) => {
                aco_shipping_widget.unblockElement(".woocommerce-checkout-review-order-table");
                aco_shipping_widget.unblockElement("#aco-iframe");
                // Test if the response is a success or not.
                console.log('onChangedPickupPoint response', response);
                if (!response.success) {
                    console.log(response.data);
                }
            },
            error: (response) => {
                aco_shipping_widget.unblockElement(".woocommerce-checkout-review-order-table");
                aco_shipping_widget.unblockElement("#aco-iframe");
                console.log(response);
            },
        });

        
    },  
    syncWithKrokedilShippingSelect: (event) => {
        const select = $(event.target);
        const value = select.val();
        const rateId = select.data("rate-id");
        console.log('onChangedPickupPoint');
        console.log('value', value);
        console.log('rateId', rateId);

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
    
    };

    // Make the aco_shipping_widget object available globally under avardaShipping.
    window.avardaShipping = aco_shipping_widget;
});
