<?php

/**
 * Sezzlepay heartbeat model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Gateway_Heartbeat
{

    /**
     * Send payment & widget status to sezzle
     *
     * @throws Mage_Core_Exception
     */
    public function sendHeartbeat()
    {
        $isPublicKeyEntered = strlen(Mage::getStoreConfig('payment/sezzlepay/public_key')) > 0 ? true : false;
        $isPrivateKeyEntered = strlen(Mage::getStoreConfig('payment/sezzlepay/private_key')) > 0 ? true : false;
        $isWidgetConfigured = strlen(explode('|', Mage::getStoreConfig('sezzle_sezzlepay/product_widget/xpath'))[0]) > 0 ? true : false;
        $isMerchantIdEntered = strlen(Mage::getStoreConfig('sezzle_sezzlepay/product_widget/merchant_id')) > 0 ? true : false;
        $isPaymentActive = Mage::getStoreConfig('payment/sezzlepay/active') == 1 ? true : false;
        $merchantId = Mage::getStoreConfig('sezzle_sezzlepay/product_widget/merchant_id');
        $body = array(
            'is_payment_active' => $isPaymentActive,
            'is_widget_active' => true,
            'is_widget_configured' => $isWidgetConfigured,
            'is_merchant_id_entered' => $isMerchantIdEntered,
            'merchant_id' => $merchantId
        );
        if ($isPublicKeyEntered
            && $isPrivateKeyEntered
            && $isMerchantIdEntered) {
            $url = $this->getApiRouter()->getHeartbeatUrl();
            $result = $this->getSezzleBaseModel()->_sendApiRequest(
                $url,
                $body,
                true,
                Varien_Http_Client::POST
            );
            if ($result->isError()) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    __('Sezzle Pay API Error: %s', $result->getMessage())
                );
            }
            $this->helper()->log('Heartbeat sent to Sezzle');
        } else {
            $this->helper()->log('Could not send Heartbeat to Sezzle. Please set api keys.');
        }
    }
}