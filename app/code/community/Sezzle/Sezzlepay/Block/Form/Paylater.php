<?php

/**
 * Sezzlepay paylater block
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Block_Form_Paylater extends Mage_Payment_Block_Form
{
    /**
     * Set template and redirect message
     */
    protected function _construct()
    {
        $mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplateHelper($this);
        $mark->setTemplate('sezzlepay/form/paylater.phtml');
        $this->setMethodTitle('')
            ->setMethodLabelAfterHtml($mark->toHtml());
        return parent::_construct();
    }
}