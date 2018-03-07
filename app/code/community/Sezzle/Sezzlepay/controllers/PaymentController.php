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
                    'Detected fraud. Front-End Key:' . $frontendFormKey . ' Session Key:' . $sessionFormKey,
                    Zend_Log::ERR
                );

                Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Detected fraud.'));
                return;
            }

            $this->_initCheckout();

            if ($this->_getQuote()->getIsMultiShipping()) {
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
                $this->_quote = $this->helper()->storeCreditSessionSet($this->_quote);
                $this->_quote = $this->helper()->giftCardsSessionSet($this->_quote);
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
                        'Error occur during process, Quote not found. %s.', $e->getMessage(),
                        Zend_Log::ERR
                    )
                );
            } else {
                $this->helper()->log(
                    $this->__(
                        'Error occur during process. %s. QuoteID=%s', $e->getMessage(), $this->_quote->getId()
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
        $logged_in = Mage::getSingleton('customer/session')->isLoggedIn();
        $create_account = $request->getParam("create_account");
	
	    if( !is_null($quote->getCheckoutMethod()) && (empty($create_account))) {
            return;
        }

        try {

            if( $create_account ) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            }
            else if( !$create_account && !$logged_in ) {
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

    public function completeAction() 
    {
        $this->_sezzleCapture();
    }

    protected function _sezzleCapture() 
    {
        $message = Mage::helper('sezzle_sezzlepay')->__('Sezzle Capture start...');

        try {
            $orderId = $this->getRequest()->getParam('id');

            $this->_initCheckout();
            $this->_quote->collectTotals();

            if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                $this->_quote = $this->helper()->storeCreditCapture($this->_quote);
                $this->_quote = $this->helper()->giftCardsCapture($this->_quote); 
                $this->_quote->save();
            }

            $payment = $this->_quote->getPayment();
            // Debug log
            $this->helper()->log(
                $this->__(
                    'Payment succeeded with Sezzlepay. QuoteID=%s ReservedOrderID=%s', $this->_quote->getId(),
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
                    'Exception during order creation. %s', $e->getMessage()
                ), 
                Zend_Log::ERR
            );
        }
    }

    public function placeOrderAction() 
    {
        try {
            $reference = $this->getRequest()->getParam('magento_sezzle_id');
            $this->helper()->log("reference: $reference", Zend_Log::DEBUG);
            // Load the checkout session
            $this->_initCheckout();

            $checkoutMethod = $this->_quote->getCheckoutMethod();
            if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
                $this->_prepareNewSezzleCustomerQuote();
            } else if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST) {
                $this->_prepareSezzleGuestQuote();
            } else {
                $this->_prepareSezzleCustomerQuote();
            }

            $placeOrder = Mage::getModel('sezzle_sezzlepay/PaymentMethod')->place($this->_quote, $reference);
            
            if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                $this->helper()->storeCreditPlaceOrder();
                $this->helper()->giftCardsPlaceOrder();
            }

            $this->_redirect('checkout/onepage/success');
        } catch (Exception $e) {
            // Debug log
            $this->_getSession()->addError($e->getMessage());

            $this->_redirect('checkout/cart');
        }
    }

    protected function _prepareSezzleGuestQuote()
    {
        $quote = $this->_quote;
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    }

    protected function _prepareSezzleCustomerQuote()
    {
        $quote      = $this->_quote;
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

        $quote->setCustomer($customer)->setCustomerIsGuest(false);
    }

    protected function _prepareNewSezzleCustomerQuote()
    {
        $quote      = $this->_quote;
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        $customer = $this->_lookupCustomer();

        if ($customer->getData()) {
            $session = Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
            return $this->_prepareSezzleCustomerQuote();
        }

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
        $quote = $this->_getQuote();
        
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Forbidden');
            
            if (!$quote->hasItems()) {
                $message = 'No items in quote';
                $this->helper()->log(
                    $message,
                    Zend_Log::DEBUG
                );
            } else if ($quote->getHasError()) {
                $message = 'Quote Error Received: ' . $quote->getMessage();
                $this->helper()->log(
                    $message,
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
} 