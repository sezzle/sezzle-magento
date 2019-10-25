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
        $nonCapturedOrders = Mage::getModel("sales/order")
            ->getCollection()
            ->addFieldToFilter("is_captured",Sezzle_Sezzlepay_Model_Sezzlepay::STATE_NOT_CAPTURED);
        $currentTimestamp = Mage::getModel('core/date')->timestamp("now");
        foreach ($nonCapturedOrders as $order) {
            $captureExpiration = $order->getSezzleCaptureExpiry();
            $captureExpirationTimestamp = Mage::getModel('core/date')->timestamp($captureExpiration);
            $paymentType = $order->getPayment()->getAdditionalInformation("payment_type");
            if (($captureExpirationTimestamp >= $currentTimestamp) && $paymentType == Sezzle_Sezzlepay_Model_Sezzlepay::AUTH_CAPTURE) {
                $this->helper()->log("Capturing payment for order #".$order->getIncrementId());
                $hasSezzleCaptured = Mage::getModel("sezzle_sezzlepay/sezzlepay")->sezzleCaptureAndComplete($order);
                if ($hasSezzleCaptured) {
                    $this->helper()->log("Capturing payment for order #".$order->getIncrementId()." is successful");
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
                }
            }
        }
        $this->helper()->log("****End of Capture Payment for non captured orders via Cron****",Zend_Log::DEBUG);
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