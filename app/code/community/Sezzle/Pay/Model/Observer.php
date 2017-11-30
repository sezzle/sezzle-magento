<?php
class Sezzle_Pay_Model_Observer
{
    public function sendDailyData(Mage_Cron_Model_Schedule $schedule) {
        $this->helper()->log("I ran");
    }

    protected function helper()
    {
        return Mage::helper('sezzle_pay');
    }
}