<?php

/**
 * Sezzlepay widget alignment model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_System_Config_Source_Widgetalignment
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'center',
                'label' => 'Center',
            ),
            array(
                'value' => 'right',
                'label' => 'Right',
            ),
            array(
                'value' => 'left',
                'label' => 'Left',
            ),
        );
    }
}