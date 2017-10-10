<?php
class Sezzle_Pay_Model_WidgetTheme
{
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