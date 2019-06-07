<?php

/**
 * Sezzlepay product config block
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Block_Widget_Productconfig extends Mage_Core_Block_Template
{

    const WIDGET_TYPE = 'product-page';

    /**
     * Create JS configurations for Sezzle widget
     *
     * @return array
     */
    public function getJsConfig()
    {
        $targetXPath = explode('|', Mage::getStoreConfig('sezzle_sezzlepay/product_page/xpath'));
        $renderToPath = explode('|', Mage::getStoreConfig('sezzle_sezzlepay/product_page/renderXPath'));
        $forcedShow = Mage::getStoreConfig('sezzle_sezzlepay/product_page/forced_show') == "1" ? true : false;
        $alignment = Mage::getStoreConfig('sezzle_sezzlepay/product_page/alignment');
        $merchantID = Mage::getStoreConfig('payment/sezzlepay/merchant_id');
        $theme = Mage::getStoreConfig('sezzle_sezzlepay/product_page/theme');
        $widthType = Mage::getStoreConfig('sezzle_sezzlepay/product_page/width-type');
        $imageUrl = Mage::getStoreConfig('sezzle_sezzlepay/product_page/image-url');
        $hideClasses = explode('|', Mage::getStoreConfig('sezzle_sezzlepay/product_page/hide-classes'));
        $minPrice = Mage::getStoreConfig('sezzle_sezzlepay/product_page/minPrice');
        $maxPrice = Mage::getStoreConfig('sezzle_sezzlepay/product_page/maxPrice');

        return array(
            'targetXPath' => $targetXPath,
            'renderToPath' => $renderToPath,
            'forcedShow' => $forcedShow,
            'alignment' => $alignment,
            'merchantID' => $merchantID,
            'theme' => $theme,
            'widthType' => $widthType,
            'widgetType' => self::WIDGET_TYPE,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'imageUrl' => $imageUrl,
            'hideClasses' => $hideClasses,
        );
    }
}
