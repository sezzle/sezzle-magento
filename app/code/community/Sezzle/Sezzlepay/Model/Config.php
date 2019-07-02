<?php

/**
 * Sezzlepay Config
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Config
{

    const PAYMENT_ACTION_CONFIG_PATH = 'payment/sezzlepay/payment_action';

    public function getPaymentAction()
    {
        return Mage::getStoreConfig(self::PAYMENT_ACTION_CONFIG_PATH);
    }
}