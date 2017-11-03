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
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_isInitializeNeeded      = true;
    protected $_formBlockType           = 'sezzle_pay/form_paylater';

    /**
     * Constants
     */
    const API_PUBLIC_KEY_CONFIG_PATH = 'payment/pay/public_key';
    const API_PRIVATE_KEY_CONFIG_PATH = 'payment/pay/private_key';
    const API_MODE_CONFIG_FIELD = 'api_mode';
    const API_BASE_URL_CONFIG_FIELD = 'base_url';

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
        $merchantPublicKey     = trim(Mage::getStoreConfig(self::API_PUBLIC_KEY_CONFIG_PATH));
        $merchantPrivateKey    = trim(Mage::getStoreConfig(self::API_PRIVATE_KEY_CONFIG_PATH));

        return array(
            "public_key" => $merchantPublicKey,
            "private_key" => $merchantPrivateKey
        );
    }

    // Get auth token from Sezzle API
    protected function getSezzleAuthToken() {
        $result = $this->_sendApiRequest(
            $this->getApiRouter()->getAuthTokenUrl(),
            $this->getSezzleAuthHeader(),
            false,
            Varien_Http_Client::POST
        );
        if ($result->isError()) {
            throw Mage::exception(
                'Sezzle_Pay',
                __('Sezzle Pay API Error: %s', $result->getMessage())
            );
        }
        $resultJson = $result->getBody();
        $resultObject = json_decode($resultJson, true);
        $token = $resultObject['token'];
        if (empty($token)) {
            throw Mage::exception(
                'Sezzle_Pay',
                "Sezzle Pay API Error: Cannot get auth token. $resultJson"
            );
        }
        return $token;
    }

    // Send quote data and get the redirect URL from Sezzle API
    public function start($quote, $cancelUrl, $completeUrl) {

        $quote->collectTotals();

        if (!$quote->getGrandTotal() && !$quote->hasNominalItems()) {
            Mage::throwException(Mage::helper('paypal')->__('Sezzle does not support processing orders with zero amount. To complete your purchase, proceed to the standard checkout process.'));
        }

        $quote->reserveOrderId()->save();

        // create request body for sezzle checkout init
        $requestBody = $this->createCheckoutRequestBody($quote, $cancelUrl, $completeUrl);
        
        // Send request
        $result = $this->_sendApiRequest(
            $this->getApiRouter()->getSubmitCheckoutDetailsAndGetRedirectUrl(),
            $requestBody,
            true,
            Varien_Http_Client::POST
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
        $requestBody["amount_in_cents"] = $quote->getGrandTotal() * 100;
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
            $productPrice = $item->getProduct()->getPrice() * 100;
            $productSKU = $item->getProduct()->getSku();
            $productQuantity = $item->getQty();
            $itemData = array(
                "name" => $productName,
                "sku" => $productSKU,
                "quantity" => $productQuantity,
                "price" => array(
                    "amount_in_cents" => $productPrice,
                    "currency" => $requestBody["currency_code"]
                )
            );
            array_push($requestBody["items"], $itemData);
        }
        $requestBody["merchant_completes"] = true;

        return $requestBody;
    }

    // Get auth token from Sezzle API
    protected function getApiRouter() {
        return Mage::getModel('sezzle_pay/api_router');
    }

    // send request
    protected function _sendApiRequest($url, $body, $isAuth = true, $method = Varien_Http_Client::GET) {
        $client = new Varien_Http_Client($url);
        if ($body !== false) {
            $client->setRawData(Mage::helper('core')->jsonEncode($body), 'application/json');
        }
        if ($isAuth) {
            // Get the auth token
            $token = $this->getSezzleAuthToken();
            // set auth header
            $client->setHeaders('Authorization', "Bearer $token");
        }
        $response = $client->request($method);
        return $response;
    }

}