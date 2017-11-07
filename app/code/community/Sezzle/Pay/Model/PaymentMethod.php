<?php
class Sezzle_Pay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Availability options
     */ 
    protected $_logFileName = 'sezzle-pay.log';
    protected $_code = 'pay';
    protected $_isGateway                  = true;
    protected $_canOrder                   = true;
    protected $_canAuthorize               = true;
    protected $_canCapture                 = true;
    protected $_canCapturePartial          = false;
    protected $_canCaptureOnce             = false;
    protected $_canRefund                  = true;
    protected $_canRefundInvoicePartial    = true;
    protected $_canVoid                    = false;
    protected $_canUseInternal             = false;
    protected $_canUseCheckout             = true;
    protected $_canUseForMultishipping     = false;
    protected $_isInitializeNeeded         = false;
    protected $_canFetchTransactionInfo    = true;
    protected $_canReviewPayment           = true;
    protected $_canCreateBillingAgreement  = false;
    protected $_canManageRecurringProfiles = false;
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
    protected function getSezzleAuthHeader() 
    {
        $merchantPublicKey     = trim(Mage::getStoreConfig(self::API_PUBLIC_KEY_CONFIG_PATH));
        $merchantPrivateKey    = trim(Mage::getStoreConfig(self::API_PRIVATE_KEY_CONFIG_PATH));

        return array(
            "public_key" => $merchantPublicKey,
            "private_key" => $merchantPrivateKey
        );
    }

    // Get auth token from Sezzle API
    protected function getSezzleAuthToken() 
    {
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
                "Sezzle Pay API Error: Cannot get auth token."
            );
        }

        return $token;
    }

    // Send quote data and get the redirect URL from Sezzle API
    public function start($quote) 
    {

        $quote->collectTotals();

        if (!$quote->getGrandTotal()
            && !$quote->hasNominalItems()
        ) {
            Mage::throwException(
                Mage::helper('Sezzle_Pay')->__(
                    'Sezzle does not support
                    processing orders with zero amount.
                    To complete your purchase,
                    proceed to the standard checkout process.'
                )
            );
        }

        $quote->reserveOrderId()->save();
        $reference = $this->createUniqueReferenceId($quote->getReservedOrderId());

        $cancelUrl = Mage::getUrl('*/*/cancel');
        $completeUrl = Mage::getUrl('*/*/complete')
            . "id/"
            . $quote->getReservedOrderId()
            . '/'
            . 'magento_sezzle_id/'
            . $reference;

        // create request body for sezzle checkout init
        $requestBody = $this->createCheckoutRequestBody($quote, $reference, $cancelUrl, $completeUrl);
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

    // Place order using quote
    public function place($quote, $reference) 
    {
        // Converting quote to order
        $service = Mage::getModel('sales/service_quote', $quote);
        
        $service->submitAll();
        $order = $service->getOrder();
    
        // ensure that Grand Total is not doubled
        $order->setBaseGrandTotal($quote->getBaseGrandTotal());
        $order->setGrandTotal($quote->getGrandTotal());


        // adjust the Quote currency to prevent the default currency being stuck
        $order->setBaseCurrencyCode(Mage::app()->getStore()->getCurrentCurrencyCode());
        $order->setQuoteCurrencyCode(Mage::app()->getStore()->getCurrentCurrencyCode());
        $order->setOrderCurrencyCode(Mage::app()->getStore()->getCurrentCurrencyCode());
        $order->save();

        $session = $this->_getSession();

        if ($order->getId()) {
            // Check with recurring payment
            $profiles = $service->getRecurringPaymentProfiles();
            if ($profiles) {
                $ids = array();
                foreach ($profiles as $profile) {
                    $ids[] = $profile->getId();
                }

                $session->setLastRecurringProfileIds($ids);
            }

            //ensure the order amount due is 0
            $order->setTotalDue(0);
            $order->save();
                        
            if (!$order->getEmailSent()) {
                $order->sendNewOrderEmail();
            }

            // prepare session to success or cancellation page clear current session
            $session->clearHelperData();

            // "last successful quote" for correctly redirect to success page
            $quoteId = $session->getQuote()->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            // an order may be created
            $session->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());

            //clear the checkout session
            $session->getQuote()->setIsActive(0)->save();

            $referenceArr = explode('-', $reference);
            $transactionId = $referenceArr[0];
            $orderId = $referenceArr[1];

            // send the id
            $result = $this->_sendApiRequest(
                $this->getApiRouter()->getOrderIdUrl($reference),
                array(
                    "order_id" => $orderId
                ),
                true,
                Varien_Http_Client::POST
            );
            if ($result->isError()) {
                throw Mage::exception(
                    'Sezzle_Pay',
                    __('Sezzle Pay API Error: %s', $result->getMessage())
                );
            }

            return true;
        }

        return false; 
    }

    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    // Create unique reference ID
    protected function createUniqueReferenceId($referenceId) 
    {
        return uniqid() . "-" . $referenceId;
    }

    // Create checkout data for sezzle API from quote
    protected function createCheckoutRequestBody($quote, $reference, $cancelUrl, $completeUrl) 
    {
        $requestBody = array();
        $requestBody["amount_in_cents"] = $quote->getGrandTotal() * 100;
        $requestBody["currency_code"] = $quote->getBaseCurrencyCode();
        $requestBody["order_description"] = $reference;
        $requestBody["order_reference_id"] = $reference;
        $requestBody["checkout_cancel_url"] = Mage::getModel('core/url')->sessionUrlVar($cancelUrl);
        $requestBody["checkout_complete_url"] = Mage::getModel('core/url')->sessionUrlVar($completeUrl);
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

        $requestBody["merchant_completes"] = false;

        return $requestBody;
    }

    // Get auth token from Sezzle API
    protected function getApiRouter() 
    {
        return Mage::getModel('sezzle_pay/api_router');
    }

    // send request
    protected function _sendApiRequest($url, $body, $isAuth = true, $method = Varien_Http_Client::GET) 
    {
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