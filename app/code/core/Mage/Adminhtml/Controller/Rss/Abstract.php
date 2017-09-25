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
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright Copyright (c) 2006-2017 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license http://www.magento.com/license/enterprise-edition
 */

/**
 * Adminhtml abstract Rss controller
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class Mage_Adminhtml_Controller_Rss_Abstract extends Mage_Adminhtml_Controller_Action
{
    /**
     * Check feed enabled in config
     *
     * @param string $code
     * @return boolean
     */
    protected function isFeedEnable($code)
    {
        return $this->_getHelper('rss')->isRssEnabled()
            && Mage::getStoreConfig('rss/'. $code);
    }

    /**
     * Do check feed enabled and prepare response
     *
     * @param string $code
     * @return boolean
     */
    protected function checkFeedEnable($code)
    {
        if ($this->isFeedEnable($code)) {
            $this->getResponse()->setHeader('Content-type', 'text/xml; charset=UTF-8');
            return true;
        } else {
            $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found');
            $this->getResponse()->setHeader('Status', '404 File not found');
            $this->_forward('noRoute');
            return false;
        }
    }

    /**
     * Retrieve helper instance
     *
     * @param string $name
     * @return Mage_Core_Helper_Abstract
     */
    protected function _getHelper($name)
    {
        return Mage::helper($name);
    }
}
