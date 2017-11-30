<?php
class Sezzle_Pay_Model_Observer
{
    public function sendDailyData(Mage_Cron_Model_Schedule $schedule) {
        $this->sendOrdersToSezzle();
    }

    protected function helper()
    {
        return Mage::helper('sezzle_pay');
    }

    protected function sendOrdersToSezzle() {
        $today = date("Y-m-d H:i:s");
        $yesterday = date("Y-m-d H:i:s", strtotime("-5 days"));

        $yesterday = date('Y-m-d H:i:s', strtotime($yesterday));
        $today = date('Y-m-d H:i:s', strtotime($today));
        $ordersCollection = Mage::getModel('sales/order')->getCollection()
            // Get only if status is complete or processing
            ->addFieldToFilter('status',
                array(
                    'eq' => 'complete',
                    'eq' => 'processing'
                )
            )
            // Get last day to today
            ->addAttributeToFilter('created_at',
                array(
                    'from' => $yesterday,
                    'to' => $today
                )
            )
            ->addAttributeToSelect('increment_id');
        $body = array();
        foreach ($ordersCollection as $orderObj) {
            $orderIncrementId = $orderObj->getIncrementId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
            $payment = $order->getPayment();
            $billing = $order->getBillingAddress();

            $orderForSezzle = array(
                'order_number' => $orderIncrementId,
                'payment_method' => $payment->getMethod() == 'pay' ? 'sezzlepay' : $payment->getMethod(),
                'amount' => $order->getGrandTotal() * 100,
                'currency' => $order->getOrderCurrencyCode(),
                'sezzle_reference' => $payment->getData('sezzle_reference_id'),
                'customer_email' => $billing->getEmail(),
                'customer_phone' => $billing->getTelephone(),
                'billing_address1' => $billing->getStreet(1),
                'billing_address2' => $billing->getStreet2(),
                'billing_city' => $billing->getCity(),
                'billing_state' => $billing->getRegionCode(),
                'billing_postcode' => $billing->getPostcode(),
                'billing_country' => $billing->getCountry()
            );
            array_push($body, $orderForSezzle);
        }

        $url = $this->getApiRouter()->getOrdersSubmitUrl();

        $result = $this->getSezzleBaseModel()->_sendApiRequest(
            $url,
            $body,
            true,
            Varien_Http_Client::POST
        );
        if ($result->isError()) {
            throw Mage::exception(
                'Sezzle_Pay',
                __('Sezzle Pay API Error: %s', $result->getMessage())
            );
        }
        $this->helper()->log('Order data sent to sezzle succefully');
    }

    private function getSezzleBaseModel() {
        return Mage::getModel('sezzle_pay/PaymentMethod');
    }

    protected function getApiRouter() 
    {
        return Mage::getModel('sezzle_pay/api_router');
    }
}