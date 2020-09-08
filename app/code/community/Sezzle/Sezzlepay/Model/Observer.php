<?php

/**
 * Sezzlepay Observer
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Observer
{
    /**
     * Capture Payment for non captured orders via Cron
     *
     * @return void
     */
    public function capturePayment() {
        $this->helper()->log("****Start of Capture Payment for non captured orders via Cron****",Zend_Log::DEBUG);
        $collection = Mage::getModel('sales/order')->getCollection()
            ->join(
                array('payment' => 'sales/order_payment'),
                'main_table.entity_id = payment.parent_id',
                array('payment_method' => 'payment.method')
            )->addFieldToFilter('payment.method', array('eq' => 'sezzlepay'));

        $currentTimestamp = Mage::getModel('core/date')->timestamp("now");
        foreach ($collection as $order) {
            $paymentType = $order->getPayment()->getAdditionalInformation("payment_type");
            if (!$order->getPayment()->getAdditionalInformation('is_captured')
                && $paymentType == Sezzle_Sezzlepay_Model_Sezzlepay::AUTH_CAPTURE) {
                $captureExpiration = $order->getPayment()->getAdditionalInformation('sezzle_capture_expiry');
                $captureExpirationTimestamp = Mage::getModel('core/date')->timestamp($captureExpiration);
                if (($captureExpirationTimestamp >= $currentTimestamp)) {
                    $this->helper()->log("Capturing payment for order #" . $order->getIncrementId());
                    $hasSezzleCaptured = Mage::getModel("sezzle_sezzlepay/sezzlepay")->sezzleCaptureAndComplete($order);
                    if ($hasSezzleCaptured) {
                        $this->helper()->log("Capturing payment for order #" . $order->getIncrementId() . " is successful");
                        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
                    }
                }
            }
        }
        $this->helper()->log("****End of Capture Payment for non captured orders via Cron****",Zend_Log::DEBUG);
    }

    /**
     * Hide Sezzle based on min checkout amount(grand total)
     *
     * @return void
     */
    public function hideGateway(Varien_Event_Observer $observer) {
        $quote = $observer->getEvent()->getQuote();
        $result = $observer->getEvent()->getResult();
        $methodInstance = $observer->getEvent()->getMethodInstance();

        $minCheckoutAmount = $methodInstance->getConfigData('min_checkout_amount', $quote ? $quote->getStoreId() : null);

        if ($methodInstance->getCode() == Sezzle_Sezzlepay_Model_Sezzlepay::PAYMENT_CODE
            && $quote
            && ($quote->getBaseGrandTotal() < $minCheckoutAmount)) {
                $result->isAvailable = false;
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
}
