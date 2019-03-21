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
                            alert('Sezzlepay Gateway is not available.');
                        }
                    }
                );

                this.saveUrl = window.Sezzlepay.saveUrl;
                this.onComplete = function (transport) {
                    var response = {};
                    // Parse the response - lifted from original method
                    try {
                        response = eval('(' + transport.responseText + ')');
                    } catch (e) {
                        response = {};
                    }
                    if (response.redirect) {
                        location.href = response.redirect
                    }
                };
            }
            reviewSave.apply(this, arguments);
        };
    }
})();