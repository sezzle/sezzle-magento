<?php
class Sezzle_Pay_Model_ApiMode
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