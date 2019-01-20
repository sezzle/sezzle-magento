<?php

/**
 * Sezzlepay helper
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var string
     */
    protected $_logFileName = 'sezzle-pay.log';

    /**
     * Get the current version of the Sezzle Pay extension
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return (string)Mage::getConfig()->getModuleConfig('Sezzle_Sezzlepay')->version;
    }

    /**
     * @param $message
     * @param null $level
     */
    public function log($message, $level = null)
    {
        Mage::log($message, $level, $this->_logFileName);
    }
}