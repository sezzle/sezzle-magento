<?php

/**
 * Sezzlepay api processor
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Api_Processor
{
    const API_PUBLIC_KEY_CONFIG_PATH = 'payment/sezzlepay/public_key';
    const API_PRIVATE_KEY_CONFIG_PATH = 'payment/sezzlepay/private_key';

    /**
     * Api call to sezzle
     *
     * @param $url
     * @param $body
     * @param bool $isAuth
     * @param string $method
     * @return Zend_Http_Response
     * @throws Zend_Http_Client_Exception
     */
    public function sendApiRequest($url, $body, $isAuth = true, $method = Varien_Http_Client::GET)
    {
        $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/paymentmethod');
        Mage::log('Session : ' . $sezzlePaymentModel->getSessionID() . " Sending Request $url");
        $client = new Varien_Http_Client($url);
        $client->setConfig(array(
            'timeout' => 80
        ));
        if ($body !== false) {
            $client->setRawData(
                Mage::helper('core')->jsonEncode($body),
                'application/json');
        }
        if ($isAuth) {
            // Get the auth token
            $token = $this->getSezzleAuthToken();
            // set auth header
            $client->setHeaders('Authorization', "Bearer $token");
        }
        $response = $client->request($method);
        return $response;
    }

    /**
     * Get Sezzle auth token
     *
     * @return mixed
     * @throws Mage_Core_Exception
     */
    protected function getSezzleAuthToken()
    {
        try {
            $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/paymentmethod');
            $result = $this->_sendApiRequest(
                $sezzlePaymentModel->getApiRouter()->getAuthTokenUrl(),
                $this->_getSezzleAuthHeader(),
                false,
                Varien_Http_Client::POST
            );
            if ($result->isError()) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    __('Sezzle Pay API Error: %s', $result->getMessage())
                );
            }
            $resultJson = $result->getBody();
            $resultObject = Mage::helper('core')->jsonEncode($resultJson);
            $token = $resultObject['token'];
            if (empty($token)) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    "Sezzle Pay API Error: Cannot get auth token."
                );
            }
            return $token;
        } catch (Exception $e) {
            Mage::log(
                'Session : ' . $sezzlePaymentModel->getSessionID() . $e->getMessage(),
                Zend_Log::ERR
            );
        }
    }

    /**
     * Get Sezzle auth header
     *
     * @return array|null
     */
    protected function _getSezzleAuthHeader()
    {
        $merchantPublicKey = trim(Mage::getStoreConfig(self::API_PUBLIC_KEY_CONFIG_PATH));
        $merchantPrivateKey = trim(Mage::getStoreConfig(self::API_PRIVATE_KEY_CONFIG_PATH));
        if ($merchantPublicKey && $merchantPrivateKey) {
            return array(
                "public_key" => $merchantPublicKey,
                "private_key" => $merchantPrivateKey
            );
        }
        return null;
    }
}