<?php
class Sezzle_Pay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Availability options
     */ 
    protected $_logFileName = 'sezzle-pay.log';
    protected $_code = 'pay';
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_isInitializeNeeded      = false;
    protected $_formBlockType           = 'sezzle_pay/form_paylater';

    /**
     * Constants
     */
    const API_PUBLIC_KEY_CONFIG_PATH = 'payment/pay/public_key';
    const API_PRIVATE_KEY_CONFIG_PATH = 'payment/pay/private_key';
    const API_MODE_CONFIG_FIELD = 'api_mode';

    /**
    * @return Mage_Checkout_Model_Session
    */
    protected function _getCheckout()
    {
       return Mage::getSingleton('checkout/session');
    }
    // Construct the redirect URL
    public function getOrderPlaceRedirectUrl()
    {   
        $redirectUrl = Mage::getUrl('pay/payment/redirect');
        Mage::Log("Step 2 Process: Getting the redirect URL: $redirectUrl", Zend_Log::DEBUG, $this->_logFileName);
        return $redirectUrl;
    }

    // Get the auth data to be sent to sezzle API to get a token
    protected function getSezzleAuthHeader() {
        $merchantPublicKey     = trim($this->getConfigData(self::API_PUBLIC_KEY_CONFIG_PATH));
        $merchantPrivateKey    = trim($this->getConfigData(self::API_PRIVATE_KEY_CONFIG_PATH));

        return array(
            "public_key" => $merchantPublicKey,
            "private_key" => $merchantPrivateKey
        );
    }

    // Get auth token from Sezzle API
    protected function getSezzleAuthToken() {
        $response = $this->_sendApiRequest(
            $this->getApiRouter()->getAuthTokenUrl(),
            $this->getSezzleAuthHeader()
        );
        if ($result->isError()) {
            throw Mage::exception(
                'Sezzle_Pay',
                __('Sezzle Pay API Error: %s', $result->getMessage())
            );
        }
        $resultObject = json_decode($result->getBody(), true);
        $token = $resultObject['token'];
        if (empty($orderToken)) {
            throw Mage::exception(
                'Sezzle_Pay',
                'Sezzle Pay API Error: Cannot get auth token.'
            );
        }
        return $token;
    }

    // Send quote data and get the redirect URL from Sezzle API
    protected function start($quote, $cancelUrl, $completeUrl) {

        $quote->collectTotals();

        if (!$this->_quote->getGrandTotal() && !$this->_quote->hasNominalItems()) {
            Mage::throwException(Mage::helper('paypal')->__('PayPal does not support processing orders with zero amount. To complete your purchase, proceed to the standard checkout process.'));
        }

        $this->_quote->reserveOrderId()->save();

        // create request body for sezzle checkout init
        $requestBody = $this->createCheckoutRequestBody($quote, $cancelUrl, $completeUrl);
        
        // Send request
        $response = $this->_sendApiRequest(
            $this->getApiRouter()->getSubmitCheckoutDetailsAndGetRedirectUrl(),
            $this->createCheckoutRequestBody($quote, $cancelUrl, $completeUrl)
        );
        if ($result->isError()) {
            throw Mage::exception(
                'Sezzle_Pay',
                __('Sezzle Pay API Error: %s', $result->getMessage())
            );
        }

        $resultObject = json_decode($result->getBody(), true);
        $checkoutUrl = $resultObject['checkout_url'];
        if (empty($checkoutUrl)) {
            throw Mage::exception(
                'Sezzle_Pay',
                'Sezzle Pay API Error: Cannot get checkout Url'
            );
        }
        return $checkoutUrl;
    }

    // Create checkout data for sezzle API from quote
    protected function createCheckoutRequestBody($quote, $cancelUrl, $completeUrl) {
        $requestBody = array();
        $requestBody["amount_in_cents"] = $quote->getGrandTotal();
        $requestBody["currency_code"] = $quote->getBaseCurrencyCode();
        $requestBody["order_description"] = $quote->getReservedOrderId();
        $requestBody["order_reference_id"] = $quote->getReservedOrderId();
        $requestBody["checkout_cancel_url"] = $cancelUrl;
        $requestBody["checkout_complete_url"] = $completeUrl;
        $requestBody["customer_details"] = array(
            "first_name" => $quote->getCustomerFirstname(),
            "last_name" => $quote->getCustomerLastname(),
            "email" => $quote->getCustomerEmail(),
            "phone" => $quote->getBillingAddress()->getTelephone()
        );
        $requestBody["billing_address"] = array(
            "street" => $quote->getBillingAddress()->getStreet(1),
            "street2" => $quote->getBillingAddress()->getStreet2(),
            "city" => $quote->getBillingAddress()->getCity(),
            "state" => $quote->getBillingAddress()->getRegionCode(),
            "postal_code" => $quote->getBillingAddress()->getPostcode(),
            "country_code" => $quote->getBillingAddress()->getCountry(),
            "phone" => $quote->getBillingAddress()->getTelephone()
        );
        $requestBody["shipping_address"] = array(
            "street" => $quote->getShippingAddress()->getStreet(1),
            "street2" => $quote->getShippingAddress()->getStreet2(),
            "city" => $quote->getShippingAddress()->getCity(),
            "state" => $quote->getShippingAddress()->getRegionCode(),
            "postal_code" => $quote->getShippingAddress()->getPostcode(),
            "country_code" => $quote->getShippingAddress()->getCountry(),
            "phone" => $quote->getShippingAddress()->getTelephone()
        );
        $requestBody["items"] = array();
        foreach ($quote->getAllVisibleItems() as $item) {
            $productName = $item->getProduct()->getName();
            $productPrice = $item->getProduct()->getPrice();
            $productSKU = $item->getProduct()->getSku();
            $productQuantity = $item->getProduct()->getQty();
            $itemData = array(
                "name" => $productName,
                "sku" => $productSKU,
                "quantity" => $productQuantity,
                "price" => $productPrice
            );
            array_push($requestBody["items"], $itemData);
        }
        $requestBody["merchant_completes"] = false;

        return $requestBody;
    }

    // Get auth token from Sezzle API
    protected function getApiRouter() {
        return Mage::getModel('pay/api_router');
    }

    // send request
    protected function _sendApiRequest($url, $body) {
        $client = new Varien_Http_Client($url);
        if ($body !== false) {
            $client->setRawData($coreHelper->jsonEncode($body), 'application/json');
        }
        if (!substr($url, -15) == '/authentication') {
            // Get the auth token
            $token = $this->getSezzleAuthToken();
            // set auth header
            $client->setHeaders('Authorization', "Bearer $token");
        }
        $response = $client->request();
        return $response;
    }

}