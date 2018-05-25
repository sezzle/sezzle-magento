<?php
class Sezzle_Sezzlepay_Model_WidgetWidth
{
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