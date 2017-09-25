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
 * @package     Enterprise_UrlRewrite
 * @copyright Copyright (c) 2006-2017 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license http://www.magento.com/license/enterprise-edition
 */

/**
 * Category url rewrite helper
 *
 * @category    Enterprise
 * @package     Enterprise_UrlRewrite
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Enterprise_UrlRewrite_Helper_Core_Url_Rewrite
    extends Mage_Core_Helper_Abstract
{
    /**
     * Validate request path.
     * If something is wrong with a path it throws localized error message.
     *
     * @param $requestPath
     * @return bool
     * @throws Exception
     */
    protected function _validate($requestPath)
    {
        if (preg_match('/^(\.[0-9a-zA-Z_]+)|^\/$|^$/', $requestPath) == false) {
            throw new Exception(
                $this->__('"URL Suffix" must be either: (1) blank or (2) a forward slash "/" or (3) a ' .
                    'dot "." followed by alphanumeric characters or underscores "_"')
            );
        }
        return true;
    }

    /**
     * Validates request path
     * Either returns TRUE (success) or throws error (validation failed)
     *
     * @param string $requestPath
     * @return bool
     */
    public function validateRequestPath($requestPath)
    {
        return $this->_validate($requestPath);
    }

    /**
     * Validates suffix for url rewrites to inform user about errors in it
     * Either returns TRUE (success) or throws error (validation failed)
     *
     * @param string $suffix
     * @return bool
     */
    public function validateSuffix($suffix)
    {
        return $this->_validate($suffix); // Suffix itself must be a valid request path
    }
}
