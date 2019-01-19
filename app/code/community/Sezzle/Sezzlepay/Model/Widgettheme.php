<?php

/**
 * Sezzlepay widget theme model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Widgettheme
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'light',
                'label' => 'Light',
            ),
            array(
                'value' => 'dark',
                'label' => 'Dark',
            ),
        );
    }
}