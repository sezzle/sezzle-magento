<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition End User License Agreement
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magento.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Enterprise
 * @package     Enterprise_PageCache
 * @copyright Copyright (c) 2006-2017 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license http://www.magento.com/license/enterprise-edition
 */
/**
 * PageCache Data helper
 *
 * @category    Enterprise
 * @package     Enterprise_PageCache
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Enterprise_PageCache_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Character sets
     */
    const CHARS_LOWERS                          = 'abcdefghijklmnopqrstuvwxyz';
    const CHARS_UPPERS                          = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const CHARS_DIGITS                          = '0123456789';

    const COOKIE_CONFIG_FPC_KEY                 = 'COOKIE_CONFIG_FPC_KEY';
    const XML_PATH_USE_COOKIE_CONFIG            = 'system/page_cache/use_cookie_config';

    /**
     * Get random generated string
     *
     * @param int $len
     * @param string|null $chars
     * @return string
     */
    public static function getRandomString($len, $chars = null)
    {
        if (is_null($chars)) {
            $chars = self::CHARS_LOWERS . self::CHARS_UPPERS . self::CHARS_DIGITS;
        }
        for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }

    /**
     * Wrap string with placeholder wrapper
     *
     * @param string $string
     * @return string
     */
    public static function wrapPlaceholderString($string)
    {
        return '{{' . chr(1) . chr(2) . chr(3) . $string . chr(3) . chr(2) . chr(1) . '}}';
    }

    /**
     * Prepare content for saving
     *
     * @param string $content
     */
    public static function prepareContentPlaceholders(&$content)
    {
        /**
         * Replace all occurrences of session_id with unique marker
         */
        Enterprise_PageCache_Helper_Url::replaceSid($content);
        /**
         * Replace all occurrences of form_key with unique marker
         */
        Enterprise_PageCache_Helper_Form_Key::replaceFormKey($content);
    }

    /**
     * Check if the request is secure or not
     *
     * @return bool
     */
    public static function isSSL()
    {
        $isSSL           = false;
        $standardRule    = !empty($_SERVER['HTTPS']) && ('off' != $_SERVER['HTTPS']);
        $offloaderHeader = Enterprise_PageCache_Model_Cache::getCacheInstance()
            ->load(Enterprise_PageCache_Model_Processor::SSL_OFFLOADER_HEADER_KEY);
        $offloaderHeader = trim(@unserialize($offloaderHeader));

        if ((!empty($offloaderHeader) && !empty($_SERVER[$offloaderHeader])) || $standardRule) {
            $isSSL = true;
        }
        return $isSSL;
    }

    /**
     * Set cookie without app using cached cookie config
     *
     * @param string $name
     * @param string $value
     * @param int $period
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     */
    public static function setCookieWithoutApp(
        $name, $value, $period = null, $path = null, $domain = null, $secure = null, $httponly = null
    ) {
        $config = self::_getCookieConfig();
        if (isset($config[self::XML_PATH_USE_COOKIE_CONFIG]) && $config[self::XML_PATH_USE_COOKIE_CONFIG]) {
            if ($period === true) {
                $period = 3600 * 24 * 365;
            } elseif (is_null($period)) {
                $period = isset($config[Mage_Core_Model_Cookie::XML_PATH_COOKIE_LIFETIME])
                    ? $config[Mage_Core_Model_Cookie::XML_PATH_COOKIE_LIFETIME] : null;
            }
            if (is_null($path)) {
                $path = isset($config[Mage_Core_Model_Cookie::XML_PATH_COOKIE_PATH])
                    ? $config[Mage_Core_Model_Cookie::XML_PATH_COOKIE_PATH] : Mage::app()->getRequest()->getBasePath();
            }
            if (is_null($domain)) {
                $domain = isset($config[Mage_Core_Model_Cookie::XML_PATH_COOKIE_DOMAIN])
                    ? $config[Mage_Core_Model_Cookie::XML_PATH_COOKIE_DOMAIN]
                    : Mage::app()->getRequest()->getHttpHost();
            }
            if (is_null($secure)) {
                $secure = Mage::app()->getRequest()->isSecure();
            }
            if (is_null($httponly)) {
                $httponly = !empty($config[Mage_Core_Model_Cookie::XML_PATH_COOKIE_HTTPONLY])
                    ? (bool) $config[Mage_Core_Model_Cookie::XML_PATH_COOKIE_HTTPONLY] : null;
            }
        } else {
            $path = '/';
        }

        if ($period == 0) {
            $expire = 0;
        } else {
            $expire = time() + $period;
        }

        setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * Get cached cookie configuration for current store
     *
     * return array
     */
    protected static function _getCookieConfig()
    {
        $cacheInstance = Enterprise_PageCache_Model_Cache::getCacheInstance();
        $config = $cacheInstance->load(self::COOKIE_CONFIG_FPC_KEY);
        if (!$config) {
            return array();
        }

        $config = @unserialize($config);
        if (!is_array($config)) {
            return array();
        }

        if (isset($_COOKIE[Mage_Core_Model_Store::COOKIE_NAME])) {
            $storeIdentifier = $_COOKIE[Mage_Core_Model_Store::COOKIE_NAME];
        } else {
            $storeIdentifier = Mage::app()->getRequest()->getHttpHost() . Mage::app()->getRequest()->getBaseUrl();
        }
        if (!isset($config[$storeIdentifier])) {
            return array();
        }

        return $config[$storeIdentifier];
    }
}
