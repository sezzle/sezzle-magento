<?php

/**
 * Sezzlepay order to sezzle model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Gateway_Order
{
    /**
     * Send orders to Sezzle
     *
     * @throws Mage_Core_Exception
     */
    public function sendOrdersToSezzle()
    {
        $today = date("Y-m-d H:i:s");
        $yesterday = date("Y-m-d H:i:s", strtotime("-1 days"));
        $yesterday = date('Y-m-d H:i:s', strtotime($yesterday));
        $today = date('Y-m-d H:i:s', strtotime($today));
        try {
            $ordersCollection = Mage::getModel('sales/order')->getCollection()
                // Get only if status is complete or processing
                ->addFieldToFilter(
                    'status',
                    array(
                        'eq' => 'complete',
                        'eq' => 'processing'
                    )
                )
                // Get last day to today
                ->addAttributeToFilter(
                    'created_at',
                    array(
                        'from' => $yesterday,
                        'to' => $today
                    )
                )
                ->addAttributeToSelect('increment_id');
            $url = $this->getApiRouter()->getOrdersSubmitUrl();
            $body = $this->_buildOrderPayload($ordersCollection);
            $result = $this->getSezzleBaseModel()->_sendApiRequest(
                $url,
                $body,
                true,
                Varien_Http_Client::POST
            );
            if ($result->isError()) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    __('Sezzle Pay API Error: %s', $result->getMessage())
                );
            }
            $this->helper()->log('Order data sent to sezzle successfully');
        } catch (Exception $e) {
            $this->helper()->log('Error while sending order to Sezzle' . $e->getMessage());
        }
    }

    /**
     * Build order payload for Sezzle
     *
     * @param null $ordersCollection
     * @return array
     */
    private function _buildOrderPayload($ordersCollection = null)
    {
        $body = array();
        $merchantId = Mage::getStoreConfig('sezzle_sezzlepay/product_widget/merchant_id');
        if ($ordersCollection) {
            foreach ($ordersCollection as $orderObj) {
                $orderIncrementId = $orderObj->getIncrementId();
                $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
                $payment = $order->getPayment();
                $billing = $order->getBillingAddress();
                $orderForSezzle = array(
                    'order_number' => $orderIncrementId,
                    'payment_method' => $payment->getMethod() == 'sezzlepay' ? 'sezzlepay' : $payment->getMethod(),
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
                    'billing_country' => $billing->getCountry(),
                    'merchant_id' => $merchantId
                );
                array_push($body, $orderForSezzle);
            }
            return $body;
        }
    }
}