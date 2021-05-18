(function () {
  Sezzle = {}
  Sezzle.render = function (merchant_id) {
    if (!merchant_id) {
      console.warn('Sezzle: merchant id not set, cannot render widget');
      return;
    }
    document.sezzleConfig = {
      "configGroups": [{
        "targetXPath": ".price-info/.price-box/.special-price/.price",
        'relatedElementActions': [
          {
          'relatedPath': '../..',
          'initialAction': function(r,w){
          if(getComputedStyle(r).display === 'none'){
          w.style.display = 'none'
          }
          }
          }
          ]} ,{
           "targetXPath": ".price-info/.price-box/.regular-price/.price",
          "renderToPath": "..",
          'relatedElementActions': [
  {
  'relatedPath': '.',
  'initialAction': function(r,w){
  if(r.querySelector('.special-price')){
  w.style.display = 'none'
  }
  }
  }
  ]
  },
        {
          "targetXPath": ".a-right/STRONG-0/.price",
          "renderToPath": "../../../../..",
        }
      ]
    }
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'https://widget.sezzle.com/v1/javascript/price-widget?uuid=' + merchant_id;
    document.head.appendChild(script);
  }
})();
