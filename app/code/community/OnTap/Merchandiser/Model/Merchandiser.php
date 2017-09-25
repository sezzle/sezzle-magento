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
class OnTap_Merchandiser_Model_Merchandiser
{
    /**
     * _data
     *
     * (default value: array())
     *
     * @var array
     */
    protected $_data = array();

    /**
     * Move products which are in stock to the top
     *
     * @param mixed $params
     * @return void
     */
    public function moveInStockToTheTop($params)
    {
        $catId = $params['catId'];
        $merchandiserResourceModel = $this->getResourceModel();
        $outStockProducts = $merchandiserResourceModel->getOutofStockProducts($catId);

        $maxPosition = $merchandiserResourceModel->getMaxInstockPositionFromCategory($catId);

        if (count($outStockProducts)) {
            foreach ($outStockProducts as $outStockProduct) {
                $outStockProductId = $outStockProduct['product_id'];
                $merchandiserResourceModel->updateProductPosition($catId, $outStockProductId, ++$maxPosition);
            }
        }
    }

    /**
     * Move saleable products to the top
     *
     * @param mixed $params
     * @return void
     */
    public function moveSaleAtTop($params)
    {
        $catId = $params['catId'];
        $merchandiserResourceModel = $this->getResourceModel();
        $readResult = $merchandiserResourceModel->getSaleCategoryProducts($catId, "DESC");
        $position = 1;
        foreach ($readResult as $row) {
            $merchandiserResourceModel->updateProductPosition($catId, $row['product_id'], $position);
            $position++;
        }
    }

    /**
     * Move saleable products to the bottom
     *
     * @param mixed $params
     * @return void
     */
    public function moveSaleAtBottom($params)
    {
        $catId = $params['catId'];
        $merchandiserResourceModel = $this->getResourceModel();
        $readResult = $merchandiserResourceModel->getSaleCategoryProducts($catId, "ASC");
        $position = 1;
        foreach ($readResult as $row) {
            $merchandiserResourceModel->updateProductPosition($catId, $row['product_id'], $position);
            $position++;
        }
    }

    /**
     * Affect category by smart rule
     *
     * @param mixed $categoryId
     * @return void
     */
    public function affectCategoryBySmartRule($categoryId)
    {
        $merchandiserResourceModel = $this->getResourceModel();
        $insertData = array();
        $allocatedProducts = array();
        $iCounter = 1;

        $categoryValues = $merchandiserResourceModel->getCategoryValues($categoryId);
        if ($categoryValues['smart_attributes'] == "") {
            $categoryValues['ruled_only'] = 0;
        }

        $categoryProductsResult = $merchandiserResourceModel->getCategoryProduct($categoryId);
        $positionsArray = array();
        foreach ($categoryProductsResult as $categoryProductPostions) {
            $positionsArray[$categoryProductPostions['product_id']] = $categoryProductPostions['position'];
        }
        asort($positionsArray);

        $merchandiserResourceModel->clearCategoryProducts($categoryId);

        $categoryProducts = array_map(
            array($this, 'categoryProductsMap'),
            $categoryProductsResult
        );

        $heroProducts = $categoryValues['heroproducts'];

        /** @var Mage_Catalog_Model_Product $productObject */
        $productObject = Mage::getModel('catalog/product');

        /**
         * Add products that are anchored to the very beginning or very end
         */
        foreach (explode(",", $heroProducts) as $heroSKU) {
            if ($heroSKU != '' && $productId = $productObject->getIdBySku(trim($heroSKU))) {
                if ($productId > 0) {
                    if (!in_array($productId, $allocatedProducts)) {
                        $allocatedProducts[] = $productId;
                        unset($positionsArray[$productId]);
                        $insertData[] = array(
                            'category_id' => $categoryId,
                            'product_id' => $productId,
                            'position' => $iCounter
                        );
                        $iCounter++;
                    }
                }
            }
        }

        $addTo = $this->getHelper()->newProductsHandler(); // 1= TOP , 2 = BOTTOM
        $addTo = $addTo < 1 ? 1 : $addTo;

        $categoryProducts = array_diff($categoryProducts, $allocatedProducts);
        $ruledProductIds = $this->getHelper()->smartFilter($categoryId, $categoryValues['smart_attributes']);
        $ruledProductCount = $iCounter;

        $allowNotMatching = $categoryValues['ruled_only'] == 0;

        /**
         * Add products that match rules
         */
        if (sizeof($ruledProductIds) > 0) {
            $normalProductCount = sizeof($positionsArray) > 0 ? max($positionsArray) : 0;
            $differenceFactor = $iCounter - $normalProductCount;
            if ($differenceFactor <= 0) {
                $differenceFactor = 1;
            }
            if ($addTo == 2 && $allowNotMatching) {
                 $ruledProductCount = $differenceFactor + $normalProductCount;
            }
            foreach ($ruledProductIds as $productId) {
                if (!in_array($productId, $allocatedProducts)) {
                    /**
                     * Skip products that used to be in a category, so that they are added in bulk later on.
                     * That preserves existing order and prevents newly matched products from getting in between.
                     */
                    if ($allowNotMatching && in_array($productId, $categoryProducts)) {
                        continue;
                    }
                    $allocatedProducts[] = $productId;
                    if ($addTo == 2) {
                        unset($positionsArray[$productId]);
                    }
                    $insertData[] = array(
                        'category_id' => $categoryId,
                        'product_id' => $productId,
                        'position' => $ruledProductCount
                    );
                    $ruledProductCount++;
                }
            }
        }

        if ($addTo == 1) {
            $iCounter = $ruledProductCount;
        }

        /**
         * Add products that used to belong to a category although they don't match rules.
         * These products either have been assigned manually or rules have changed and products no longer match.
         */
        if ($allowNotMatching) {
            if (sizeof($categoryProducts) > 0) {
                $incrementFactor = $iCounter - min($positionsArray);
                if ($incrementFactor < 0) {
                    $incrementFactor = 0;
                }
                foreach ($categoryProducts as $productId ) {
                    if (!in_array($productId, $allocatedProducts)) {
                        $allocatedProducts[] = $productId;
                        $currentPosition = ($positionsArray[$productId] > 0) ? $positionsArray[$productId] : 0;
                        $currentPosition += $incrementFactor;
                        $insertData[] = array(
                            'category_id' => $categoryId,
                            'product_id' => $productId,
                            'position' => $currentPosition
                        );
                    }
                }
            }
        }

        if (sizeof($insertData) > 0) {
            $merchandiserResourceModel->insertMultipleProductsToCategory($insertData);
        }
    }

    /**
     * Callback function for mapping category products
     *
     * @param mixed $value
     * @return string
     */
    public function categoryProductsMap($value)
    {
        if (is_array($value) && isset($value['product_id'])) {
            return $value['product_id'];
        }
    }

    /**
     * Get category values
     *
     * @param mixed $categoryId
     * @param mixed $field (default: null)
     * @return string
     */
    public function getCategoryValues($categoryId, $field = null)
    {
        return $this->getResourceModel()->getCategoryValues($categoryId, $field);
    }

    /**
     * Clear entity cache
     *
     * @param Mage_Core_Model_Abstract $entity
     * @param array $ids
     * @return void
     */
    public function clearEntityCache(Mage_Core_Model_Abstract $entity, array $ids)
    {
        $cacheTags = array();
        foreach ($ids as $entityId) {
            $entity->setId($entityId);
            $cacheTags = array_merge($cacheTags, $entity->getCacheIdTags());
        }
        if (!empty($cacheTags)) {
            Enterprise_PageCache_Model_Cache::getCacheInstance()->clean($cacheTags);
        }
    }

    /**
     * Arrange products in category
     *
     * @param array $params
     * @param string $resourceMethod
     * @return void
     */
    protected function arrangeProducts($params, $resourceMethod)
    {
        if (isset($params['catId']) && $params['catId'] > 0) {
            $catId = $params['catId'];
            $merchandiserResourceModel = $this->getResourceModel();
            $categoryProducts = $merchandiserResourceModel->getCategoryProduct($catId);

            if (count($categoryProducts) > 0) {
                $iCounter = 1;
                $allocatedProducts = array();

                $products = $merchandiserResourceModel->{$resourceMethod}($catId);

                foreach ($products as $product) {
                    $productId = $product['product_id'];
                    if (!in_array($productId, $allocatedProducts)) {
                        $merchandiserResourceModel->updateProductPosition($catId, $productId, $iCounter);
                        $allocatedProducts[] = $productId;
                        $iCounter++;
                    }
                }

                foreach ($categoryProducts as $catProduct) {
                     $productId = $catProduct['product_id'];
                     if (!in_array($productId, $allocatedProducts)) {
                        $merchandiserResourceModel->updateProductPosition($catId, $productId, $iCounter);
                        $allocatedProducts[] = $productId;
                        $iCounter++;
                    }
                }
            }
        }
    }

    /**
     * Move the most saleable products to the top
     *
     * @param array $params
     * @return void
     */
    public function moveBestsellersTop($params)
    {
        $this->arrangeProducts($params, 'getBestSellersProducts');
    }

    /**
     * Move the lowstock products to top
     *
     * @param array $params
     * @return void
     */
    public function moveLowstockTop($params)
    {
        $this->arrangeProducts($params, 'getLowStockProducts');
    }

    /**
     * Move newest products to top
     *
     * @param array $params
     * @return void
     */
    public function newestFirst($params)
    {
        $catId = $params['catId'];
        $merchandiserResourceModel = $this->getResourceModel();
        $categoryProducts = $merchandiserResourceModel->getCategoryProduct($catId, "product_id DESC");
        $position = 1;
        foreach ($categoryProducts as $product) {
            $merchandiserResourceModel->updateProductPosition($catId, $product['product_id'], $position);
            $position++;
        }
    }

    /**
     * Sort products by difference between price and cost
     *
     * @param array $params
     * @return void
     */
    public function highestMarginFirst($params)
    {
        $categoryId = $params['catId'];
        $resource = $this->getResourceModel();
        $productCollection = $resource->getProductsOrderedByMargin($categoryId);

        $position = 1;
        foreach ($productCollection as $product) {
            $resource->updateProductPosition($categoryId, $product->getId(), $position);
            $position++;
        }
    }

    /**
     * Sort products by color
     *
     * @param array $params
     * @return void
     */
    public function sortByColor($params)
    {
        $categoryId = $params['catId'];
        $resource = $this->getResourceModel();

        /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
        $productCollection = $resource->getProductsOrderedByColor($categoryId);

        $position = 1;
        /** @var Mage_Catalog_Model_Product $product */
        foreach ($productCollection as $product) {
            $resource->updateProductPosition($categoryId, $product->getId(), $position);
            if ($product->getColor()) {
                $position++;
            }
        }
    }

    /**
     * Get merchandiser helper
     *
     * @return OnTap_Merchandiser_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('merchandiser');
    }

    /**
     * Get merchandiser resource model
     *
     * @return OnTap_Merchandiser_Model_Resource_Merchandiser
     */
    public function getResourceModel()
    {
        return Mage::getResourceModel('merchandiser/merchandiser');
    }
}
