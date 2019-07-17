(function () {
        var reviewSave = window.FireCheckout.prototype.save;
        window.FireCheckout.prototype.save = function () {
            // check if payment method is sezzlepay
            if (payment.currentMethod == 'sezzlepay') {
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

                this.urls.save = window.Sezzlepay.saveUrl;
                this.setResponse = function (transport) {
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
            }
            reviewSave.apply(this, arguments);
        };
})();