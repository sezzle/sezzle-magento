(function () {
  Sezzle = {}
  Sezzle.render = function (merchant_id) {
    if (!merchant_id) {
      console.warn('Sezzle: merchant id not set, cannot render widget');
      return;
    }

    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'https://widget.sezzle.com/v1/javascript/price-widget?uuid=' + merchant_id;
    document.head.appendChild(script);
    document.sezzleConfig = {
      "configGroups": [
              {
                      "targetXPath": ".product-info-main/.price-wrapper/.price",
                      "renderToPath": "../../../..",
                      "relatedElementActions": [
                              {
                                      "relatedPath": ".",
                                      "initialAction": function(r,w){
                                              if(getComputedStyle(r).textDecoration.indexOf("line-through") > -1){
                                                      w.style.display = "none"
                                              }
                                      }
                              }
                      ]
              },
              {
                      "targetXPath": ".amount/STRONG-0/.price",
                      "renderToPath": "../../../../..",
                      "urlMatch": "cart"
              }
      ]
}
  }
})();
