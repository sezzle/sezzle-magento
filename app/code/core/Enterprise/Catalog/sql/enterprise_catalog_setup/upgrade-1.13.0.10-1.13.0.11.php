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
 * @package     Enterprise_Catalog
 * @copyright Copyright (c) 2006-2017 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license http://www.magento.com/license/enterprise-edition
 */

/** @var $this Mage_Core_Model_Resource_Setup */

$rows = $this->getConnection()->fetchAll(
    $this->getConnection()->select()
        ->from($this->getTable('core/config_data'))
        ->where('path IN (?)', array('catalog/seo/product_url_suffix','catalog/seo/category_url_suffix'))
        ->where('value <>  \'\'')
        ->where('value IS NOT NULL')
);

foreach ($rows as $row) {
    if (!preg_match('/[^a-zA-Z0-9_]/', $row['value'])) {
        $value = '.' . $row['value'];
        $this->setConfigData($row['path'], $value, $row['scope'], $row['scope_id']);
    }
}
