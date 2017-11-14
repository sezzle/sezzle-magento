// Using Afterpay's idea of overriding payment flow using JS
// Check code here: https://github.com/afterpay/afterpay-magento/blob/master/src/js/Afterpay/checkout/onepage.js
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
            // check payment method
            if (payment.currentMethod == 'pay') {
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