<?php

/**
 * Sezzlepay payment controller
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Quote instance
     */
    protected $_quote;

    /**
     * Redirect user to Sezzle
     *
     * @throws Mage_Core_Exception
     */
    public function startAction()
    {
        $this->helper()->log(
            'Session : ' . $this->getSessionID() . ' Starting sezzle payment',
            Zend_Log::DEBUG
        );
        try {

            $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();

            if ($requiredAgreements) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                $diff = array_diff($requiredAgreements, $postedAgreements);
                if ($diff) {
                    Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Please agree to all the terms and conditions before placing the order.'));
                    return;
                }
            }

            $params = Mage::app()->getRequest()->getParams();
            if ($params) {
                $this->_saveCart($params);
            }
            // Check with security updated on form key
            if (!$this->_validateFormKey()) {
                $frontendFormKey = Mage::app()->getRequest()->getParam('form_key');
                $sessionFormKey = Mage::getSingleton('core/session')->getFormKey();
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
                $this->_quote = Mage::helper('sezzle_sezzlepay')->setStoreCreditSession($this->_quote);
                $this->_quote = Mage::helper('sezzle_sezzlepay')->setGiftCardsSession($this->_quote);
                $this->helper()->log(
                    'Session : ' . $this->getSessionID() . ' Set credit and card session.',
                    Zend_Log::DEBUG
                );
            }
            $redirectUrl = Mage::getModel('sezzle_sezzlepay/sezzlepay')->start($this->_quote);
            $response = array(
                'success' => true,
                'redirect' => $redirectUrl,
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
            $message = Mage::helper(
                'sezzle_sezzlepay')->
            __('There was an error processing your order.');
            $this->_getCheckoutSession()->addError($message);
            $response = array(
                'success' => false,
                'message' => $message,
                'redirect' => Mage::getUrl('checkout/cart'),
            );
        }
        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode($response));
    }

    /**
     * Get sezzle helper
     *
     * @return Sezzle_Sezzlepay_Helper_Data
     */
    protected function helper()
    {
        return Mage::helper('sezzle_sezzlepay');
    }

    /**
     * Get encrypted session id
     *
     * @return mixed
     */
    public function getSessionId()
    {
        return Mage::getSingleton('core/session')
            ->getEncryptedSessionId();
    }

    /**
     * Save the cart
     *
     * @param $array
     */
    protected function _saveCart($array)
    {
        $request = Mage::app()->getRequest();
        $useBillingForShipping = false;
        $useShippingForBilling = false;
        foreach ($array as $type => $data) {
            switch ($type) {
                case 'billing':
                    if (!$useShippingForBilling) {
                        Mage::getModel('checkout/type_onepage')
                            ->saveBilling(
                                $data,
                                $request->getPost('billing_address_id',
                                    false));
                    }
                    $useBillingForShipping = array_key_exists('use_for_shipping', $data) && $data['use_for_shipping'] ? true : false;
                    if ($useBillingForShipping) {
                        Mage::getModel('checkout/type_onepage')
                            ->saveShipping(
                                $data,
                                $request->getPost('billing_address_id',
                                    false));
                    }
                    break;
                case 'shipping':
                    if (!$useBillingForShipping) {
                        Mage::getModel('checkout/type_onepage')
                            ->saveShipping(
                                $data,
                                $request->getPost('shipping_address_id',
                                    false));
                    }
                    $useShippingForBilling = array_key_exists('use_for_billing', $data) && $data['use_for_billing'] ? true : false;
                    if ($useShippingForBilling) {
                        Mage::getModel('checkout/type_onepage')
                            ->saveBilling(
                                $data,
                                $request->getPost('shipping_address_id',
                                    false));
                    }
                    break;
                case 'shipping_method':
                    Mage::getModel('checkout/type_onepage')
                        ->saveShippingMethod($data);
                    break;
                case 'payment':
                    Mage::getModel('checkout/type_onepage')
                        ->savePayment(array('method' => 'sezzlepay'));
                    break;
            }
        }
    }

    /**
     * Initializes checkout
     *
     * @throws Mage_Core_Exception
     */
    protected function _initCheckout()
    {
        $this->helper()->log(
            'Session : ' . $this->getSessionId() . ' _initCheckout called',
            Zend_Log::DEBUG
        );
        $quote = $this->_getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()
                ->setHeader('HTTP/1.1', '403 Forbidden');

            if (!$quote->hasItems()) {
                $message = 'No items in quote';
                $this->helper()->log(
                    'Session : ' . $this->getSessionId() . ' ' . $message,
                    Zend_Log::DEBUG
                );
            } else if ($quote->getHasError()) {
                $message = 'Quote Error Received: ' . $quote->getMessage();
                $this->helper()->log(
                    'Session : ' . $this->getSessionId() . ' ' . $message,
                    Zend_Log::DEBUG
                );
            }
            Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Unable to initialize Sezzle Pay: ' . $message));
        }
    }

    /**
     * Quote instance
     *
     * @return mixed
     */
    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * User check during checkout
     *
     * @param $quote
     * @param $request
     */
    public function userProcessing($quote, $request)
    {
        $this->helper()->log(
            'Session : ' . $this->getSessionId() . ' userProcessing called',
            Zend_Log::DEBUG
        );
        $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
        $isRegisterRequest = $request->getParam("create_account");

        if (!is_null($quote->getCheckoutMethod())
            && (empty($isRegisterRequest))) {
            return;
        }

        try {
            if ($isRegisterRequest) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
            } else if (!$isRegisterRequest && !$isLoggedIn) {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER);
            }
            $quote->save();
        } catch (Exception $e) {
            $message = Mage::helper(
                'sezzle_sezzlepay')->
            __('There was an error while checking out.');
            // Add error message
            $this->_getSession()->addError($message);
        }
    }

    /**
     * Core session
     *
     * @return Mage_Core_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('core/session');
    }

    /**
     * Redirection
     */
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

    /**
     * Cancel action
     */
    public function cancelAction()
    {
        if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
            Mage::helper('sezzle_sezzlepay')->unsetStoreCreditSession();
            Mage::helper('sezzle_sezzlepay')->unsetGiftCardsSession();
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Sezzle logging action
     */
    public function logAction()
    {
        try {
            $sendAllLogs = $this->getRequest()->getParam('all-logs');
            $marker = "======== Sezzle ========";
            // read file from end and get the last log upload time
            $this->helper()->log(
                "logAction called with param sendAllLogs=$sendAllLogs",
                Zend_Log::DEBUG);
            $fileHandler = new Varien_Io_File();
            if (!$fileHandler->fileExists('var/log/sezzle-pay.log')) {
                throw new Exception('File not found.');
            }
            $path = ['path' => 'var/log/sezzle-pay.log'];
            $fileOpened = $fileHandler->open($path);
            if (!$fileOpened) {
                throw new Exception('File open failed.');
            }

            $currentLine = '';
            $lineStore = '';
            for ($xPos = 0; fseek($fileOpened, $xPos, SEEK_END) !== -1; $xPos--) {
                $char = fgetc($fileOpened);
                if ($char === PHP_EOL) {
                    $lineStore .= $char . $currentLine;
                    $currentLine = '';
                } else {
                    $currentLine = $char . $currentLine;
                }

                // Look for our marker
                if (strrpos($currentLine, $marker) !== false && (int)$sendAllLogs === 0) {
                    $lineStore .= $currentLine . PHP_EOL;
                    break;
                }
            }

            $fileHandler->close();
            if ((int)$sendAllLogs === 0 &&
                ($currentLine === '' || (strrpos($currentLine, $marker) === false))) {
                // does not find the marker. Upload everything
                $this->helper()->log($marker . date('Y-m-d H:i:s', time()));
                return;
            } else if ((int)$sendAllLogs === 0) {
                // check if we want to send or not
                $pos = strrpos($currentLine, $marker) + strlen($marker);
                $timeString = substr($currentLine, $pos + 1);
                $time = strtotime($timeString);
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

            $this->helper()->log($marker . ' ' . date('Y-m-d H:i:s', time()));
            $merchantId = Mage::getStoreConfig(Sezzle_Sezzlepay_Model_Api_Processor::API_MERCHANT_ID_CONFIG_PATH);
            $url = $this->getApiRouter()->getSendLogsUrl($merchantId);
            $body = array(
                'start_time' => date('Y-m-d H:i:s', $time),
                'end_time' => date('Y-m-d H:i:s', time()),
                'log' => $lineStore
            );

            $result = $this->getApiProcessor()->sendApiRequest(
                $url,
                $body,
                true,
                Varien_Http_Client::POST
            );
            if (isset($result['status']) && $result['status'] == Sezzle_Sezzlepay_Model_Api_Processor::BAD_REQUEST) {
                $this->helper()->log("Could not send log to Sezzle");
            }
        } catch (Exception $e) {
            $this->helper()->log("Logging failed");
        }
    }

    /**
     * Api Router
     *
     * @return Sezzle_Sezzlepay_Model_Api_Router
     */
    protected function getApiRouter()
    {
        return Mage::getModel('sezzle_sezzlepay/api_router');
    }

    /**
     * Api Router
     *
     * @return Sezzle_Sezzlepay_Model_Api_Processor
     */
    protected function getApiProcessor()
    {
        return Mage::getModel('sezzle_sezzlepay/api_processor');
    }

    /**
     * Complete action
     */
    public function completeAction()
    {
        $this->helper()->log(
            'Session : ' . $this->getSessionId() . " Received action from Sezzle. Starting capture process.",
            Zend_Log::DEBUG);
        $this->_initOrderPlace();
    }

    /**
     * Sezzle payment capture
     */
    private function _initOrderPlace()
    {
        $this->helper()->log('Session : ' . $this->getSessionId() . " Entered _sezzleCapture function", Zend_Log::DEBUG);
        try {
            $this->_initCheckout();
            $this->_quote->collectTotals();
            $this->helper()->log(
                'Session : ' . $this->getSessionId() . ' reference:' . $this->_quote->getReservedOrderId() . ': Collected totals',
                Zend_Log::DEBUG);
            if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                $this->_quote = Mage::helper('sezzle_sezzlepay')->storeCreditCapture($this->_quote);
                $this->_quote = Mage::helper('sezzle_sezzlepay')->giftCardsCapture($this->_quote);
                $this->_quote->save();
            }
            // Debug log
            $this->helper()->log(
                $this->__(
                    'Session : ' . $this->getSessionId() . ' Payment capture started. QuoteID=%s ReservedOrderID=%s', $this->_quote->getId(),
                    $this->_quote->getReservedOrderId()
                ),
                Zend_Log::NOTICE
            );

            // Place order when validation is correct
            $this->_forward('placeOrder');
        } catch (Exception $e) {
            $message = Mage::helper(
                'sezzle_sezzlepay')->
            __('There was an error while placing the order. Reason might be no items in the cart.');
            // Add error message
            $this->_getSession()->addError($message);
            $this->helper()->log(
                $this->__(
                    'Session : ' . $this->getSessionId() . ' Exception during order creation. %s', $e->getMessage()
                ),
                Zend_Log::ERR
            );
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Place order action
     *
     * @throws Mage_Core_Exception
     */
    public function placeOrderAction()
    {
        try {
            $magentoSezzleId = $this->getRequest()->getParam('magento_sezzle_id');
            $this->helper()->log(
                'Session : ' . $this->getSessionId() . " reference: $magentoSezzleId",
                Zend_Log::DEBUG);
            // Load the checkout session
            $this->_initCheckout();
            $this->helper()->log(
                'Session : ' . $this->getSessionId() . ' reference: ' . $this->_quote->getReservedOrderId() . ": Getting checkout type",
                Zend_Log::DEBUG);
            $checkoutMethod = $this->_quote->getCheckoutMethod();
            $this->helper()->log(
                'Session : ' . $this->getSessionId() . ' reference: ' . $this->_quote->getReservedOrderId() . ": Checkout type is : $checkoutMethod",
                Zend_Log::DEBUG);
            if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
                $this->_prepareNewSezzleCustomerQuote();
            } else if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST) {
                $this->_prepareSezzleGuestQuote();
            } else {
                $this->_prepareSezzleCustomerQuote();
            }

            if ($this->_quote->getPayment()->getAdditionalInformation('sezzle_reference_id') != $magentoSezzleId) {
                Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Sezzle checkout failed. Another session exists for this payment.'));
            }

            $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $this->_quote->getReservedOrderId() . ': Placing order.', Zend_Log::DEBUG);
            $isOrderPlaced = Mage::getModel('sezzle_sezzlepay/sezzlepay')->place($this->_quote, $magentoSezzleId);
            if ($isOrderPlaced) {
                $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $this->_quote->getReservedOrderId() . ': Placed order. Redirecting to success.', Zend_Log::DEBUG);
                if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                    Mage::helper('sezzle_sezzlepay')->storeCreditPlaceOrder();
                    Mage::helper('sezzle_sezzlepay')->giftCardsPlaceOrder();
                }
                $this->_redirect('checkout/onepage/success');
            } else {
                $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $this->_quote->getReservedOrderId() . ': Order failed. Redirecting to checkout.', Zend_Log::DEBUG);
                Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Sezzle checkout failed. Please select an alternative payment method.'));
                $this->_redirect(Mage::helper('checkout/url')->getCheckoutUrl());
            }
        } catch (Exception $e) {
            $message = Mage::helper(
                'sezzle_sezzlepay')->
            __($e->getMessage());
            // Debug log
            $this->helper()->log(
                $this->__(
                    'Order creation failed. %s. SezzleOrderID=%s QuoteID=%s ReservedOrderID=%s Stack Trace=%s',
                    $e->getMessage(),
                    $magentoSezzleId,
                    $this->_quote->getId(),
                    $this->_quote->getReservedOrderId(),
                    $e->getTraceAsString()
                ),
                Zend_Log::ERR
            );
            $this->_getSession()->addError($message);
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Create sezzle quote for new customer
     */
    protected function _prepareNewSezzleCustomerQuote()
    {
        $quote = $this->_quote;
        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Preparing new customer quote.', Zend_Log::DEBUG);
        $billing = $quote->getBillingAddress();
        $shipping = $quote->isVirtual()
            ? $quote->getBillingAddress() :
            $quote->getShippingAddress();

        $customer = $quote->getCustomer();
        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Getting customer.', Zend_Log::DEBUG);
        if ($customer->getId()) {
            $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Existing customer. Preparing existing customer quote.', Zend_Log::DEBUG);
            return $this->_prepareSezzleCustomerQuote();
        }
        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Prepare the new customer.', Zend_Log::DEBUG);
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

        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Copying fields from billing customer to customer for quote.', Zend_Log::DEBUG);
        Mage::helper('core')->copyFieldset(
            'checkout_onepage_billing',
            'to_customer',
            $billing,
            $customer);

        $customer = $this->_createCustomerFromQuotation($quote, $customer);
        $this->_forceCustomerLogin($customer);
        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Logging in new customer and setting new customer to quote.', Zend_Log::DEBUG);
        $quote->setCustomer($customer)->setCustomerIsGuest(false);
    }

    /**
     * Create sezzle quote for customer
     */
    protected function _prepareSezzleCustomerQuote()
    {
        $quote = $this->_quote;
        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Preparing Sezzle customer quote.', Zend_Log::DEBUG);
        $billing = $quote->getBillingAddress();
        $shipping = $quote->isVirtual()
            ? $quote->getBillingAddress() :
            $quote->getShippingAddress();

        $customer = $quote->getCustomer();
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }

        if ($shipping
            && ((!$shipping->getCustomerId()
                    && !$shipping->getSameAsBilling())
                || (!$shipping->getSameAsBilling()
                    && $shipping->getSaveInAddressBook()))) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }

        if ($shipping
            && isset($customerBilling)
            && !$customer->getDefaultShipping()
            && $shipping->getSameAsBilling()) {
            $customerBilling->setIsDefaultShipping(true);
        } elseif ($shipping
            && isset($customerShipping)
            && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        }

        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Setting customer on quote.', Zend_Log::DEBUG);
        $quote->setCustomer($customer)->setCustomerIsGuest(false);
    }

    /**
     * Create customer from quote
     *
     * @param $quote
     * @param $customer
     * @return Mage_Customer_Model_Customer
     */
    protected function _createCustomerFromQuotation($quote, $customer = null)
    {
        if (!$customer) {
            $customer = Mage::getModel('customer/customer');
        }
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
        return $customer;
    }

    /**
     * Force customer to login
     * @param $customer
     */
    private function _forceCustomerLogin($customer)
    {
        $session = Mage::getSingleton('customer/session')
            ->setCustomerAsLoggedIn($customer);
        $session->login(
            $customer->getEmail()
            , $customer->getPassword());
    }

    /**
     * Create sezzle quote for Guest customer
     */
    protected function _prepareSezzleGuestQuote()
    {
        $quote = $this->_quote;
        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Preparing sezzle guest quote.', Zend_Log::DEBUG);
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    }

    /**
     * Sezzle order model
     *
     * @return Sezzle_Sezzlepay_Model_Sezzlepay
     */
    private function getSezzleBaseModel()
    {
        return Mage::getModel('sezzle_sezzlepay/sezzlepay');
    }
}
