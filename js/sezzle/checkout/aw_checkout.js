(function () {

    if (typeof window.AWOnestepcheckoutForm !== "undefined") {
        var target = window.AWOnestepcheckoutForm;
    }
    else {
        var target = false;
    }

    if (target) {
        var orderSave = target.prototype.placeOrder;

        target.prototype.placeOrder = function () {
            // check if payment method is sezzlepay
            if (this.validate() && $('p_method_sezzlepay') && $('p_method_sezzlepay').checked) {
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

                this.placeOrderUrl = window.Sezzlepay.saveUrl;
                this.onComplete = function (transport) {
                    var response = {};
                    try {
                        response = transport.responseJSON || transport.responseText.evalJSON(true) || {};
                    } catch (e) {
                        response = {};
                    }
                    if (response.redirect) {
                        location.href = encodeURI(response.redirect);
                    }
                    else {
                        alert(Translator.translate('Unable to reach Sezzle Gateway').stripTags());
                        location.href = encodeURI(window.Sezzlepay.cartUrl);
                    }
                };
                this.onFailure = function () {
                    alert(Translator.translate('Unable to reach Sezzle Gateway').stripTags());
                    location.href = encodeURI(window.Sezzlepay.cartUrl);
                }
            }
            orderSave.apply(this, arguments);
        };
    }
})();
