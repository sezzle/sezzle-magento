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
 * @category    OnTap
 * @package     OnTap_Merchandiser
 * @copyright Copyright (c) 2006-2017 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license http://www.magento.com/license/enterprise-edition
 */
class OnTap_Merchandiser_Model_Resource_Mysql4_Product_Collection
    extends Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
{
    /**
     * filterNotFindInSet
     *
     * @deprecated
     *
     * @param mixed $attribute
     * @param mixed $condition (default: null)
     * @param string $joinType (default: 'inner')
     * @return string
     */
    public function filterNotFindInSet($attribute, $condition = null, $joinType = 'inner')
    {
        return $this->_getNegativeAttributeConditionSql($attribute, $condition, $joinType);
    }

    /**
     * Retrieve negative attribute sql condition
     *
     * @param mixed $attribute
     * @param mixed $condition
     * @param string $joinType
     *
     * @return string
     */
    protected function _getNegativeAttributeConditionSql($attribute, $condition = null, $joinType = 'inner')
    {
        return 'NOT ' . $this->_getAttributeConditionSql($attribute, $condition, $joinType);
    }

    /**
     * Set negative attribute condition
     *
     * @param mixed $attribute
     * @param mixed $condition
     * @param string $joinType
     *
     * @return OnTap_Merchandiser_Model_Resource_Mysql4_Product_Collection
     */
    public function addNegativeCondition($attribute, $condition = null, $joinType = 'inner')
    {
        $this->getSelect()->where(
            $this->_getNegativeAttributeConditionSql($attribute, $condition, $joinType),
            null, Varien_Db_Select::TYPE_CONDITION);

        return $this;
    }

    /**
     * Retrieve all ids for collection sorted by field
     *
     * @param string $sortField
     * @param string $direction
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return array
     */
    public function getAllSortedIds($sortField, $direction = Zend_Db_Select::SQL_ASC, $limit = null, $offset = null)
    {
        $idsSelect = $this->_getClearSelect();
        $idsSelect->columns('e.' . $this->getEntity()->getIdFieldName());
        $idsSelect->limit($limit, $offset);
        $idsSelect->resetJoinLeft();

        $idsSelect->order($sortField . ' ' . $direction);

        return $this->getConnection()->fetchCol($idsSelect, $this->_bindParams);
    }
}
