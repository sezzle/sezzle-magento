(function () {
    if (typeof window.Review !== "undefined") {
        var target = window.Review;
    }
    else if (typeof window.Payment !== "undefined") {
        var target = window.Payment;
    }
    else {
        var target = false;
    }

    if (target) {
        var reviewSave = target.prototype.save;
        target.prototype.save = function () {
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

                this.saveUrl = window.Sezzlepay.saveUrl;
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
            reviewSave.apply(this, arguments);
        };
    }
})();