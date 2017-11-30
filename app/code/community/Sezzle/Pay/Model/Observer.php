<?php
class Sezzle_Pay_Model_Observer
{
    public function sendDailyData(Mage_Cron_Model_Schedule $schedule) {
        $this->sendOrdersToSezzle();
        $this->sendHeartbeat();
    }

    protected function helper()
    {
        return Mage::helper('sezzle_pay');
    }

    protected function sendHeartbeat() {
        $is_public_key_entered = strlen(Mage::getStoreConfig('payment/pay/public_key')) > 0 ? true : false;
        $is_private_key_entered = strlen(Mage::getStoreConfig('payment/pay/private_key')) > 0 ? true : false;
        $is_widget_configured = strlen(explode('|', Mage::getStoreConfig('sezzle_pay/product_widget/xpath'))[0]) > 0 ? true : false;
        $is_merchant_id_entered = strlen(Mage::getStoreConfig('sezzle_pay/product_widget/merchant_id')) > 0 ? true : false;
        $is_payment_active = Mage::getStoreConfig('payment/pay/active') == 1 ? true : false;

        $body = array(
            'is_payment_active' => $is_payment_active,
            'is_widget_active' => true,
            'is_widget_configured' => $is_widget_configured,
            'is_merchant_id_entered' => $is_merchant_id_entered,
        );

        if ($is_public_key_entered && $is_private_key_entered) {
            $url = $this->getApiRouter()->getHeartbeatUrl();

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
            $this->helper()->log('Heartbeat sent to Sezzle');
        } else {
            $this->helper()->log('Could not send Heartbeat to Sezzle. Please set api keys.');
        }
    }

    protected function sendOrdersToSezzle() {
        $today = date("Y-m-d H:i:s");
        $yesterday = date("Y-m-d H:i:s", strtotime("-1 days"));

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