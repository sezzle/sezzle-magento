<?php

/**
 * Sezzlepay observer
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Observer
{
    /**
     * Send daily transaction data to Sezzle
     *
     * @param Mage_Cron_Model_Schedule $schedule
     */
    public function sendDailyData(Mage_Cron_Model_Schedule $schedule)
    {
        $sezzleHeartbeatGateway = Mage::getModel('sezzle_sezzlepay/gateway_heartbeat');
        $sezzleOrderGateway = Mage::getModel('sezzle_sezzlepay/gateway_transaction');
        $sezzleOrderGateway->sendOrdersToSezzle();
        $sezzleHeartbeatGateway->sendHeartbeat();
    }
}