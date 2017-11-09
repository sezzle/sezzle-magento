<?php

class Observer_Sezzle_Pay_Model_Observer
{
    public function SaveSezzleOrderReferenceID($observer) {
        $event = $observer->getEvent();

        $cid = $event->getCid();
        echo $cid;
        exit;
    }
}

?>