<?php

/**
 * Sezzle Pay observer
 *
 */
class Sezzle_Sezzlepay_Model_Observer
{
    public function capturePayment() {
        $nonCapturedOrders = Mage::getModel("sales/order")
            ->getCollection()
            ->addFieldToFilter("is_captured",0);
        $currentTimestamp = Mage::getModel('core/date')->timestamp("now");
        foreach ($nonCapturedOrders as $order) {
            $orderReferenceId = $order->getPayment()->getData("sezzle_reference_id");
            $sezzleOrderInfo = Mage::getModel("sezzle_sezzlepay/sezzlepay")->getSezzleOrderInfo($orderReferenceId);
            $captureExpirationTimestamp = Mage::getModel('core/date')->timestamp($sezzleOrderInfo["capture_expiration"]);
            $paymentAction = $order->getPayment()->getAdditionalInformation("payment_type");
            if (($captureExpirationTimestamp >= $currentTimestamp) && $paymentAction == Sezzle_Sezzlepay_Model_Sezzlepay::AUTH_CAPTURE) {
                Mage::getModel("sezzle_sezzlepay/sezzlepay")->sezzleCaptureAndComplete($order);
            }
        }
    }

}