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
    protected $_logFileName = 'sezzle-pay.log';
    public function redirectAction()
    {
        try {
            Mage::Log('Step 5 Process: Loading the redirect.html page', Zend_Log::DEBUG, $this->_logFileName);
            $this->loadLayout();
            $storeId = Mage::app()->getStore()->getStoreId();
            $storeCode = Mage::app()->getStore()->getCode();
            Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->_logFileName);

            // Get latest order data
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            
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

            Mage::log(
                "message=$message", Zend_Log::DEBUG,
                $this->_logFileName
            );

            $sign = hash_hmac("sha256", $message, $privateKey);
            Mage::log(
                "sign=$sign", Zend_Log::DEBUG,
                $this->_logFileName
            );
            $data["x_signature"] = $sign;
            
            $arrayString = json_encode($data);

            // Log the whole form information
            Mage::log(
                "data=$arrayString", Zend_Log::DEBUG,
                $this->_logFileName
            );

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
            Mage::log($e, Zend_Log::ERR, $this->_logFileName);
            parent::_redirect('checkout/cart');
        }
    }
    // Redirect from Sezzle Pay
    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function successAction() 
    {
        try {
            Mage::log("Running response action", Zend_Log::DEBUG, $this->_logFileName);
            
            $storeId = Mage::app()->getStore()->getStoreId();
            $storeCode = Mage::app()->getStore()->getCode();
            Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->_logFileName);

            // Get the $sezzleId from the request
            $sezzleId = $this->getRequest()->getQuery('x_gateway_reference');
            Mage::log("sezzleId: $sezzleId", Zend_Log::DEBUG, $this->_logFileName);

            // Get transaction id from request url
            $tranId = $this->getRequest()->getParam('id');
            Mage::log("TransactionId: $tranId", Zend_Log::DEBUG, $this->_logFileName);

            // Get the order id from the request url
            $orderTranId = explode('-', $tranId);
            $transactionId = $orderTranId[0];
            $orderId = $orderTranId[1];
            Mage::log("TransactionId and orderId: $transactionId | $orderId", Zend_Log::DEBUG, $this->_logFileName);

            // Get the order object
            $order = Mage::getModel('sales/order');
            $cart = Mage::getSingleton('checkout/cart');
            $order->loadByIncrementId($orderId);
            $session = Mage::getSingleton('checkout/session');
            Mage::log("Received order object", Zend_Log::DEBUG, $this->_logFileName);

            // Set order state
            Mage::log("Payment was successfull for $sezzleId", Zend_Log::DEBUG, $this->_logFileName);
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
            Mage::log("Pending payment is set to False", Zend_Log::DEBUG, $this->_logFileName);

            Mage::log("Save order", Zend_Log::DEBUG, $this->_logFileName);

            // Get payment details of this transaction
            $payment = $order->getPayment();
            $transaction = $payment->getTransaction($tranId);
            $data = $transaction->getAdditionalInformation();
            $url = $data['raw_details_info']['Url'];
            $amount = $this->getRequest()->getQuery('x_amount');

            Mage::log("Amount received", Zend_Log::DEBUG, $this->_logFileName);

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

            Mage::log("Transaction additional info saved", Zend_Log::DEBUG, $this->_logFileName);

            // create invoice
            if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice->register()->capture();
                $order->addRelatedObject($invoice);   
            }

            Mage::log("Transaction invoice created", Zend_Log::DEBUG, $this->_logFileName);

            // send new order email
            $order->queueNewOrderEmail();
            $order->setEmailSent(true);
            $order->setIsCustomerNotified(true);

            Mage::log("Transaction email sent", Zend_Log::DEBUG, $this->_logFileName);

            // Save and close this transaction
            $transaction->setParentTxnId($sezzleId)->save();
            $payment->setIsTransactionClosed(1);

            Mage::log("Transaction details saved", Zend_Log::DEBUG, $this->_logFileName);

            // Save order
            $order->save();

            // Redirect to success page
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
        } catch (Exception $e){
            Mage::logException($e);
            Mage::log($e, Zend_Log::ERR, $this->_logFileName);
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
            Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->_logFileName);

            // Get transaction id from request url
            $tranId = $this->getRequest()->getParam('id');
            Mage::log("TransactionId: $tranId", Zend_Log::DEBUG, $this->_logFileName);

            // Get the order id from the request url
            $orderTranId = explode('-', $tranId);
            $transactionId = $orderTranId[0];
            $orderId = $orderTranId[1];
            Mage::log("TransactionId and orderId: $transactionId | $orderId", Zend_Log::DEBUG, $this->_logFileName);

            // Get the order object
            $order = Mage::getModel('sales/order');
            $cart = Mage::getSingleton('checkout/cart');
            $order->loadByIncrementId($orderId);
            $session = Mage::getSingleton('checkout/session');
            Mage::log("Received order object", Zend_Log::DEBUG, $this->_logFileName);

            // Cancel the order
            $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Payment failed.')->save();
            Mage::log("Order canceled!", Zend_Log::DEBUG, $this->_logFileName);

            // Add items back to the cart
            $items = $order->getItemsCollection();
            foreach ($items as $item) {
                try {
                    $cart->addOrderItem($item);
                } catch (Mage_Core_Exception $e) {
                    $session->addError($this->__($e->getMessage()));
                    Mage::logException($e);
                    continue;
                }
            }

            $cart->save();
            Mage::log("Cart populated back", Zend_Log::DEBUG, $this->_logFileName);

            // redirect to the cart
            Mage::getSingleton('core/session')->addError('Your payment failed.');
            Mage::log("Redirecting to cart...", Zend_Log::DEBUG, $this->_logFileName);
            $this->_redirect('checkout/cart');
            return;
        } catch (Exception $e){
            Mage::logException($e);
            Mage::log($e, Zend_Log::ERR, $this->_logFileName);
            parent::_redirect('checkout/cart');
        }
    }
} 