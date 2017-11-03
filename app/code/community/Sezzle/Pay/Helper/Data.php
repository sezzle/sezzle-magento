<?php

class Sezzle_Pay_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $logFileName = 'sezzle-pay.log';

    /**
     * Get the current version of the Sezzle Pay extension
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig('Sezzle_Pay')->version;
    }

    public function log($message, $level = null)
    {
        Mage::log($message, $level, $this->logFileName);
    }
}