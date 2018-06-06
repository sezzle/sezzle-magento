// Using Afterpay's idea of overriding payment flow using JS
// Check code here: https://github.com/afterpay/afterpay-magento/blob/master/src/js/Afterpay/checkout/idev_onestep.js

/**
 * Function specifically for Idev OneStepCheckout. The class especially for it
 */
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
                        alert('Sezzlepay Gateway is not available.');
                    }
                }
            );

            // prepare params
            var params = form.serialize(true);

            var customer_password = jQuery('#billing\\:customer_password').val();

            if(typeof customer_password !== 'undefined' && customer_password.length) {
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
                    response = eval('(' + transport.responseText + ')');
                }
                catch (e) {
                    response = {};
                }
                if (response.redirect) {
                    // location.href = response.redirect
                    var modal = (function(){
                        var 
                        method = {},
                        $overlay,
                        $modal,
                        $content,
                        $close;
                      
                        // Append the HTML
                      
                        // Center the modal in the viewport
                        method.center = function () {};
                      
                        // Open the modal
                        method.open = function (settings) {};
                      
                        // Close the modal
                        method.close = function () {};
                      
                        return method;
                    }());
                    $overlay = jQuery('<div id="overlay"></div>');
                    $modal = jQuery('<div id="modal"></div>');
                    $content = jQuery('<div id="content"></div>');
                    $modal.append($content);

                    $overlay.css({
                        "position": "fixed",
                        "top": "0",
                        "left": "0",
                        "width": "100%",
                        "height": "100%",
                        "background": "#000",
                        "opacity": "0.5",
                        "filter": "alpha(opacity=50)",
                    });

                    $modal.css({
                        "position": "absolute",
                        "background": "url(tint20.png) 0 0 repeat",
                        "background": "rgba(0,0,0,0.2)",
                        "border-radius": "14px",
                        "padding": "8px",
                    });

                    $content.css({
                        "border-radius": "8px",
                        "background": "#fff"
                    });

                    jQuery('body').append($overlay, $modal);

                    modal.center = function () {
                        var top, left;
                      
                        top = Math.max(jQuery(window).height() - $modal.outerHeight(), 0) / 2;
                        left = Math.max(jQuery(window).width() - $modal.outerWidth(), 0) / 2;
                      
                        $modal.css({
                          top:top + jQuery(window).scrollTop(), 
                          left:left + jQuery(window).scrollLeft()
                        });
                    };

                    modal.center = function () {
                        var top, left;
                      
                        top = Math.max(jQuery(window).height() - $modal.outerHeight(), 0) / 2;
                        left = Math.max(jQuery(window).width() - $modal.outerWidth(), 0) / 2;
                      
                        $modal.css({
                          top:top + jQuery(window).scrollTop(), 
                          left:left + jQuery(window).scrollLeft()
                        });
                    };

                    modal.open = function (settings) {
                        $content.empty().append(settings.content);
                        $modal.css({
                          width: settings.width || 'auto', 
                          height: settings.height || 'auto'
                        })
                      
                        modal.center();
                      
                        jQuery(window).bind('resize.modal', modal.center);
                      
                        $modal.show();
                        $overlay.show();
                    };

                    modal.open({content: "<iframe height='610px' src=" + response.redirect + "></iframe>"});
                }

            }.bind(this),
            onFailure: function () {
                alert('Sezzlepay Gateway is not available.');
            }
        }
    );
}