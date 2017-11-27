<?php

class Sezzle_Pay_Block_ProductConfig extends Mage_Core_Block_Template
{
    public function getJsConfig() 
    {
        $targetXPath = explode('|', Mage::getStoreConfig('sezzle_pay/product_widget/xpath'));
        $renderToPath = explode('|', Mage::getStoreConfig('sezzle_pay/product_widget/renderXPath'));
        $forcedShow = Mage::getStoreConfig('sezzle_pay/product_widget/forced_show') == "1" ? true : false;
        $alignment = Mage::getStoreConfig('sezzle_pay/product_widget/alignment');
        $merchantID = Mage::getStoreConfig('sezzle_pay/product_widget/merchant_id');
        $theme = Mage::getStoreConfig('sezzle_pay/product_widget/theme');
        $widthType = Mage::getStoreConfig('sezzle_pay/product_widget/width-type');
        $imageUrl = Mage::getStoreConfig('sezzle_pay/product_widget/image-url');
        $hideClasses = explode('|', Mage::getStoreConfig('sezzle_pay/product_widget/hide-classes'));
        $minPrice = Mage::getStoreConfig('sezzle_pay/product_widget/minPrice');
        $maxPrice = Mage::getStoreConfig('sezzle_pay/product_widget/maxPrice');

        return array(
            'targetXPath'          => $targetXPath,
            'renderToPath'         => $renderToPath,
            'forcedShow'           => $forcedShow,
            'alignment'            => $alignment,
            'merchantID'           => $merchantID,
            'theme'                => $theme,
            'widthType'            => $widthType,
            'widgetType'           => 'product-page',
            'imageUrl'             => $imageUrl,
            'minPrice'             => $minPrice,
            'maxPrice'             => $maxPrice,
            'hideClasses'          => $hideClasses,
        );
    }
}
