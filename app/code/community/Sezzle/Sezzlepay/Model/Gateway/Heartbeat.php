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
        $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
        $helper = $sezzlePaymentModel->helper();
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
        try {
            if ($isPublicKeyEntered
                && $isPrivateKeyEntered
                && $isMerchantIdEntered) {
                $url = $this->getApiRouter()->getHeartbeatUrl();
                $result = $this->getApiProcessor()->sendApiRequest(
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
                $helper->log('Heartbeat sent to Sezzle');
            } else {
                $helper->log('Could not send Heartbeat to Sezzle. Please set api keys.');
            }
        }
        catch (Exception $e) {
            $helper->log(
                'Session Id: .'.$sezzlePaymentModel->getSessionId()."error while sending heartbeat to Sezzle".$e->getMessage(), Zend_Log::ERR);
        }
    }

    /**
     * Api Processor
     *
     * @return Sezzle_Sezzlepay_Model_Api_Processor
     */
    protected function getApiProcessor()
    {
        return Mage::getModel('sezzle_sezzlepay/api_processor');
    }
}