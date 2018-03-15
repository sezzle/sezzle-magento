jQuery( document ).ready( function() {
    var form = document.getElementById('onestep_form');
    var action = form.getAttribute('action');

    // save in variable for default .submit
    var original = form.submit;
    
    //hacks the form to prevent override by other plugins
    jQuery(".btn-checkout").on("click", function(e) {
    
	if (payment.currentMethod == 'sezzlepay') {
    
    		e.preventDefault();
            e.stopPropagation();
            
            // send logs
            new Ajax.Request(
                window.Sezzlepay.logUrl,
                {
                    method: 'post',
                    parameters: null,
                    onFailure: function () {
                        alert('Sezzlepay Gateway is not available.');
                    }
                }
            );
	
            // prepare params
            var params = form.serialize(true);

            // Ajax to start order token
            var request = new Ajax.Request(
                window.Sezzlepay.saveUrl,
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
        } else {
            original.apply(form, arguments);
        }		
    });
});
