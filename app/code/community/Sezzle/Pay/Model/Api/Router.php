<?php

/**
 * Used for creating routes based on selected config
 */
class Sezzle_Pay_Model_Api_Router
{

    const API_MODE_STAGING = 'staging';
    const API_MODE_LOCAL   = 'local';
    const API_MODE_LIVE = 'live';
    const API_MODE_SANDBOX = 'sandbox';

    // Returns the authentication token url
    public function getAuthTokenUrl() 
    {
        return $this->getBaseApiUrl() . '/v1/authentication';
    }

    public function checkoutRefundUrl($reference) 
    {
        return $this->getBaseApiUrl() . '/v1/orders' . '/' . $reference . '/refund';
    }

    public function checkoutCompleteUrl($reference) 
    {
        return $this->getBaseApiUrl() . '/v1/checkouts' . '/' . $reference . '/complete';
    }

    public function getSubmitCheckoutDetailsAndGetRedirectUrl() 
    {
        return $this->getBaseApiUrl() . '/v1/checkouts';
    }

    public function getOrdersSubmitUrl()
    {
        return $this->getBaseApiUrl() . '/v1/merchant_data' . '/magento/merchant_orders';
    }

    // Returns base api url
    protected function getBaseApiUrl() 
    {
        $apiMode      = Mage::getStoreConfig(
            'payment/pay/' . Sezzle_Pay_Model_PaymentMethod::API_MODE_CONFIG_FIELD
        );
        $overrideBaseUrl = Mage::getStoreConfig(
            'payment/pay/' . Sezzle_Pay_Model_PaymentMethod::API_BASE_URL_CONFIG_FIELD
        );
        if ($overrideBaseUrl) {
            return $this->removeSlashFromUrl($overrideBaseUrl);
        }

        switch ($apiMode) {
            case self::API_MODE_STAGING:
                return 'https://staging.gateway.sezzle.com';
            case self::API_MODE_SANDBOX:
                return 'https://sandbox.gateway.sezzle.com';
            case self:API_MODE_LOCAL:
                return 'http://127.0.0.1:9002';
            case self:API_MODE_LIVE:
                return 'https://gateway.sezzle.com';
            default:
                return 'https://gateway.sezzle.com';
        }
    }

    // Removes / from end of url if present
    protected function removeSlashFromUrl($url) 
    {
        if (substr($url, -1) == '/') {
            $url = substr($string, 0, -1);
        }

        return $url;
    }
}