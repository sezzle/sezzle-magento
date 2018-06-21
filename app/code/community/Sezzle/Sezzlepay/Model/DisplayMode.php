<?php
class Sezzle_Sezzlepay_Model_DisplayMode
{
    public function toOptionArray() 
    {
        return array(
            array(
                'value' => 'redirect',
                'label' => 'Redirect',
            ),
            array(
                'value' => 'window',
                'label' => 'Window',
            ),
        );
    }
}