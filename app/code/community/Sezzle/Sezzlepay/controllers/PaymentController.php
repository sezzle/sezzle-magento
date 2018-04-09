<?php
if (!function_exists('boolval')) {
    function boolval($val) 
    {
            return (bool) $val;
    }
}

class Sezzle_Sezzlepay_PaymentController extends Mage_Core_Controller_Front_Action
{
    protected $_quote;

    // Entrypoint: Redirect user to Sezzle
    public function startAction() 
    {
        $this->helper()->log(
            'Session : ' . $this->getSessionID() . ' Starting sezzle payment',
            Zend_Log::DEBUG
        );
        try {
            $params = Mage::app()->getRequest()->getParams();
            if ($params) {
                $this->_saveCart($params);
            }

            // Check with security updated on form key
            if (!$this->_validateFormKey()) {
                $frontendFormKey  =   Mage::app()->getRequest()->getParam('form_key');
                $sessionFormKey   =   Mage::getSingleton('core/session')->getFormKey();

                $this->helper()->log(
                    'Session : ' . $this->getSessionID() . ' Detected fraud. Front-End Key:' . $frontendFormKey . ' Session Key:' . $sessionFormKey,
                    Zend_Log::ERR
                );

                Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Detected fraud.'));
                return;
            }

            $this->_initCheckout();

            if ($this->_getQuote()->getIsMultiShipping()) {
                $this->helper()->log(
                    'Session : ' . $this->getSessionID() . ' Sezzle payment is not supported for this checkout',
                    Zend_Log::DEBUG
                );
                Mage::throwException(
                    Mage::helper('sezzle_sezzlepay')->__('Sezzle payment is not supported for this checkout.')
                );
            }

            $this->userProcessing($this->_quote, $this->getRequest());

            // Check if customer has to be logged in to process to checkout
            $quoteCheckoutMethod = $this->_getQuote()->getCheckoutMethod();
            if ($quoteCheckoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST &&
                !Mage::helper('checkout')->isAllowedGuestCheckout(
                    $this->_getQuote(),
                    $this->_getQuote()->getStoreId()
                )) {
                $this->helper()->log(
                    'Session : ' . $this->getSessionID() . ' Guest checkout not allowed in this website. Redirecting to login.',
                    Zend_Log::DEBUG
                );
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('sezzle_sezzlepay')->__('To proceed to Checkout, please log in using your email address.')
                );
                $this->redirectLogin();
                Mage::getSingleton('customer/session')
                ->setBeforeAuthUrl(Mage::getUrl('*/*/*', array('_current' => true)));
                return;
            }

            // Utilise Magento Session to preserve Store Credit details
            if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                $this->helper()->log(
                    'Session : ' . $this->getSessionID() . ' Store is enterprise edition.',
                    Zend_Log::DEBUG
                );
                $this->_quote = $this->helper()->storeCreditSessionSet($this->_quote);
                $this->_quote = $this->helper()->giftCardsSessionSet($this->_quote);
                $this->helper()->log(
                    'Session : ' . $this->getSessionID() . ' Set credit and card session.',
                    Zend_Log::DEBUG
                );
            }

            $redirectUrl = Mage::getModel('sezzle_sezzlepay/PaymentMethod')->start($this->_quote);
            $response = array(
                'success' => true,
                'redirect'  => $redirectUrl,
            );
        } catch (Exception $e) {
            // Debug log
            if (empty($this->_quote)) {
                $this->helper()->log(
                    $this->__(
                        'Session : ' . $this->getSessionID() . ' Error occur during process, Quote not found. %s.', $e->getMessage(),
                        Zend_Log::ERR
                    )
                );
            } else {
                $this->helper()->log(
                    $this->__(
                        'Session : ' . $this->getSessionID() . ' Error occur during process. %s. QuoteID=%s', $e->getMessage(), $this->_quote->getId()
                    ), Zend_Log::ERR
                );
            }

            // Adding error for redirect and JSON
            $message = Mage::helper('sezzle_sezzlepay')->__('There was an error processing your order. %s', $e->getMessage());

            $this->_getCheckoutSession()->addError($message);

            // Response to the
            $response = array(
                'success'  => false,
                'message'  => $message,
                'redirect' => Mage::getUrl('checkout/cart'),
            );
        }

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(json_encode($response));
    }

    public function userProcessing($quote, $request)
    {
        $this->helper()->log(
            'Session : ' . $this->getSessionID() . ' userProcessing called',
            Zend_Log::DEBUG
        );
        $logged_in = Mage::getSingleton('customer/session')->isLoggedIn();
        $create_account = $request->getParam("create_account");
    
        if(!is_null($quote->getCheckoutMethod()) && (empty($create_account))) {
            return;
        }

        try {
            if($create_account) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            }
            else if(!$create_account && !$logged_in) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
            }
            else {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER);
            }

            $quote->save();
        }
        catch (Exception $e) {
            // Add error message
            $this->_getSession()->addError($e->getMessage());
        }
    }

    // User returned to store without paying
    public function cancelAction() 
    {
        if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
            $this->helper()->storeCreditSessionUnset();
            $this->helper()->giftCardsSessionUnset();
        }

        $this->_redirect('checkout/cart');
    }

    public function logAction()
    {
        try {
            $sendAllLogs = $this->getRequest()->getParam('all-logs');
            $marker = "======== Sezzle ========";
            // read file from end and get the last log upload time
            $this->helper()->log("logAction called with param sendAllLogs=$sendAllLogs", Zend_Log::DEBUG);
            if (!file_exists('var/log/sezzle-pay.log')) {
                throw new Exception('File not found.');
            }

            $fp = fopen('var/log/sezzle-pay.log', 'r');
            if (!$fp) {
                throw new Exception('File open failed.');
            }
  
            $currentLine = '';
            $line_store = '';
            for($x_pos = 0; fseek($fp, $x_pos, SEEK_END) !== -1; $x_pos--) {
                $char = fgetc($fp);
                if ($char === PHP_EOL) {
                    $line_store .= $char . $currentLine;
                    $currentLine = '';
                } else {
                    $currentLine = $char . $currentLine;
                }

                // Look for our marker
                if (strrpos($currentLine, $marker) !== false && (int)$sendAllLogs === 0) {
                    $line_store .= $currentLine . PHP_EOL;
                    break;
                }
            }

            fclose($fp);
            if ((int)$sendAllLogs === 0 && ($currentLine === '' || (strrpos($currentLine, $marker) === false))) {
                // does not find the marker. Upload everything
                $this->helper()->log($marker . date('Y-m-d H:i:s', time()));
                return;
            } else if ((int)$sendAllLogs === 0) {
                // check if we want to send or not
                $pos = strrpos($currentLine, $marker) + strlen($marker);
                $time_string = substr($currentLine, $pos + 1);
                $time = strtotime($time_string);
                $now = time();
                $diff = $now - $time;
                // Get the time difference between last upload and now.
                // If it is more than an hour, send the log to sezzle
                if ($diff < 60 * 60) {
                    return;
                }
            }

            if ((int)$sendAllLogs === 1) {
                $time = time();
            }

            $this->helper()->log($marker . ' ' .  date('Y-m-d H:i:s', time()));
            $merchant_id = Mage::getStoreConfig('sezzle_sezzlepay/product_widget/merchant_id');
            $url = $this->getApiRouter()->getSendLogsUrl($merchant_id);
            $body = array(
                'start_time' => date('Y-m-d H:i:s', $time),
                'end_time' => date('Y-m-d H:i:s', time()),
                'log' => $line_store
            );

            $result = $this->getSezzleBaseModel()->_sendApiRequest(
                $url,
                $body,
                true,
                Varien_Http_Client::POST
            );
            if ($result->isError()) {
                $this->helper()->log("Could not send log to Sezzle");
            }
        } catch (Exception $e) {
            $this->helper()->log("Logging failed");
            if($fp) {
                fclose($fp);
            }
        }
    }

    private function getSezzleBaseModel() 
    {
        return Mage::getModel('sezzle_sezzlepay/PaymentMethod');
    }

    protected function getApiRouter() 
    {
        return Mage::getModel('sezzle_sezzlepay/api_router');
    }

    public function completeAction() 
    {
        $this->helper()->log('Session : ' . $this->getSessionID() . " Received action from Sezzle. Starting capture process.", Zend_Log::DEBUG);
        $this->_sezzleCapture();
    }

    protected function _sezzleCapture() 
    {
        $this->helper()->log('Session : ' . $this->getSessionID() . " Entered _sezzleCapture function", Zend_Log::DEBUG);
        $message = Mage::helper('sezzle_sezzlepay')->__('Sezzle Capture start.');

        try {
            $orderId = $this->getRequest()->getParam('id');

            $this->_initCheckout();
            $this->_quote->collectTotals();
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference:' . $this->_quote->getReservedOrderId() . ': Collected totals', Zend_Log::DEBUG);
            if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                $this->_quote = $this->helper()->storeCreditCapture($this->_quote);
                $this->_quote = $this->helper()->giftCardsCapture($this->_quote); 
                $this->_quote->save();
            }

            $payment = $this->_quote->getPayment();
            // Debug log
            $this->helper()->log(
                $this->__(
                    'Session : ' . $this->getSessionID() . ' Payment capture started. QuoteID=%s ReservedOrderID=%s', $this->_quote->getId(),
                    $this->_quote->getReservedOrderId()
                ), 
                Zend_Log::NOTICE
            );
            
            // Place order when validation is correct
            $this->_forward('placeOrder');
        } catch (Exception $e) {
            // Add error message
            $this->_getSession()->addError($e->getMessage());

            $this->_getSession()->log(
                $this->__(
                    'Session : ' . $this->getSessionID() . ' Exception during order creation. %s', $e->getMessage()
                ), 
                Zend_Log::ERR
            );
        }
    }

    public function placeOrderAction() 
    {
        try {
            $reference = $this->getRequest()->getParam('magento_sezzle_id');
            $this->helper()->log('Session : ' . $this->getSessionID() . " reference: $reference", Zend_Log::DEBUG);
            // Load the checkout session
            $this->_initCheckout();
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $this->_quote->getReservedOrderId() . ": Getting checkout type", Zend_Log::DEBUG);
            $checkoutMethod = $this->_quote->getCheckoutMethod();
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $this->_quote->getReservedOrderId() . ": Checkout type is : $checkoutMethod", Zend_Log::DEBUG);
            if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
                $this->_prepareNewSezzleCustomerQuote();
            } else if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST) {
                $this->_prepareSezzleGuestQuote();
            } else {
                $this->_prepareSezzleCustomerQuote();
            }

            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $this->_quote->getReservedOrderId() . ': Placing order.', Zend_Log::DEBUG);
            $placeOrder = Mage::getModel('sezzle_sezzlepay/PaymentMethod')->place($this->_quote, $reference);
            if ($placeOrder) {
                $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $this->_quote->getReservedOrderId() . ': Placed order. Redirecting to success.', Zend_Log::DEBUG);
                if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                    $this->helper()->storeCreditPlaceOrder();
                    $this->helper()->giftCardsPlaceOrder();
                }

                $this->_redirect('checkout/onepage/success');
            } else {
                $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $this->_quote->getReservedOrderId() . ': Order failed. Redirecting to checkout.', Zend_Log::DEBUG);
                Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Sezzle checkout failed. Please select an alternative payment method.'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
            }
        } catch (Exception $e) {
            // Debug log
            $this->_getSession()->addError($e->getMessage());

            $this->_redirect('checkout/cart');
        }
    }

    protected function _prepareSezzleGuestQuote()
    {
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Preparing sezzle guest quote.', Zend_Log::DEBUG);
        $quote = $this->_quote;
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    }

    protected function _prepareSezzleCustomerQuote()
    {
        $quote      = $this->_quote;
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Preparing Sezzle customer quote.', Zend_Log::DEBUG);
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        $customer = $quote->getCustomer();
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }

        if ($shipping && ((!$shipping->getCustomerId() && !$shipping->getSameAsBilling())
            || (!$shipping->getSameAsBilling() && $shipping->getSaveInAddressBook()))) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }

        if ($shipping && isset($customerBilling) && !$customer->getDefaultShipping() && $shipping->getSameAsBilling()) {
            $customerBilling->setIsDefaultShipping(true);
        } elseif ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        }

        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Setting customer on quote.', Zend_Log::DEBUG);
        $quote->setCustomer($customer)->setCustomerIsGuest(false);
    }

    protected function _prepareNewSezzleCustomerQuote()
    {
        $quote      = $this->_quote;
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Preparing new customer quote.', Zend_Log::DEBUG);
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        $customer = $this->_lookupCustomer();
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Getting customer.', Zend_Log::DEBUG);
        if ($customer->getData()) {
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Existing customer. Preparing existing customer quote.', Zend_Log::DEBUG);
            $session = Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
            return $this->_prepareSezzleCustomerQuote();
        }

        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Prepare the new customer.', Zend_Log::DEBUG);
        $customer = $quote->getCustomer();
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } else if ($shipping) {
            $customerBilling->setIsDefaultShipping(true);
        }

        if ($quote->getCustomerDob() && !$billing->getCustomerDob()) {
            $billing->setCustomerDob($quote->getCustomerDob());
        }

        if ($quote->getCustomerTaxvat() && !$billing->getCustomerTaxvat()) {
            $billing->setCustomerTaxvat($quote->getCustomerTaxvat());
        }

        if ($quote->getCustomerGender() && !$billing->getCustomerGender()) {
            $billing->setCustomerGender($quote->getCustomerGender());
        }

        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Copying fields from billing customer to customer for quote.', Zend_Log::DEBUG);
        Mage::helper('core')->copyFieldset('checkout_onepage_billing', 'to_customer', $billing, $customer);

        $email = $quote->getCustomerEmail();
        $password = $customer->decryptPassword($quote->getPasswordHash());

        $customer->setEmail($email);
        $customer->setPrefix($quote->getCustomerPrefix());
        $customer->setFirstname($quote->getCustomerFirstname());
        $customer->setMiddlename($quote->getCustomerMiddlename());
        $customer->setLastname($quote->getCustomerLastname());
        $customer->setSuffix($quote->getCustomerSuffix());
        $customer->setPassword($password);
        $customer->setPasswordHash($customer->hashPassword($customer->getPassword()));
        $customer->save();

        //force login
        $session = Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
        $session->login($email, $password);
        $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Logging in new customer and setting new customer to quote.', Zend_Log::DEBUG);
        $quote->setCustomer($customer)->setCustomerIsGuest(false);
    }

    protected function _lookupCustomer()
    {
        return Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($this->_quote->getCustomerEmail());
    }

    protected function helper()
    {
        return Mage::helper('sezzle_sezzlepay');
    }

    // Init checkout model
    protected function _initCheckout()
    {
        $this->helper()->log(
            'Session : ' . $this->getSessionID() . ' _initCheckout called',
            Zend_Log::DEBUG
        );
        $quote = $this->_getQuote();
        
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Forbidden');
            
            if (!$quote->hasItems()) {
                $message = 'No items in quote';
                $this->helper()->log(
                    'Session : ' . $this->getSessionID() . ' ' . $message,
                    Zend_Log::DEBUG
                );
            } else if ($quote->getHasError()) {
                $message = 'Quote Error Received: ' . $quote->getMessage();
                $this->helper()->log(
                    'Session : ' . $this->getSessionID() . ' ' . $message,
                    Zend_Log::DEBUG
                );
            }

            Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Unable to initialize Sezzle Pay: ' . $message));
        }
    }

    protected function _getSession()
    {
        return Mage::getSingleton('core/session');
    }

    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }

    public function redirectLogin()
    {
        $this->setFlag('', 'no-dispatch', true);
        $this->getResponse()->setRedirect(
            Mage::helper('core/url')->addRequestParam(
                Mage::helper('customer')->getLoginUrl(),
                array('context' => 'checkout')
            )
        );
    }

    protected function _saveCart($array)
    {
        $skipShipping = false;
        $request = Mage::app()->getRequest();
        foreach ($array as $type => $data) {
            $result = array();
            switch ($type) {
                case 'billing':
                    $result = Mage::getModel('checkout/type_onepage')->saveBilling($data, $request->getPost('billing_address_id', false));
                    $skipShipping = array_key_exists('use_for_shipping', $data) && $data['use_for_shipping'] ? true : false;
                    break;
                case 'shipping':
                    if (!$skipShipping) {
                        $result = Mage::getModel('checkout/type_onepage')->saveShipping($data, $request->getPost('shipping_address_id', false));
                    }
                    break;
                case 'shipping_method':
                    $result = Mage::getModel('checkout/type_onepage')->saveShippingMethod($data);
                    break;
                case 'payment':
                    $result = Mage::getModel('checkout/type_onepage')->savePayment(array('method' => 'sezzlepay'));
                    break;
            }

            if (array_key_exists('error', $result) && $result['error'] == 1) {
                Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('%s', json_encode($result['message'])));
            }
        }
    }

    public function getSessionID() 
    {
        $session = Mage::getSingleton('core/session');
        $SID = $session->getEncryptedSessionId();
        return $SID;
    }
} 