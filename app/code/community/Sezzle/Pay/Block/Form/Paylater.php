<?php

class Sezzle_Pay_Block_Form_Paylater extends Mage_Payment_Block_Form
{
    
    protected function _construct()
    {
        parent::_construct();
        $block = Mage::getConfig()->getBlockClassName('core/template');
        $block = new $block;
        $block->setTemplateHelper($this);
        $block->setTemplate('pay/form/paylater.phtml');
        $this->setMethodTitle('')->setMethodLabelAfterHtml($block->toHtml());
    }
}