<?php
if (!function_exists('boolval')) {
    function boolval($val) 
    {
            return (bool) $val;
    }
}

class Sezzle_Pay_PaymentController extends Mage_Core_Controller_Front_Action
{

    // Redirect to sezzle pay 
    public function redirectAction()
    {
        try {
            $this->helper()->log('Step 5 Process: Loading the redirect.html page');
            $this->loadLayout();
            $storeId = Mage::app()->getStore()->getStoreId();
            $storeCode = Mage::app()->getStore()->getCode();
            $this->helper()->log("Store ID and Code: $storeId | $storeCode");

            // Get latest order data
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            
            // Utilise Magento Session to preserve Store Credit details
    	    if( Mage::getEdition() == Mage::EDITION_ENTERPRISE ) {
                $this->helper()->storeCreditPlaceOrder();
                $this->helper()->giftCardsPlaceOrder();    	    	
            }
            
            // Set status to payment pending
            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
            $accountID = Mage::getStoreConfig('payment/pay/public_key', $storeId);
            $privateKey = Mage::getStoreConfig('payment/pay/private_key', $storeId);

            // Cost details
            $amount = $order->getBaseGrandTotal();
            $currency = Mage::app()->getStore()->getCurrentCurrencyCode();
            
            
            // Billing address
            $billingAddress = $order->getBillingAddress();
            $billingAddressOne = $billingAddress->getStreet(1);
            $billingAddressTwo = $billingAddress->getStreet2();
            $billingCity = $billingAddress->getCity();
            $billingPhone = $billingAddress->getTelephone();
            $billingZip = $billingAddress->getPostcode();
            $billingState = $billingAddress->getRegionCode();
            $billingCountry = $billingAddress->getCountry();

            // User details
            $email = $order->getCustomerEmail();
            $firstName = $order->getCustomerFirstname();
            $lastName = $order->getCustomerLastname();
            $phone = $billingAddress->getTelephone();

            // Shipping address
            $shippingAddress = $order->getShippingAddress();
            $shippingAddressOne = $shippingAddress->getStreet(1);
            $shippingAddressTwo = $shippingAddress->getStreet2();
            $shippingCity = $shippingAddress->getCity();
            $shippingPhone = $shippingAddress->getTelephone();
            $shippingZip = $shippingAddress->getPostcode();
            $shippingState = $shippingAddress->getRegionCode();
            $shippingCountry = $shippingAddress->getCountry();
            $shippingFirstname = $shippingAddress->getFirstname();
            $shippingLastname = $shippingAddress->getLastname();
            
            // Reference
            $reference = $orderId;
            $countryCode = Mage::getStoreConfig('general/country/default');
            $shopName = Mage::app()->getStore()->getFrontendName();
            $testMode = false;
            $url = Mage::getStoreConfig('payment/pay/base_url', $storeId);
            $tranId = uniqid() . "-" . $orderId;

            // Fix urls
            $completeUrl = Mage::getUrl('pay/payment/success', array('id' => $tranId));
            $completeUrl = Mage::getModel('core/url')->sessionUrlVar($completeUrl);
            $cancelUrl = Mage::getUrl('pay/payment/cancel', array('id' => $tranId));
            $cancelUrl = Mage::getModel('core/url')->sessionUrlVar($cancelUrl);

            $data = Array(
                "x_account_id" => $accountID,
                "x_amount" => $amount,
                "x_currency" => $currency,
                "x_customer_billing_address1" => $billingAddressOne,
                "x_customer_billing_address2" => $billingAddressTwo,
                "x_customer_billing_city" => $billingCity,
                "x_customer_billing_country" => $billingCountry,
                "x_customer_billing_phone" => $billingPhone,
                "x_customer_billing_zip" => $billingZip,
                "x_customer_billing_state" => $billingState,
                "x_customer_email" => $email,
                "x_customer_first_name" => $firstName,
                "x_customer_last_name" => $lastName,
                "x_customer_phone" => $phone,
                "x_customer_shipping_address1" => $shippingAddressOne,
                "x_customer_shipping_address2" => $shippingAddressTwo,
                "x_customer_shipping_city" => $shippingCity,
                "x_customer_shipping_country" => $shippingCountry,
                "x_customer_shipping_first_name" => $shippingFirstname,
                "x_customer_shipping_last_name" => $shippingLastname,
                "x_customer_shipping_phone" => $shippingPhone,
                "x_customer_shipping_zip" => $shippingZip,
                "x_customer_shipping_state" => $shippingState,
                "x_reference" => $orderId,
                "x_shop_country" => $countryCode,
                "x_shop_name" => $shopName,
                "x_test" => $testMode,
                'x_url_complete' => $completeUrl,
                'x_url_cancel' => $cancelUrl,
            );

            // Create the signature
            $ver = explode('.', phpversion());
            $major = (int) $ver[0];
            $minor = (int) $ver[1];
            if ($major >= 5 and $minor >= 4) {
                ksort($data, SORT_STRING | SORT_FLAG_CASE);
            } else {
                uksort($data, 'strcasecmp');
            }

            $message = "";
            foreach ($data as $key => $value) {
                $message .= "$key$value";
            }

            $this->helper()->log($message);

            $sign = hash_hmac("sha256", $message, $privateKey);
            Mage::log(
                "sign=$sign", Zend_Log::DEBUG,
                $this->_logFileName
            );
            $data["x_signature"] = $sign;
            
            $arrayString = json_encode($data);

            // Log the whole form information
            $this->helper()->log("data=$arrayString");

            // Save this order
            $payment = $order->getPayment();
            $payment->setTransactionId($tranId);
            $transaction = $payment->addTransaction(
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH
            );
            $transaction->setAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                array('Context'=>'Order payment',
                  'Amount'=>$amount,
                  'Status'=>0,
                  'Url'=>$url
                )
            );
            $transaction->setIsTransactionClosed(false);
            $transaction->save();
            $order->save();

            // Redirect to the redirect page
            $block = $this
                ->getLayout()
                ->createBlock(
                    'Mage_Core_Block_Template',
                    'pay', 
                    array('template' => 'pay/redirect.phtml')
                )
                ->assign(Array("data" => $data, "url" => $url));
            $this->getLayout()->getBlock('content')->append($block);
            $this->renderLayout();
        } catch (Exception $e){
            Mage::logException($e);
            $this->helper()->log($e, Zend_Log::ERR);
            parent::_redirect('checkout/cart');
        }
    }
    // Redirect from Sezzle Pay
    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function successAction() 
    {
        try {
            $this->helper()->log("Running response action");
            
            $storeId = Mage::app()->getStore()->getStoreId();
            $storeCode = Mage::app()->getStore()->getCode();
            $this->helper()->log("Store ID and Code: $storeId | $storeCode");

            // Get the $sezzleId from the request
            $sezzleId = $this->getRequest()->getQuery('x_gateway_reference');
            $this->helper()->log("sezzleId: $sezzleId");

            // Get transaction id from request url
            $tranId = $this->getRequest()->getParam('id');
            $this->helper()->log("TransactionId: $tranId");

            // Get the order id from the request url
            $orderTranId = explode('-', $tranId);
            $transactionId = $orderTranId[0];
            $orderId = $orderTranId[1];
            $this->helper()->log("TransactionId and orderId: $transactionId | $orderId");

            // Get the order object
            $order = Mage::getModel('sales/order');
            $cart = Mage::getSingleton('checkout/cart');
            $order->loadByIncrementId($orderId);
            $session = Mage::getSingleton('checkout/session');
            $this->helper()->log("Received order object");

            // Get payment details of this transaction
            $payment = $order->getPayment();
            $transaction = $payment->getTransaction($tranId);
            $data = $transaction->getAdditionalInformation();
            $url = $data['raw_details_info']['Url'];
            $amount = $this->getRequest()->getQuery('x_amount');

            $this->helper()->log("Amount received");

            // Set additional details to have information of successful payment
            $transaction->setAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                array('SezzleId'=> $sezzleId,
                    'Context'=>'Successful Payment',
                    'Amount'=>$amount,
                    'Status'=>1,
                    'Url'=>$url
                )
            )->save();

            $this->helper()->log("Transaction additional info saved");

            // create invoice
            if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice->register()->capture();
                $invoice->sendEmail()->setEmailSent(true);
                $order->addRelatedObject($invoice);
            }

            $this->helper()->log("Transaction invoice created");

            // send new order email
            $order->setEmailSent(true);
            $order->setIsCustomerNotified(true);

            $this->helper()->log("Transaction email sent");

            // Save and close this transaction
            $transaction->setParentTxnId($sezzleId)->save();
            $payment->setIsTransactionClosed(1);

            $this->helper()->log("Transaction details saved");

            // Save order
            $order->save();
            $this->helper()->log("Save order");

            // Redirect to success page
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        } catch (Exception $e){
            Mage::logException($e);
            $this->helper()->log($e, Zend_Log::ERR);
            parent::_redirect('checkout/cart');
        }
    }


    // Redirect from Sezzle Pay
    // A cancelled payment
    public function cancelAction() 
    {
        try {
            Mage::log("Running response action", Zend_Log::DEBUG, $this->_logFileName); 

            $storeId = Mage::app()->getStore()->getStoreId();
            $storeCode = Mage::app()->getStore()->getCode();
            $this->helper()->log("Store ID and Code: $storeId | $storeCode");

            // Get transaction id from request url
            $tranId = $this->getRequest()->getParam('id');
            $this->helper()->log("TransactionId: $tranId");

            // Get the order id from the request url
            $orderTranId = explode('-', $tranId);
            $transactionId = $orderTranId[0];
            $orderId = $orderTranId[1];
            $this->helper()->log("TransactionId and orderId: $transactionId | $orderId");

            // Get the order object
            $order = Mage::getModel('sales/order');
            $cart = Mage::getSingleton('checkout/cart');
            $order->loadByIncrementId($orderId);
            $session = Mage::getSingleton('checkout/session');
            $this->helper()->log("Received order object");

            // Cancel the order
            $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Payment failed.')->save();
            $this->helper()->log("Order canceled!");

            // Add items back to the cart
            $items = $order->getItemsCollection();
            foreach ($items as $item) {
                try {
                    $cart->addOrderItem($item);
                } catch (Mage_Core_Exception $e) {
                    $session->addError($this->__($e->getMessage()));
                    Mage::logException($e);
                    $this->helper()->log("ERROR: $e", Zend_Log::ERR);
                    continue;
                }
            }

            $cart->save();
            $this->helper()->log("Cart populated back");

            // redirect to the cart
            Mage::getSingleton('core/session')->addError('Your payment failed.');
            $this->helper()->log("Redirecting to cart...");
            $this->_redirect('checkout/cart');
            return;
        } catch (Exception $e){
            Mage::logException($e);
            $this->helper()->log($e, Zend_Log::ERR);
            parent::_redirect('checkout/cart');
        }
    }

    /**
     * Get checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get core session
     *
     * @return Mage_Core_Model_Session
     */
    protected function getSession()
    {
        return Mage::getSingleton('core/session');
    }

    /**
     * @return Afterpay_Afterpay_Helper_Data
     */
    protected function helper()
    {
        Mage::Log('Getting sezzle helper...', Zend_Log::DEBUG, $this->_logFileName);        
        return Mage::helper('sezzle_pay');
    }

} 