<?php

/**
 * Sezzlepay widget width model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_System_Config_Source_Widgetwidth
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'thin',
                'label' => 'Thin',
            ),
            array(
                'value' => 'thick',
                'label' => 'Thick',
            ),
        );
    }
}