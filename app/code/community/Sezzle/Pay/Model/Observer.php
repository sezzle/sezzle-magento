<?php
class Sezzle_Pay_Model_Observer
{
    public function sendDailyData(Mage_Cron_Model_Schedule $schedule) {
        $today = date("Y-m-d H:i:s");
        $yesterday = date("Y-m-d H:i:s", strtotime("-1 days"));

        $yesterday = date('Y-m-d H:i:s', strtotime($yesterday));
        $today = date('Y-m-d H:i:s', strtotime($today));

        $orders = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('status',
                array(
                    'eq' => 'complete',
                    'eq' => 'processing'
                )
            )
            ->addAttributeToFilter('created_at',
                array(
                    'from' => $yesterday,
                    'to' => $today
                )
            )
            ->addAttributeToSelect('customer_email')
            ->addAttributeToSelect('status');
        foreach ($orders as $order) {
            $email = $order->getCustomerEmail();
            $status = $order->getStatus();
            $this->helper()->log("$email $status");
        }
    }

    protected function helper()
    {
        return Mage::helper('sezzle_pay');
    }
}