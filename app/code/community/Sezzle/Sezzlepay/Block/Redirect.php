<?php

class Sezzle_Sezzlepay_Block_Redirect extends Mage_Core_Block_Template
{
    public function getRedirectJsUrl() {
        $redirectUrl = Mage::getModel('sezzle_sezzlepay/api_router')->getCheckoutJsUrl();
        return $redirectUrl;
    }
}
