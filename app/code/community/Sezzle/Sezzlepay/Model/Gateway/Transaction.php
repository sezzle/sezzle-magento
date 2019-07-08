<?php

/**
 * Sezzlepay order to sezzle model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Gateway_Transaction
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
        $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
        $helper = $sezzlePaymentModel->helper();
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
            $url = $sezzlePaymentModel->getApiRouter()->getOrdersSubmitUrl();
            $body = $this->_buildOrderPayload($ordersCollection);
            $result = $this->getApiProcessor()->sendApiRequest(
                $url,
                $body,
                true,
                Varien_Http_Client::POST
            );
            $result = Mage::helper('core')->jsonDecode($result);
            if (isset($result['status']) && $result['status'] == Sezzle_Sezzlepay_Model_Api_Processor::BAD_REQUEST) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    __('Sezzle Pay API Error: %s', $result['message'])
                );
            }
            $helper->log('Order data sent to sezzle successfully');
        } catch (Exception $e) {
            $helper->log('Error while sending order to Sezzle' . $e->getMessage());
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
                    'amount' => round($order->getGrandTotal(), Sezzle_Sezzlepay_Model_Sezzlepay::PRECISION) * 100,
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

	/**
     * Api Processor
     *
     * @return Sezzle_Sezzlepay_Model_Api_Processor
     */
    protected function getApiProcessor()
    {
        return Mage::getModel('sezzle_sezzlepay/api_processor');
    }
}
