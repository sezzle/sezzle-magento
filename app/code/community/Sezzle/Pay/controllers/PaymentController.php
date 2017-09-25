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
            $accountID = Mage::getStoreConfig('payment/imojo/public_key', $storeId);

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
            $billingState = $billingAddress->getRegion();

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
            $shippingState = $shippingAddress->getRegion();
            
            // Reference
            $reference = $orderId;
            $countryCode = Mage::getStoreConfig('general/country/default');
            $shopName = Mage::app()->getStore()->getName();
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