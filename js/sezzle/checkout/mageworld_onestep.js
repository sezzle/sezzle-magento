jQuery(document).ready(
    function () {
    var form = document.getElementById('onestep_form');
    var action = form.getAttribute('action');

    // save in variable for default .submit
    var original = form.submit;
    
    //hacks the form to prevent override by other plugins
    jQuery(".btn-checkout").on(
        "click", function (e) {
    
        if (payment.currentMethod == 'sezzlepay') {
            e.preventDefault();
            e.stopPropagation();
            var sendAllLogs = window.Sezzlepay.sendAllLogs ? 1 : 0;
            // send logs
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

            // Ajax to start order token
            new Ajax.Request(
                window.Sezzlepay.saveUrl,
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
        } else {
            original.apply(form, arguments);
        }        
        }
    );
    }
);
