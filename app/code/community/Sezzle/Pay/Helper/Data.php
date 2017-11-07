<?php

class Sezzle_Pay_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $logFileName = 'sezzle-pay.log';

    /**
     * Get the current version of the Sezzle Pay extension
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig('Sezzle_Pay')->version;
    }

    public function log($message, $level = null)
    {
        Mage::log($message, $level, $this->logFileName);
    }

    public function createInvoice(Mage_Sales_Model_Order $order)
    {
        $paymentMethod = $order->getPayment()->getMethodInstance();

        if ($order->getId()) {
            if ($order->hasInvoices()) {
                throw Mage::exception('Sezzle_Pay', $this->__('Order already has invoice.'));
            }

            if (!$order->canInvoice()) {
                throw Mage::exception('Sezzle_Pay', $this->__("Order can't be invoiced."));
            }

            $invoice = $order->prepareInvoice();

            if ($invoice->getTotalQty() > 0) {
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);

                if ($order->getPayment()->getLastTransId()) {
                    $invoice->setTransactionId($order->getPayment()->getLastTransId());
                }

                $invoice->register();
                $transaction = Mage::getModel('core/resource_transaction');

                $transaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());

                $transaction->save();

                $invoice->addComment($this->__('Sezzlepay Automatic invoice.'), false);

                // Send invoice email
                if (!$invoice->getEmailSent()) {
                    $invoice->sendEmail()->setEmailSent(true);
                }

                $invoice->save();
            }
        }

        return $this;
    }

    public function storeCreditSessionSet($quote) 
    {
        // Utilise Magento Session to preserve Store Credit details
    
        $params = Mage::app()->getRequest()->getParams();
        
        if(Mage::getSingleton('customer/session')->isLoggedIn() && $quote->getCustomerBalanceAmountUsed()) {
            Mage::getSingleton('checkout/session')->setData('sezzleCustomerBalance', $quote->getCustomerBalanceAmountUsed());
        }
        else if(Mage::getSingleton('customer/session')->isLoggedIn() && !empty($params) && !empty($params["payment"]) && isset($params["payment"]["use_customer_balance"]) && $params["payment"]["use_customer_balance"]) {
            // Handler for Default One Page Checkout
            $customerId = Mage::getSingleton('customer/session')->getId();
            $website_id = Mage::app()->getStore()->getWebsiteId(); 

            $balance = Mage::getModel('enterprise_customerbalance/balance')
                    ->setCustomerId($customerId)
                    ->setWebsiteId($website_id)
                    ->loadByCustomer();
            
            $quote->setUseCustomerBalance(1);
            $quote->setCustomerBalanceAmountUsed($balance->getAmount());
            
            $grand_total = $quote->getGrandTotal();
            $quote->setGrandTotal($grand_total - $balance->getAmount());
            
            $quote->save();
        
            Mage::getSingleton('checkout/session')->setData('sezzleCustomerBalance', $balance->getAmount());
        }

        Mage::getSingleton('checkout/session')->setData('sezzleGrandTotal', $quote->getGrandTotal());
        Mage::getSingleton('checkout/session')->setData('sezzleSubtotal', $quote->getSubtotal());
        return $quote;
    }

    public function storeCreditSessionUnset() 
    {
        if(Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance')) {
            Mage::getSingleton('checkout/session')->unsetData('sezzleCustomerBalance');
        }
    
        if(Mage::getSingleton('checkout/session')->getData('sezzleGrandTotal')) {
            Mage::getSingleton('checkout/session')->unsetData('sezzleGrandTotal');
        }
    
        if(Mage::getSingleton('checkout/session')->getData('sezzleSubtotal')) {
            Mage::getSingleton('checkout/session')->unsetData('sezzleSubtotal');
        }
    }

    public function storeCreditCapture($quote) 
    {
        if(Mage::getSingleton('customer/session')->isLoggedIn() && Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance')) {
            //$grand_total = $quote->getGrandTotal();
            $grand_total = Mage::getSingleton('checkout/session')->getData('sezzleGrandTotal');
            $subtotal = Mage::getSingleton('checkout/session')->getData('sezzleSubtotal');
            $balance = Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance');
            
            $quote->setUseCustomerBalance(1);
                
            $quote->setCustomerBalanceAmountUsed($balance);
                $quote->setBaseCustomerBalanceAmountUsed($balance);
                
            if($quote->getSubtotal() == $subtotal) {
                $quote->setGrandTotal($grand_total)->save();
            }

            
            $this->log($this->__('Store Credit being used: ' . $balance . ", Grand Total: " . $grand_total));
        
            return $quote;
        }

        return $quote;
    }

    public function giftCardsSessionSet($quote) 
    {
        if($quote->getGiftCardsAmountUsed()) {
            Mage::getSingleton('checkout/session')->setData('sezzleGiftCards', $quote->getGiftCards());
            Mage::getSingleton('checkout/session')->setData('sezzleGiftCardsAmount', $quote->getGiftCardsAmountUsed());
        }
  
        return $quote;
    }

    public function giftCardsSessionUnset() 
    {
        if(Mage::getSingleton('checkout/session')->getData('sezzleGiftCards')) {
            Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCards');
            Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCardsAmount');
        }
    }

    public function giftCardsCapture($quote) 
    {
        
        $balance = Mage::getSingleton('checkout/session')->getData('sezzleGiftCardsAmount');
        $gift_cards = Mage::getSingleton('checkout/session')->getData('sezzleGiftCards');
        
        if(!empty($balance) && $balance > 0) {
        $grand_total = $quote->getGrandTotal();
            
        $quote->setGiftCardsAmountUsed($balance);
        $quote->setGiftCards($gift_cards);
        
        //deduct the gift card
        $gift_cards_data = unserialize($gift_cards);
        $gift_cards_account = Mage::getModel('enterprise_giftcardaccount/giftcardaccount')
                ->loadByCode($gift_cards_data[0]['c']);
            
        if (!$gift_cards_account->getId()) {
            Mage::throwException('Gift Card Code Not Found');
        }
        else {
        if(!empty($gift_card_account) && $gift_card_account->getGiftCardsAmount() >= $balance) {
            $gift_cards_new_amount = $balance;
            $gift_cards_account->charge($gift_cards_new_amount);
            $gift_cards_account->save();
            
            $this->log($this->__('Gift Cards used: ' . $gift_cards  . ' Amount being used: ' . $balance));
        }
        else {
            $this->log($this->__('Gift Cards used: ' . $gift_cards  . ' Amount is deducted already'));
        }
              
        Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCards');
        Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCardsAmount');
        }

            return $quote;
        }

        return $quote;
    }

    public function storeCreditPlaceOrder()
    {
        if(Mage::getSingleton('customer/session')->isLoggedIn() && Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance')) {
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);

            $balanceUsed = Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance');

            $order->setCustomerBalanceAmount($balanceUsed);
            $order->setBaseCustomerBalanceAmount($balanceUsed);
            $order->setCustomerBalanceInvoiced($balanceUsed);
            $order->setBaseCustomerBalanceInvoiced($balanceUsed);
            $order->setTotalPaid($order->getGrandTotal());  
        
            $order->save();

            $this->customerBalanceDeductionFallback($orderId, $balanceUsed);
                    
            Mage::getSingleton('checkout/session')->unsetData('sezzleCustomerBalance');
        }

        return true;
    }

    public function customerBalanceDeductionFallback( $orderId, $balanceUsed ) 
    {
        // Get the first customer in the store's ID
        $customerId = Mage::getSingleton('customer/session')->getId();

        $balance = Mage::getModel('enterprise_customerbalance/balance')
                ->setCustomerId($customerId)
                ->setWebsiteId(Mage::app()->getWebsite()->getId($orderId))
                ->loadByCustomer();

        if($balance->getAmount() > 0) {
            //safeguard against a possibility of minus balance
            $balance->setAmountDelta(-1 * $balanceUsed)
                    ->setUpdatedActionAdditionalInfo("Order #" . $orderId);
            
            $this->log("Customer Balance deduction fallback engaged. Order: " . $orderId . " Balance Delta: " . $balanceUsed);
            $balance->save();
        }
    }

    public function giftCardsPlaceOrder()
    {
        if(Mage::getSingleton('checkout/session')->getData('sezzleGiftCards')) {
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);

            $gift_cards = Mage::getSingleton('checkout/session')->getData('sezzleGiftCards');
            $balance_used = Mage::getSingleton('checkout/session')->getData('sezzleGiftCardsAmount');

            $order->setGiftCards($gift_cards);
            $order->setGiftCardsAmount($balance_used);
            $order->setGiftCardsInvoiced($balance_used);
           
            $order->save();
                    
            Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCards');
            Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCardsAmount');
        }

        return true;
    }
}