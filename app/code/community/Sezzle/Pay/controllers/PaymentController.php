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
    public function responseAction() {
    }
} 