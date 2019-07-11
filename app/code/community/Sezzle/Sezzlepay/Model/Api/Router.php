<?php

/**
 * Sezzlepay api router
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Api_Router
{

    const API_MODE_STAGING = 'staging';
    const API_MODE_LOCAL = 'local';
    const API_MODE_LIVE = 'live';
    const API_MODE_SANDBOX = 'sandbox';

    /**
     * Get auth token url
     *
     * @return string
     */
    public function getAuthTokenUrl()
    {
        return $this->getBaseApiUrl() . '/v1/authentication';
    }

    /**
     * Get checkout refund url
     *
     * @param $reference
     * @return string
     */
    public function getCheckoutRefundUrl($reference)
    {
        return $this->getBaseApiUrl() . '/v1/orders' . '/' . $reference . '/refund';
    }

    /**
     * Get checkout details url
     *
     * @param $reference
     * @return string
     */
    public function getCheckoutDetailsUrl($reference)
    {
        return $this->getBaseApiUrl() . '/v1/orders' . '/' . $reference;
    }


    /**
     * Get checkout complete url
     *
     * @param $reference
     * @return string
     */
    public function getCheckoutCompleteUrl($reference)
    {
        return $this->getBaseApiUrl() . '/v1/checkouts' . '/' . $reference . '/complete';
    }

    /**
     * Get submit checkout details and get redirecturl
     *
     * @return string
     */
    public function getSubmitCheckoutDetailsAndGetRedirectUrl()
    {
        return $this->getBaseApiUrl() . '/v1/checkouts';
    }

    /**
     * Get orders submit url
     *
     * @return string
     */
    public function getOrdersSubmitUrl()
    {
        return $this->getBaseApiUrl() . '/v1/merchant_data' . '/magento/merchant_orders';
    }

    /**
     * Get heartbeat url
     *
     * @return string
     */
    public function getHeartbeatUrl()
    {
        return $this->getBaseApiUrl() . '/v1/merchant_data' . '/magento/heartbeat';
    }

    /**
     * Get send log url
     *
     * @param $merchant_id
     * @return string
     */
    public function getSendLogsUrl($merchantId)
    {
        return $this->getBaseApiUrl() . '/v1/logs/' . $merchantId;
    }

    /**
     * Get base api url
     *
     * @return bool|string
     */
    protected function getBaseApiUrl()
    {
        $apiMode = Mage::getStoreConfig(
            'payment/sezzlepay/' . Sezzle_Sezzlepay_Model_Sezzlepay::API_MODE_CONFIG_FIELD
        );
        switch ($apiMode) {
            case self::API_MODE_STAGING:
                return 'https://staging.gateway.sezzle.com';
            case self::API_MODE_SANDBOX:
                return 'https://sandbox.gateway.sezzle.com';
            case self::API_MODE_LOCAL:
                return 'http://127.0.0.1:9002';
            case self::API_MODE_LIVE:
                return 'https://gateway.sezzle.com';
            default:
                return 'https://gateway.sezzle.com';
        }
    }
}
