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
                            alert('Sezzlepay Gateway is not available.');
                        }
                    }
                );

                this.urls.save = window.Sezzlepay.saveUrl;
                this.setResponse = function (transport) {
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
})();