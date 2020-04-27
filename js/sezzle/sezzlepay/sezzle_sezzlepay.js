(function () {
  Sezzle = {}
  Sezzle.render = function (merchant_id) {
    console.log(merchant_id);
    if (!merchant_id) {
      console.warn('Sezzle: merchant id not set, cannot render widget');
      return;
    }
  
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'https://widget.sezzle.com/v1/javascript/price-widget?uuid=' + merchant_id;
    document.head.appendChild(script);
  }
})();