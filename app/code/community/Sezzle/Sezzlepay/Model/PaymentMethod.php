<?php
class Sezzle_Sezzlepay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Availability options
     */ 
    protected $_logFileName = 'sezzle-pay.log';
    protected $_code = 'sezzlepay';
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
    protected $_formBlockType           = 'sezzle_sezzlepay/form_paylater';

    /**
     * Constants
     */
    const API_PUBLIC_KEY_CONFIG_PATH = 'payment/sezzlepay/public_key';
    const API_PRIVATE_KEY_CONFIG_PATH = 'payment/sezzlepay/private_key';
    const API_MODE_CONFIG_FIELD = 'api_mode';
    const API_BASE_URL_CONFIG_FIELD = 'base_url';
    const MERCHANT_ID_CONFIG_FIELD = 'merchant_id';

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
        $redirectUrl = Mage::getUrl('sezzlepay/payment/redirect');
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
                'Sezzle_Sezzlepay',
                __('Sezzle Pay API Error: %s', $result->getMessage())
            );
        }

        $resultJson = $result->getBody();
        $resultObject = json_decode($resultJson, true);
        $token = $resultObject['token'];
        if (empty($token)) {
            throw Mage::exception(
                'Sezzle_Sezzlepay',
                "Sezzle Pay API Error: Cannot get auth token."
            );
        }

        return $token;
    }

    // Send quote data and get the redirect URL from Sezzle API
    public function start($quote) 
    {
        $this->helper()->log('Session : ' . $this->getSessionID() . ' Starting sezzle transaction.', Zend_Log::DEBUG);

        $quote->collectTotals();
        $this->helper()->log('Session : ' . $this->getSessionID() . ' Collected totals.', Zend_Log::DEBUG);

        if (!$quote->getGrandTotal()
            && !$quote->hasNominalItems()
        ) {
            $this->helper()->log('Session : ' . $this->getSessionID() . ' User tried to checkout with 0 amount.', Zend_Log::DEBUG);
            Mage::throwException(
                Mage::helper('Sezzle_Sezzlepay')->__(
                    'Sezzle does not support
                    processing orders with zero amount.
                    To complete your purchase,
                    proceed to the standard checkout process.'
                )
            );
        }

        $quote->reserveOrderId()->save();
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Reserved an order ID for this quote.', Zend_Log::DEBUG);
        // use reserved merchant order id as reference id
        $reference = $this->createUniqueReferenceId($quote->getReservedOrderId());
        $quote->getPayment()->setData('sezzle_reference_id', $reference)->save();
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Unique reference ID created.', Zend_Log::DEBUG);
        $cancelUrl = Mage::getUrl('*/*/cancel');
        $completeUrl = Mage::getUrl('*/*/complete')
            . "id/"
            . $quote->getReservedOrderId()
            . '/'
            . 'magento_sezzle_id/'
            . $reference;
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Generated cancel and complete URL for this quote.', Zend_Log::DEBUG);
        // create request body for sezzle checkout init
        $requestBody = $this->createCheckoutRequestBody($quote, $reference, $cancelUrl, $completeUrl);
        $url = $this->getApiRouter()->getSubmitCheckoutDetailsAndGetRedirectUrl();
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Posting to sezzle to get the checkout redirect url: ' .$url, Zend_Log::DEBUG);

        // Send request
        $result = $this->_sendApiRequest(
            $this->getApiRouter()->getSubmitCheckoutDetailsAndGetRedirectUrl(),
            $requestBody,
            true,
            Varien_Http_Client::POST
        );
        if ($result->isError()) {
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Sezzle Pay API Error : Error receiving complete URL from Sezzle.', Zend_Log::DEBUG);
            throw Mage::exception(
                'Sezzle_Sezzlepay',
                __('Sezzle Pay API Error: %s', $result->getMessage())
            );
        }

        $resultObject = json_decode($result->getBody(), true);
        $checkoutUrl = $resultObject['checkout_url'];
        if (empty($checkoutUrl)) {
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Sezzle Pay API Error : Received empty checkout URL from Sezzle.', Zend_Log::DEBUG);
            throw Mage::exception(
                'Sezzle_Sezzlepay',
                'Sezzle Pay API Error: Cannot get checkout Url'
            );
        }

        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Received URL from Sezzle succesfully. URL : ' . $checkoutUrl, Zend_Log::DEBUG);
        return $checkoutUrl;
    }

    protected function helper()
    {
        return Mage::helper('sezzle_sezzlepay');
    }

    public function refund(Varien_Object $payment, $amount) 
    {
        $reference = $payment->getData('sezzle_reference_id');
        $currency = $payment->getOrder()->getOrderCurrencyCode();
        $this->helper()->log('Session : ' . $this->getSessionID() . ' Refunding order reference: ' . $reference . ' amount: ' . $amount, Zend_Log::DEBUG);
        
        if($amount == 0) {
            $this->helper()->log('Session : ' . $this->getSessionID() . " Zero amount refund is detected", Zend_Log::ERR);
            return $this;
        }

        // Refund
        $result = $this->_sendApiRequest(
            $this->getApiRouter()->checkoutRefundUrl($reference),
            array(
                "amount" => array(
                    "amount_in_cents" => $amount * 100,
                    "currency" => $currency
                )
            ),
            true,
            Varien_Http_Client::POST
        );
        if ($result->isError()) {
            throw Mage::exception(
                'Sezzle_Sezzlepay',
                __('Sezzle Pay API Error: %s', $result->getMessage())
            );
        }

        $this->helper()->log('Session : ' . $this->getSessionID() . ' Refund with sezzle successful' . $amount, Zend_Log::DEBUG);
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $reference = $payment->getData('sezzle_reference_id');
        $payment->setTransactionId($reference)->setIsTransactionClosed(false);
        return $this;
    }

    public function sezzleCapture(Varien_Object $payment)
    {
        $reference = $payment->getData('sezzle_reference_id');
        // Charge
        $result = $this->_sendApiRequest(
            $this->getApiRouter()->checkoutCompleteUrl($reference),
            null,
            true,
            Varien_Http_Client::POST
        );
        if ($result->isError()) {
            throw Mage::exception(
                'Sezzle_Sezzlepay',
                __('Sezzle Pay API Error: %s', $result->getMessage())
            );
        }

        return $this;
    }

    // Place order using quote
    public function place($quote, $reference) 
    {
        // Converting quote to order
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $order = $service->getOrder();
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Submitted and created order.', Zend_Log::DEBUG);

        // ensure that Grand Total is not doubled
        $order->setBaseGrandTotal($quote->getBaseGrandTotal());
        $order->setGrandTotal($quote->getGrandTotal());

        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Set grand total to order.', Zend_Log::DEBUG);

        // add Sezzle reference id for doing refunds
        $order->setExternalReferenceId($reference);
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Added sezzle reference to order.', Zend_Log::DEBUG);
        $order->save();
        $session = $this->_getSession();

        if ($order->getId()) {
            // Check with recurring payment
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Checking recurring payment profiles.', Zend_Log::DEBUG);
            $profiles = $service->getRecurringPaymentProfiles();
            if ($profiles) {
                $ids = array();
                foreach ($profiles as $profile) {
                    $ids[] = $profile->getId();
                }

                $session->setLastRecurringProfileIds($ids);
            }

            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Ensuring amount due is 0.', Zend_Log::DEBUG);
            //ensure the order amount due is 0
            $order->setTotalDue(0);
            $order->save();
            
            // prepare session to success or cancellation page clear current session
            $session->clearHelperData();

            // "last successful quote" for correctly redirect to success page
            $quoteId = $session->getQuote()->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Creating order in session.', Zend_Log::DEBUG);
            // an order may be created
            $session->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());

            // $order->getPayment()->capture(null);
            try {
                $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Capturing payment in Sezzle.', Zend_Log::DEBUG);
                $this->sezzleCapture($order->getPayment());
                $order->getPayment()->setIsTransactionClosed(true);
                $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Captured payment in Sezzle.', Zend_Log::DEBUG);
                if (!$order->getEmailSent()) {
                    $order->sendNewOrderEmail();
                }

                // clear the cart only if capture successful
                $session->getQuote()->setIsActive(0)->save();
                $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Cleared cart.', Zend_Log::DEBUG);
                return true;
            } catch (Sezzle_Sezzlepay_Exception $e) {
                $this->_cancelOrder($order);
                return false;
            }
        }

        return false; 
    }

    protected function _cancelOrder($order) 
    {
        $this->helper()->log('Session : ' . $this->getSessionID() . ' Cancelling order.', Zend_Log::DEBUG);
        $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Cancelling sezzle payment.');
        $order->save();
        $this->helper()->log('Session : ' . $this->getSessionID() . ' Cancelled order.', Zend_Log::DEBUG);
    }

    // rollback order creation
    protected function _rollbackOrderCreation($order) 
    {
        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice){
            //delete all invoice items
            $items = $invoice->getAllItems(); 
            foreach ($items as $item) {
                $item->delete();
            }

            //delete invoice
            $invoice->delete();
        }

        $creditnotes = $order->getCreditmemosCollection();
        foreach ($creditnotes as $creditnote){
            //delete all creditnote items
            $items = $creditnote->getAllItems(); 
            foreach ($items as $item) {
                $item->delete();
            }

            //delete credit note
            $creditnote->delete();
        }

        $shipments = $order->getShipmentsCollection();
        foreach ($shipments as $shipment){
            //delete all shipment items
            $items = $shipment->getAllItems(); 
            foreach ($items as $item) {
                $item->delete();
            }

            //delete shipment
            $shipment->delete();
        }

        //delete all order items
        $items = $order->getAllItems(); 
        foreach ($items as $item) {
            $item->delete();
        }

        //delete payment
        $order->getPayment()->delete();

        //delete order
        $order->delete();
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
        $precision = 2; //precision to which the price needs to be rounded off
        $requestBody = array();
        $requestBody["amount_in_cents"] = round($quote->getGrandTotal(), $precision) * 100;
        $requestBody["currency_code"] = Mage::app()->getStore()->getCurrentCurrencyCode();
        $requestBody["order_description"] = $reference;
        $requestBody["order_reference_id"] = $reference;
        $requestBody["display_order_reference_id"] = $quote->getReservedOrderId();
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
            $productPrice = $item->getProduct()->getPrice();
            $productSKU = $item->getProduct()->getSku();
            $productQuantity = $item->getQty();
            $itemData = array(
                "name" => $productName,
                "sku" => $productSKU,
                "quantity" => $productQuantity,
                "price" => array(
                    "amount_in_cents" => round($productPrice, $precision) * 100,
                    "currency" => $requestBody["currency_code"]
                )
            );
            array_push($requestBody["items"], $itemData);
        }

        $requestBody["merchant_completes"] = true;

        return $requestBody;
    }

    // Get auth token from Sezzle API
    protected function getApiRouter() 
    {
        return Mage::getModel('sezzle_sezzlepay/api_router');
    }

    // send request
    public function _sendApiRequest($url, $body, $isAuth = true, $method = Varien_Http_Client::GET) 
    {
        $this->helper()->log('Session : ' . $this->getSessionID() . " Sending Request $url");
        $client = new Varien_Http_Client($url);
        $client->setConfig(array(
            'timeout'   => 80
        ));

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

    public function getSessionID() 
    {
        $session = Mage::getSingleton('core/session');
        $SID = $session->getEncryptedSessionId();
        return $SID;
    }

}