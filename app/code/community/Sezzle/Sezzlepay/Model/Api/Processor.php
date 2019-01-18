<?php
/**
 * Created by PhpStorm.
 * User: arijit
 * Date: 1/19/2019
 * Time: 12:59 AM
 */

class Sezzle_Sezzlepay_Model_Api_Processor
{
    public function sendApiRequest($url, $body, $isAuth = true, $method = Varien_Http_Client::GET)
    {
        Mage::log('Session : ' . $this->getSessionID() . " Sending Request $url");
        $client = new Varien_Http_Client($url);
        $client->setConfig(array(
            'timeout'   => 80
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
}