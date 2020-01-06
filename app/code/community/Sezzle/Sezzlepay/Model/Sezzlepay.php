<?php

/**
 * Sezzlepay payment method model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Sezzlepay extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Constants
     */
    const STATE_CAPTURED       = 1;
    const STATE_NOT_CAPTURED   = 0;
    const API_PUBLIC_KEY_CONFIG_PATH = 'payment/sezzlepay/public_key';
    const API_PRIVATE_KEY_CONFIG_PATH = 'payment/sezzlepay/private_key';
    const API_MODE_CONFIG_FIELD = 'api_mode';
    const MERCHANT_ID_CONFIG_FIELD = 'merchant_id';
    const AUTH = 'authorize';
    const AUTH_CAPTURE = 'authorize_capture';
    const PAYMENT_CODE = 'sezzlepay';
    const PRECISION = 2;

    /**
     * @var string
     */
    protected static $_states;
    /**
     * @var string
     */
    protected $_code = 'sezzlepay';
    /**
     * @var bool
     */
    protected $_isGateway = true;
    /**
     * @var bool
     */
    protected $_canOrder = true;
    /**
     * @var bool
     */
    protected $_canAuthorize = true;
    /**
     * @var bool
     */
    protected $_canCapture = true;
    /**
     * @var bool
     */
    protected $_canCapturePartial = false;
    /**
     * @var bool
     */
    protected $_canCaptureOnce = false;
    /**
     * @var bool
     */
    protected $_canRefund = true;
    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;
    /**
     * @var bool
     */
    protected $_canVoid = false;
    /**
     * @var bool
     */
    protected $_canUseInternal = false;
    /**
     * @var bool
     */
    protected $_canUseCheckout = true;
    /**
     * @var bool
     */
    protected $_canUseForMultishipping = false;
    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;
    /**
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;
    /**
     * @var bool
     */
    protected $_canReviewPayment = true;
    /**
     * @var bool
     */
    protected $_canCreateBillingAgreement = false;
    /**
     * @var bool
     */
    protected $_canManageRecurringProfiles = false;
    /**
     * @var string
     */
    protected $_formBlockType = 'sezzle_sezzlepay/form_paylater';

    /**
     * Start the payment process
     *
     * @param $quote
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function start($quote)
    {
        try {
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
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Posting to sezzle to get the checkout redirect url: ' . $url, Zend_Log::DEBUG);
            // Send request
            $result = $this->getApiProcessor()->sendApiRequest(
                $this->getApiRouter()->getSubmitCheckoutDetailsAndGetRedirectUrl(),
                $requestBody,
                true,
                Varien_Http_Client::POST
            );
            if (isset($result['status']) && $result['status'] == Sezzle_Sezzlepay_Model_Api_Processor::BAD_REQUEST) {
                $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Sezzle Pay API Error : Error receiving complete URL from Sezzle.', Zend_Log::DEBUG);
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    __('Sezzle Pay API Error: %s', $result['message'])
                );
            }

            $checkoutUrl = isset($result['checkout_url']) ? $result['checkout_url'] : "";
            if (empty($checkoutUrl)) {
                $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Sezzle Pay API Error : Received empty checkout URL from Sezzle.', Zend_Log::DEBUG);
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    'Sezzle Pay API Error: Cannot get checkout Url'
                );
            }
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Received URL from Sezzle succesfully. URL : ' . $checkoutUrl, Zend_Log::DEBUG);
            return $checkoutUrl;
        } catch (Exception $e) {
            $this->helper()->log('Session : ' . $this->getSessionID() . ' reference: ' . $quote->getReservedOrderId() . ': Sezzle Pay API Error : Received empty checkout URL from Sezzle.', Zend_Log::ERR);
        }
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {
        switch ($paymentAction) {
            case self::ACTION_AUTHORIZE:
                $payment = $this->getInfoInstance();
                $order = $payment->getOrder();
                $order->setCanSendNewEmailFlag(false);
                $payment->authorize(true, $order->getBaseTotalDue()); // base amount will be set inside
                $payment->setAmountAuthorized($order->getTotalDue());

                $order->setState(Mage_Sales_Model_Order::STATE_NEW, 'new', '', false);

                $stateObject->setState(Mage_Sales_Model_Order::STATE_NEW);
                $stateObject->setStatus('pending');
                $stateObject->setIsNotified(false);
                break;
            case self::ACTION_AUTHORIZE_CAPTURE:
                $payment = $this->getInfoInstance();
                $order = $payment->getOrder();
                $order->setCanSendNewEmailFlag(false);
                $payment->capture(null);
                $payment->setAmountPaid($order->getTotalDue());
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing', '', false);

                $stateObject->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                $stateObject->setStatus('processing');
                break;
            default:
                break;
        }
    }

    /**
     * Get Sezzle helper
     *
     * @return Mage_Core_Helper_Abstract
     */
    public function helper()
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
        return Mage::getSingleton('core/session')->getEncryptedSessionId();
    }

    /**
     * Create unique referrence id
     *
     * @param $referenceId
     * @return string
     */
    protected function createUniqueReferenceId($referenceId)
    {
        return uniqid() . "-" . $referenceId;
    }

    /**
     * Check if order total id matching
     * 
     * @return bool
     */
    public function isOrderAmountMatched($magentoAmount, $sezzleAmount)
    {
       return (round($magentoAmount, 2) == round($sezzleAmount, 2)) ? true : false;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this|Mage_Payment_Model_Abstract
     * @throws Mage_Core_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $reference = $payment->getData('sezzle_reference_id');
        $grandTotalInCents = (int)(round($amount * 100, self::PRECISION));

        $this->helper()->log('Authorizing', Zend_Log::DEBUG);
        $result = $this->getSezzleOrderInfo($reference);

        $this->helper()->log(array(
            "response" => $result
        ), Zend_Log::DEBUG);

        if (isset($result['amount_in_cents']) 
            && !$this->isOrderAmountMatched($grandTotalInCents, $result['amount_in_cents']))
        {
            Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Unable to authorize due to invalid order total.'));
            return $this;
        }
        else {
            $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
            $this->helper()->log('Authorization successful', Zend_Log::DEBUG);
            return $this;
        }

        // invalid checkout
        Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Invalid checkout. Please retry again.'));
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this|Mage_Payment_Model_Abstract
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Invalid amount for capture.'));
        }

        $reference = $payment->getData('sezzle_reference_id');
        $grandTotalInCents = (int)(round($amount * 100, self::PRECISION));

        $this->helper()->log('Checking if checkout is valid', Zend_Log::DEBUG);
        // check if transaction id is valid
        $result = $this->getSezzleOrderInfo($reference);

        $this->helper()->log(array(
            "response" => $result
        ), Zend_Log::DEBUG);

        if (isset($result['amount_in_cents']) 
            && !$this->isOrderAmountMatched($grandTotalInCents, $result['amount_in_cents']))
        {
            Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Sezzle Pay gateway has rejected request due to invalid order total.'));
            return $this;
        }

        $capturedAt = (isset($result['captured_at']) && $result['captured_at']) ? $result['captured_at'] : null;
        $captureExpiration = (isset($result['capture_expiration']) && $result['capture_expiration']) ? $result['capture_expiration'] : null;
        if ($captureExpiration === null) {
            Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Not authorized on Sezzle. Please try again.'));
            return $this;
        }
        $captureExpirationTimestamp = Mage::getModel('core/date')->timestamp($captureExpiration);
        $currentTimestamp = Mage::getModel('core/date')->timestamp("now");
        if (($captureExpirationTimestamp >= $currentTimestamp) && $capturedAt == null) {
            $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
            $this->helper()->log('Valid checkout', Zend_Log::DEBUG);
            $this->helper()->log('Session : ' . $this->getSessionId() . ' Sezzle reference: ' . $reference . ': Capturing payment in Sezzle.', Zend_Log::DEBUG);
            $hasSezzleCaptured = $this->sezzleCaptureAndComplete($payment->getOrder());
            if ($hasSezzleCaptured) {
                $this->helper()->log('Session : ' . $this->getSessionId() . ' Sezzle reference: ' . $reference . ': Captured payment in Sezzle.', Zend_Log::DEBUG);
                $payment->setTransactionId($reference)->setIsTransactionClosed(true);
            }
            else {
                $this->helper()->log('Session : ' . $this->getSessionId() . ' Sezzle reference: ' . $reference . ': Capturing of payment in Sezzle is unsuccessful.', Zend_Log::DEBUG);
                Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Something went wrong while registering the order.'));
            }
            return $this;
        } else {
            $this->helper()->log('Session : ' . $this->getSessionId() . ' Sezzle reference: ' . $reference . ': Payment already captured in Sezzle.', Zend_Log::DEBUG);
            Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Payment already captured in Sezzle.'));
        }

        // invalid checkout
        Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Invalid checkout. Please retry again.'));
        return $this;
    }

    public function getCaptureStates()
    {
        if (is_null(self::$_states)) {
            self::$_states = array(
                self::STATE_CAPTURED       => Mage::helper('sales')->__('Captured'),
                self::STATE_NOT_CAPTURED       => Mage::helper('sales')->__('Not Captured'),
            );
        }
        return self::$_states;
    }
    
    /**
     * Get order info from Sezzle
     * 
     * @param string $reference
     * @throws Mage_Core_Exception
     * @return array
     */
    public function getSezzleOrderInfo($reference)
    {
        $result = $this->getApiProcessor()->sendApiRequest(
            $this->getApiRouter()->getCheckoutDetailsUrl($reference)
        );
        if (isset($result['status']) && $result['status'] == Sezzle_Sezzlepay_Model_Api_Processor::BAD_REQUEST) {
            $this->helper()->log('Checkout is invalid', Zend_Log::DEBUG);
            Mage::throwException(Mage::helper('sezzle_sezzlepay')->__('Invalid checkout. Please retry again.'));
            return $this;
        }
        return $result;
    }

    /**
     * Create request body
     *
     * @param $quote
     * @param $reference
     * @param $cancelUrl
     * @param $completeUrl
     * @return array
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function createCheckoutRequestBody($quote, $reference, $cancelUrl, $completeUrl)
    {
        $requestBody = array();
        $requestBody["amount_in_cents"] = (int)(round($quote->getGrandTotal() * 100, self::PRECISION));
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
            $productPrice = ($item->getProduct()->getPrice() * 100);
            $productSKU = $item->getProduct()->getSku();
            $productQuantity = $item->getQty();
            $itemData = array(
                "name" => $productName,
                "sku" => $productSKU,
                "quantity" => $productQuantity,
                "price" => array(
                    "amount_in_cents" => (int)(round($productPrice, self::PRECISION)),
                    "currency" => $requestBody["currency_code"]
                )
            );
            array_push($requestBody["items"], $itemData);
        }
        $requestBody["merchant_completes"] = true;
        return $requestBody;
    }

    /**
     * Get Api router
     *
     * @return false|Mage_Core_Model_Abstract
     */
    public function getApiRouter()
    {
        return Mage::getModel('sezzle_sezzlepay/api_router');
    }

    /**
     * Api Router
     *
     * @return Sezzle_Sezzlepay_Model_Api_Processor
     */
    public function getApiProcessor()
    {
        return Mage::getModel('sezzle_sezzlepay/api_processor');
    }

    /**
     * Get Sezzle payment refund
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this|Mage_Payment_Model_Abstract
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $reference = $payment->getData('sezzle_reference_id');
        $currency = $payment->getOrder()->getOrderCurrencyCode();
        $this->helper()->log('Session : ' . $this->getSessionId() . ' Refunding order reference: ' . $reference . ' amount: ' . $amount, Zend_Log::DEBUG);
        if ($amount == 0) {
            $this->helper()->log('Session : ' . $this->getSessionId() . " Zero amount refund is detected", Zend_Log::ERR);
            return $this;
        }
        // Refund
        $result = $this->getApiProcessor()->sendApiRequest(
            $this->getApiRouter()->getCheckoutRefundUrl($reference),
            array(
                "amount" => array(
                    "amount_in_cents" => (int)(round($amount * 100, self::PRECISION)),
                    "currency" => $currency
                )
            ),
            true,
            Varien_Http_Client::POST
        );
        if (isset($result['status']) && $result['status'] == Sezzle_Sezzlepay_Model_Api_Processor::BAD_REQUEST) {
            throw Mage::exception(
                'Sezzle_Sezzlepay',
                __('Sezzle Pay API Error: %s', $result['message'])
            );
        }
        $this->helper()->log('Session : ' . $this->getSessionId() . ' Refund with sezzle successful' . $amount, Zend_Log::DEBUG);
        return $this;
    }

    /**
     * Placing order
     *
     * @param $quote
     * @param $reference
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function place($quote, $reference)
    {
        // Converting quote to order
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $order = $service->getOrder();
        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Submitted and created order.', Zend_Log::DEBUG);

        // ensure that Grand Total is not doubled
        $order->setBaseGrandTotal($quote->getBaseGrandTotal());
        $order->setGrandTotal($quote->getGrandTotal());

        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Set grand total to order.', Zend_Log::DEBUG);

        // add Sezzle reference id for doing refunds
        $order->setExternalReferenceId($reference);
        $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Added sezzle reference to order.', Zend_Log::DEBUG);
        $order->save();
        $session = $this->_getCheckoutSession();
        if ($order->getId()) {
            // Check with recurring payment
            $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Checking recurring payment profiles.', Zend_Log::DEBUG);
            $profiles = $service->getRecurringPaymentProfiles();
            if ($profiles) {
                $ids = array();
                foreach ($profiles as $profile) {
                    $ids[] = $profile->getId();
                }
                $session->setLastRecurringProfileIds($ids);
            }

            // prepare session to success or cancellation page clear current session
            $session->clearHelperData();
            // "last successful quote" for correctly redirect to success page
            $quoteId = $session->getQuote()->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);
            $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Creating order in session.', Zend_Log::DEBUG);
            // an order may be created
            $session->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());
            try {
                $sezzleOrderInfo = $this->getSezzleOrderInfo($order->getPayment()->getData('sezzle_reference_id'));
                if (isset($sezzleOrderInfo['capture_expiration']) && $sezzleOrderInfo['capture_expiration']) {
                    $order->setSezzleCaptureExpiry($sezzleOrderInfo['capture_expiration']);
                    $order->save();
                }

                if (!$order->getEmailSent()) {
                    $order->sendNewOrderEmail();
                }
                // clear the cart only if capture successful
                $session->getQuote()->setIsActive(0)->save();
                $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Cleared cart.', Zend_Log::DEBUG);
            } catch (Exception $e) {
                $this->helper()->log('Session : ' . $this->getSessionId() . ' reference: ' . $quote->getReservedOrderId() . ': Exception occured while completing order : ', Zend_Log::ERR);
                $this->helper()->log($e->getMessage(), Zend_Log::ERR);
            }
            return true;
        }
        return false;
    }

    /**
     * Get Sezzle Config Model
     *
     * @return Sezzle_Sezzlepay_Model_Config
     */
    protected function getSezzleConfigModel()
    {
        return Mage::getModel('sezzle_sezzlepay/config');
    }

    /**
     * Get checkout session
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @param Varien_Object $order
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function sezzleCaptureAndComplete(Varien_Object $order)
    {
        $reference = $order->getPayment()->getData('sezzle_reference_id');
        // Charge
        $result = $this->getApiProcessor()->sendApiRequest(
            $this->getApiRouter()->getCheckoutCompleteUrl($reference),
            null,
            true,
            Varien_Http_Client::POST
        );
        if (isset($result['status']) && $result['status'] == Sezzle_Sezzlepay_Model_Api_Processor::BAD_REQUEST) {
            throw Mage::exception(
                'Sezzle_Sezzlepay',
                __('Sezzle Pay API Error: %s', $result['message'])
            );
        }
        elseif (isset($result["order_reference_id"]) && $result["order_reference_id"] != null) {
            $order->setIsCaptured(self::STATE_CAPTURED)->save();
        }

        return true;
    }

    /**
     * Cancel Order
     *
     * @param $order
     */
    protected function _cancelOrder($order)
    {
        $this->helper()->log('Session : ' . $this->getSessionId() . ' Cancelling order.', Zend_Log::DEBUG);
        $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Cancelling sezzle payment.');
        $order->save();
        $this->helper()->log('Session : ' . $this->getSessionId() . ' Cancelled order.', Zend_Log::DEBUG);
    }
}
