(function () {
    if (typeof window.oscPlaceOrder !== 'undefined') {
        var original = window.oscPlaceOrder;
        window.oscPlaceOrder = function (element) {
            var validator = new Validation('one-step-checkout-form');
            var form = $('one-step-checkout-form');
            if (validator.validate() && $('p_method_sezzlepay') && $('p_method_sezzlepay').checked) {
                // send logs
                var sendAllLogs = window.Sezzlepay.sendAllLogs ? 1 : 0;
                new Ajax.Request(
                    window.Sezzlepay.logUrl,
                    {
                        method: 'post',
                        parameters: {
                            'all-logs': sendAllLogs
                        },
                        onFailure: function () {
                            alert(Translator.translate('Unable to reach Sezzle Gateway').stripTags());
                        }
                    }
                );
                
                $('onestepcheckout-place-order-loading').show();
                $('onestepcheckout-button-place-order').removeClassName('onestepcheckout-btn-checkout');
                $('onestepcheckout-button-place-order').addClassName('place-order-loader');

                var payment_method = $RF(form, 'payment[method]');
                var shipping_method = $RF(form, 'shipping_method');
                var parameters = {
                    payment: payment_method,
                    shipping_method: shipping_method
                };
                get_billing_data(parameters);
                get_shipping_data(parameters);

                if ($('giftmessage-type') && $('giftmessage-type').value != '') {
                    parameters[$('giftmessage-type').name] = $('giftmessage-type').value;
                }

                if ($('create_account_checkbox_id') && $('create_account_checkbox_id').checked) {
                    parameters['create_account_checkbox'] = 1;
                }

                if ($('gift-message-whole-from') && $('gift-message-whole-from').value != '') {
                    parameters[$('gift-message-whole-from').name] = $('gift-message-whole-from').value;
                }

                if ($('gift-message-whole-to') && $('gift-message-whole-to').value != '') {
                    parameters[$('gift-message-whole-to').name] = $('gift-message-whole-to').value;
                }

                if ($('gift-message-whole-message') && $('gift-message-whole-message').value != '') {
                    parameters[$('gift-message-whole-message').name] = $('gift-message-whole-message').value;
                }

                if ($('billing-address-select') && $('billing-address-select').value != '') {
                    parameters[$('billing-address-select').name] = $('billing-address-select').value;
                }

                if ($('shipping-address-select') && $('shipping-address-select').value != '') {
                    parameters[$('shipping-address-select').name] = $('shipping-address-select').value;
                }

                /**
                 * Perform ajax to Sezzle to get order token
                 */
                new Ajax.Request(
                    window.Sezzlepay.saveUrl,
                    {
                        method: 'post',
                        parameters: parameters,
                        onSuccess: function (transport) {
                            var response = {};

                            try {
                                response = transport.responseJSON || transport.responseText.evalJSON(true) || {};
                            }
                            catch (e) {
                                response = {};
                            }

                            if (response.redirect) {
                                location.href = encodeURI(response.redirect);
                            }
                            else {
                                alert(Translator.translate('Unable to reach Sezzle Gateway').stripTags());
                                location.href = encodeURI(window.Sezzlepay.cartUrl);
                            }

                        }.bind(this),
                        onFailure: function () {
                            alert(Translator.translate('Unable to reach Sezzle Gateway').stripTags());
                            location.href = encodeURI(window.Sezzlepay.cartUrl);
                        }
                    }
                );
            } else {
                /**
                 * Call original function
                 */
                original.apply(this, arguments);
            }
        };
    }
})();