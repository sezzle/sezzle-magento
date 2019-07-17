(function () {
    var form = $('onestepcheckout-form');
    var action = form.getAttribute('action');

    // save in variable for default .submit
    var original = form.submit; 

    form.submit = function () {
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
                        alert(Translator.translate('Unable to reach Sezzle Gateway').stripTags());
                    }
                }
            );

            // prepare params
            var params = form.serialize(true);
            var customerPassword = jQuery('#billing\\:customerPassword').val();
            if(typeof customerPassword !== 'undefined' && customerPassword.length) {
                params.create_account = 1;
            }
            doSezzlepayAPICall(window.Sezzlepay.saveUrl, params);
            return false;
        } else {
            original.apply(this, arguments);
        }
    };
})();


function doSezzlepayAPICall(saveURL, params) 
{
    // Ajax to start order token
    var request = new Ajax.Request(
        saveURL,
        {
            method: 'post',
            parameters: params,
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
}