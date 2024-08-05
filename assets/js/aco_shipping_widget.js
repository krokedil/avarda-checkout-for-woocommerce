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

        // If we have a listener for the shipping_option_changed event, trigger it.
        $('body').on( 'updated_checkout', function() {
                if (aco_shipping_widget.listeners["shipping_option_changed"] !== undefined) {
                    aco_shipping_widget.listeners["shipping_option_changed"].listener()
                }
            });
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
                            <svg width="24" height="24" role="radio">
                                <circle class="outer-circle" cx="12" cy="12" r="10" stroke-width="2"></circle>
                                <circle class="inner-circle" cx="12" cy="12" r="5.5"></circle>
                            </svg>
                            ${name} - ${price} ${currency}
                        </div>
                        <div class="details">
                            <p>TODO: Pickup point, icon, description if exists etc...</p>
                        </div>
                    </label>
                </div>
            </div>
            `;

        return html;
      },
    };

    // Make the aco_shipping_widget object available globally under avardaShipping.
    window.avardaShipping = aco_shipping_widget;
});
