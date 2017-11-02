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
        $response = $client->request();
        return $response;
    }

}