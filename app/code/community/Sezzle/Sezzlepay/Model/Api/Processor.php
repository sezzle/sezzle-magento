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
    const API_MERCHANT_ID_CONFIG_PATH = 'payment/sezzlepay/merchant_id';
    const CONTENT_TYPE_JSON = 'Content-Type:application/json';
    const BAD_REQUEST = 400;
    const TIMEOUT = 80;

    /**
     * Api call to sezzle
     *
     * @param $url
     * @param $body
     * @param bool $isAuth
     * @param string $method
     * @return string
     * @throws Mage_Core_Exception
     */
    public function sendApiRequest($url, $body = false, $isAuth = true, $method = Varien_Http_Client::GET)
    {
        $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
        $sezzlePaymentModel->helper()->log('Session : ' . $sezzlePaymentModel->getSessionID() . " Sending Request $url");
        $encodedBody = Mage::helper('core')->jsonEncode($body);
        $sezzlePaymentModel->helper()->log("Request Body");
        $sezzlePaymentModel->helper()->log($encodedBody);
        try {
            $http = new Varien_Http_Adapter_Curl();
            $config = array(
                'timeout' => self::TIMEOUT
            );
            $headers = array(self::CONTENT_TYPE_JSON);
            if ($isAuth) {
                // Get the auth token
                $authToken = $this->getSezzleAuthToken();
                // set auth header
                $headers[] = "Authorization:Bearer $authToken";
            }
            $http->setConfig($config);
            $http->write(
                $method,
                $url,
                '1.1',
                $headers,
                $encodedBody
            );
            $response = $http->read();
            $response = preg_split('/^\r?$/m', $response, 2);
            $response = trim($response[1]);
            $decodedBody = Mage::helper('core')->jsonDecode($response);
            $sezzlePaymentModel->helper()->log("Response Body");
            $sezzlePaymentModel->helper()->log($decodedBody);
            return $decodedBody;
        } catch (Exception $e) {
            throw Mage::exception(
                'Sezzle_Sezzlepay',
                "Sezzle Pay API Error: " . $e->getMessage()
            );
        }
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
            $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
            $helper = $sezzlePaymentModel->helper();
            $result = $this->sendApiRequest(
                $sezzlePaymentModel->getApiRouter()->getAuthTokenUrl(),
                $this->_getSezzleAuthHeader(),
                false,
                Varien_Http_Client::POST
            );
            if (isset($result['status']) && $result['status'] == self::BAD_REQUEST) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    __('Sezzle Pay API Error: %s', $result['message'])
                );
            }
            $token = isset($result['token']) ? $result['token'] : '';
            if (empty($token)) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    "Sezzle Pay API Error: Cannot get auth token."
                );
            }
            return $token;
        } catch (Exception $e) {
            $helper->log(
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
