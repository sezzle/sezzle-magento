<?php

class Sezzle_Pay_Block_ProductConfig extends Mage_Core_Block_Template
{
    public function getJsConfig() 
    {
        $targetXPath = Mage::getStoreConfig('sezzle_pay/product_widget/xpath');
        $forcedShow = Mage::getStoreConfig('sezzle_pay/product_widget/forced_show') == "1" ? true : false;
        $alignment = Mage::getStoreConfig('sezzle_pay/product_widget/alignment');
        $merchantID = Mage::getStoreConfig('sezzle_pay/product_widget/merchant_id');
        $theme = Mage::getStoreConfig('sezzle_pay/product_widget/theme');
        $widthType = Mage::getStoreConfig('sezzle_pay/product_widget/width-type');
        $imageUrl = Mage::getStoreConfig('sezzle_pay/product_widget/image-url');

        return array(
            'targetXPath'          => $targetXPath,
            'forcedShow'           => $forcedShow,
            'alignment'            => $alignment,
            'merchantID'           => $merchantID,
            'theme'                => $theme,
            'widthType'            => $widthType,
            'widgetType'           => 'product-page',
            'minPrice'             => 0,
            'maxPrice'             => 100000,
            'imageUrl'             => $imageUrl
        );
    }
}
