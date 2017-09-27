<?php
if (!function_exists('boolval')) {
    function boolval($val) {
            return (bool) $val;
    }
}
class Sezzle_Pay_PaymentController extends Mage_Core_Controller_Front_Action
{
    // Redirect to sezzle pay 
    private $LOG_FILE_NAME = 'sezzle-pay.log';
    public function redirectAction()
    {
        try {
            Mage::Log('Step 5 Process: Loading the redirect.html page', Zend_Log::DEBUG, $this->LOG_FILE_NAME);
            $this->loadLayout();
            $storeId = Mage::app()->getStore()->getStoreId();
            $storeCode = Mage::app()->getStore()->getCode();
            Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

            // Get latest order data
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            
            // Set status to payment pending
            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
            $accountID = Mage::getStoreConfig('payment/pay/public_key', $storeId);
            $private_key = Mage::getStoreConfig('payment/pay/private_key', $storeId);

            // Cost details
            $amount = $order-> getBaseGrandTotal();
            $currency = $order->getOrderCurrencyCode();
            
            // Billing address
            $billingAddress = $order->getBillingAddress();
            $billingAddress1 = $billingAddress->getStreet(1);
            $billingAddress2 = $billingAddress->getStreet2();
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
            $shippingAddress1 = $shippingAddress->getStreet(1);
            $shippingAddress2 = $shippingAddress->getStreet2();
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
            $shopName = Mage::app()->getStore()->getName();
            $testMode = false;
            $url = Mage::getStoreConfig('payment/pay/base_url', $storeId);
            $tranId = time() . "-" . $orderId;

            // Fix urls
            $completeUrl = Mage::getUrl('pay/payment/success', array('id' => $tranId));
            $completeUrl = Mage::getModel('core/url')->sessionUrlVar($completeUrl);
            $cancelUrl = Mage::getUrl('pay/payment/cancel', array('id' => $tranId));
            $cancelUrl = Mage::getModel('core/url')->sessionUrlVar($cancelUrl);

            $data = Array(
                "x_account_id" => $accountID,
                "x_amount" => $amount,
                "x_currency" => $currency,
                "x_customer_billing_address1" => $billingAddress1,
                "x_customer_billing_address2" => $billingAddress2,
                "x_customer_billing_city" => $billingCity,
                "x_customer_billing_country" => $billingCountry,
                "x_customer_billing_phone" => $billingPhone,
                "x_customer_billing_zip" => $billingZip,
                "x_customer_billing_state" => $billingState,
                "x_customer_email" => $email,
                "x_customer_first_name" => $firstName,
                "x_customer_last_name" => $lastName,
                "x_customer_phone" => $phone,
                "x_customer_shipping_address1" => $shippingAddress1,
                "x_customer_shipping_address2" => $shippingAddress2,
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
            if($major >= 5 and $minor >= 4){
                ksort($data, SORT_STRING | SORT_FLAG_CASE);
            }
            else{
                uksort($data, 'strcasecmp');
            }
            $message = "";
            foreach ($data as $key => $value) {
                $message .= "$key$value";
            }

            Mage::log(
                "message=$message", Zend_Log::DEBUG,
                $this->LOG_FILE_NAME
            );

            $sign = hash_hmac("sha256", $message, $private_key);
            Mage::log(
                "sign=$sign", Zend_Log::DEBUG,
                $this->LOG_FILE_NAME
            );
            $data["x_signature"] = $sign;
            
            $array_string = json_encode($data);

            // Log the whole form information
            Mage::log(
                "data=$array_string", Zend_Log::DEBUG,
                $this->LOG_FILE_NAME
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
            Mage::log($e, Zend_Log::ERR, $this->LOG_FILE_NAME);
            parent::_redirect('checkout/cart');
        }
    }
    // Redirect from Sezzle Pay
    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function successAction() {
        Mage::log("Running response action", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
        
        $storeId = Mage::app()->getStore()->getStoreId();
        $storeCode = Mage::app()->getStore()->getCode();
        Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
    }


    // Redirect from Sezzle Pay
    // A cancelled payment
    public function cancelAction() {
        Mage::log("Running response action", Zend_Log::DEBUG, $this->LOG_FILE_NAME); 

        $storeId = Mage::app()->getStore()->getStoreId();
        $storeCode = Mage::app()->getStore()->getCode();
        Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

        // Get transaction id from request url
        $tranId = $this->getRequest()->getParam('id');
        Mage::log("TransactionId: $tranId", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

        // Get the order id from the request url
        $order_tran_id = explode('-',  $tranId);
        $transactionId = $order_tran_id[0];
        $orderId = $order_tran_id[1];
        Mage::log("TransactionId and orderId: $transactionId | $orderId", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

        // Get the order object
        $order = Mage::getModel('sales/order');
        $cart = Mage::getSingleton('checkout/cart');
        $order->loadByIncrementId($orderId);
        $session = Mage::getSingleton('checkout/session');
        Mage::log("Received order object", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

        // Cancel the order
        $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Payment failed.')->save();
        Mage::log("Order canceled!", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

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
        Mage::log("Cart populated back", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

        // redirect to the cart
        Mage::getSingleton('core/session')->addError('Your payment failed.');
        Mage::log("Redirecting to cart...", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
        $this->_redirect('checkout/cart');
        return;
    }
} 