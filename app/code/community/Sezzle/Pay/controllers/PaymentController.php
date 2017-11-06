<?php
if (!function_exists('boolval')) {
    function boolval($val) 
    {
            return (bool) $val;
    }
}

class Sezzle_Pay_PaymentController extends Mage_Core_Controller_Front_Action
{
    protected $_quote;

    // Entrypoint: Redirect user to Sezzle
    public function startAction() {
        try {
            // Check with security updated on form key
            if (!$this->_validateFormKey()) {
                
                $frontend_form_key  =   Mage::app()->getRequest()->getParam('form_key');
                $session_form_key   =   Mage::getSingleton('core/session')->getFormKey();

                $this->helper()->log('Detected fraud. Front-End Key:' . $frontend_form_key . ' Session Key:' . $session_form_key, Zend_Log::ERR);

                Mage::throwException(Mage::helper('sezzle_pay')->__('Detected fraud.'));
                return;
            }

            $this->_initCheckout();

            if ($this->_getQuote()->getIsMultiShipping()) {
                Mage::throwException(Mage::helper('sezzle_pay')->__('Sezzle payment is not supported for this checkout.'));
            }

            // Check if customer has to be logged in to process to checkout
            $quoteCheckoutMethod = $this->_getQuote()->getCheckoutMethod();
            if ($quoteCheckoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST &&
                !Mage::helper('checkout')->isAllowedGuestCheckout(
                $this->_getQuote(),
                $this->_getQuote()->getStoreId()
            )) {
                Mage::getSingleton('core/session')->addNotice(
                    Mage::helper('sezzle_pay')->__('To proceed to Checkout, please log in using your email address.')
                );
                $this->redirectLogin();
                Mage::getSingleton('customer/session')
                ->setBeforeAuthUrl(Mage::getUrl('*/*/*', array('_current' => true)));
                return;
            }

            $redirectUrl = Mage::getModel('sezzle_pay/paymentmethod')->start($this->_quote);
            $response = array(
                'success' => true,
                'redirect'  => $redirectUrl,
            );
        } catch (Exception $e) {
            // Debug log
            if (empty($this->_quote)) {
                $this->helper()->log($this->__('Error occur during process, Quote not found. %s.', $e->getMessage(), Zend_Log::ERR));
            }
            else {
                $this->helper()->log($this->__('Error occur during process. %s. QuoteID=%s', $e->getMessage(), $this->_quote->getId()), Zend_Log::ERR);
            }
            
            // Adding error for redirect and JSON
            $message = Mage::helper('sezzle_pay')->__('There was an error processing your order. %s', $e->getMessage());

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

    // User returned to store without paying
    public function cancelAction() {
        $this->_redirect('checkout/cart');
    }

    public function completeAction() {
        $this->_capture();
    }

    protected function _capture() {
        try {
            $orderId = $this->getRequest()->getParam('id');

            $this->_initCheckout();
            $this->_quote->collectTotals();

            $payment = $this->_quote->getPayment();
            // Debug log
            $this->helper()->log($this->__('Payment succeeded with Sezzlepay. QuoteID=%s ReservedOrderID=%s',$this->_quote->getId(), $this->_quote->getReservedOrderId()), Zend_Log::NOTICE);
            
            // Place order when validation is correct
            $this->_forward('placeOrder');
        } catch (Exception $e) {
            // Add error message
            $this->_getSession()->addError($e->getMessage());

            $this->_getSession()->log($this->__('Exception during order creation. %s', $e->getMessage()), Zend_Log::ERR);
        }
    }

    public function placeOrderAction() {
        try {
            // Load the checkout session
            $this->_initCheckout();

            $checkout_method = $this->_quote->getCheckoutMethod();
            if( $checkout_method == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER ) {
                $this->_prepareNewSezzleCustomerQuote();
            }
            else if( $checkout_method == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST ) {
                $this->_prepareSezzleGuestQuote();
            }
            else {
                $this->_prepareSezzleCustomerQuote();
            }

            $placeOrder = Mage::getModel('sezzle_pay/paymentmethod')->place($this->_quote);
            $this->_redirect('checkout/onepage/success');
        } catch (Exception $e) {
            // Debug log
            $this->getSession()->addError($e->getMessage());

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
            // $quote->loginById($customerId);

            $session = Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
            return $this->_prepareSezzleCustomerQuote();
        }

        $customer = $quote->getCustomer();
        /** @var $customer Mage_Customer_Model_Customer */
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } elseif ($shipping) {
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
        return Mage::helper('sezzle_pay');
    }

    // Init checkout model
    protected function _initCheckout()
    {
        $quote = $this->_getQuote();
        
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Forbidden');

            if (!$quote->hasItems()) {
                $message = 'No items in quote';
                $this->helper()->log(
                    $message,
                    Zend_Log::DEBUG
                );
            }
            else if($quote->getHasError()) {
                $message = 'Quote Error Received: ' . $quote->getMessage();
                $this->helper()->log(
                    $message,
                    Zend_Log::DEBUG
                );
            }

            Mage::throwException(Mage::helper('sezzle_pay')->__('Unable to initialize Sezzle Pay: ' . $message));
        }
    }

    private function _getSession()
    {
        return Mage::getSingleton('core/session');
    }

    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    private function _getQuote()
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
} 