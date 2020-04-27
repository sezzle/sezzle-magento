<?php

/**
 * Sezzlepay product config block
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Block_Widget extends Mage_Core_Block_Template
{

    /**
     * Get Widget allow status for PDP
     *
     * @return bool
     */
    public function isWidgetAllowedForPDP()
    {
        return Mage::getStoreConfig('payment/sezzlepay/widget_pdp');
    }

    /**
     * Get Widget allow status for Cart Page
     *
     * @return bool
     */
    public function isWidgetAllowedForCartPage()
    {
        return Mage::getStoreConfig('payment/sezzlepay/widget_cart');
    }

    /**
     * Get merchant id
     *
     * @return string
     */
    public function getMerchantID()
    {
        return Mage::getStoreConfig('payment/sezzlepay/merchant_id');
    }
}
