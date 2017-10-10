<?php

class Sezzle_Pay_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get the current version of the Sezzle Pay extension
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig('Sezzle_Pay')->version;
    }
}