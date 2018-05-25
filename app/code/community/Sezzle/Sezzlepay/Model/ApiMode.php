<?php
class Sezzle_Sezzlepay_Model_ApiMode
{
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