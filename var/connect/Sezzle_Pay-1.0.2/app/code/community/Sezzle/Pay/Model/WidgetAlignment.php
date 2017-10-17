<?php
class Sezzle_Pay_Model_WidgetAlignment
{
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