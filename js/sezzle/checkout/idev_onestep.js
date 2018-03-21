// Using Afterpay's idea of overriding payment flow using JS
// Check code here: https://github.com/afterpay/afterpay-magento/blob/master/src/js/Afterpay/checkout/idev_onestep.js

/**
 * Function specifically for Idev OneStepCheckout. The class especially for it
 */
(function() {
    var form = $('onestepcheckout-form');
    var action = form.getAttribute('action');

    // save in variable for default .submit
    var original = form.submit; 

    form.submit = function() {
        if (payment.currentMethod == 'sezzlepay') {
            // send logs
            var sendAllLogs = window.Sezzlepay.sendAllLogs ? 1 : 0;
            console.log('sendAllLogs', sendAllLogs);
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

            // prepare params
            var params = form.serialize(true);

            var customer_password = jQuery('#billing\\:customer_password').val();

            if( typeof customer_password !== 'undefined' && customer_password.length ) {
                params.create_account = 1;
            }

            doSezzlepayAPICall(window.Sezzlepay.saveUrl, params);
            return false;

        } else {
            original.apply(this, arguments);
        }
    };
})();


function doSezzlepayAPICall(saveURL, params) {
    // Ajax to start order token
    var request = new Ajax.Request(
        saveURL,
        {
            method: 'post',
            parameters: params,
            onSuccess: function (transport) {
                var response = {};

                try {
                    response = eval('(' + transport.responseText + ')');
                }
                catch (e) {
                    response = {};
                }
                
                if (response.redirect) {
                    location.href = response.redirect
                }

            }.bind(this),
            onFailure: function () {
                alert('Sezzlepay Gateway is not available.');
            }
        }
    );
}