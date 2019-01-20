<?php

/**
 * Sezzlepay Apimode model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_System_Config_Source_Apimode
{
    /**
     * Return API mode
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'live',
                'label' => 'Live',
            ),
            array(
                'value' => 'sandbox',
                'label' => 'Sandbox/Test',
            ),
        );
    }
}