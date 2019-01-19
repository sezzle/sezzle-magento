<?php

/**
 * Sezzlepay helper
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var string
     */
    protected $_logFileName = 'sezzle-pay.log';

    /**
     * Get the current version of the Sezzle Pay extension
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return (string)Mage::getConfig()->getModuleConfig('Sezzle_Sezzlepay')->version;
    }

    /**
     * Create invoice
     *
     * @param Mage_Sales_Model_Order $order
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function createInvoice(Mage_Sales_Model_Order $order)
    {
        if ($order->getId()) {
            if ($order->hasInvoices()) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    $this->__('Order already has invoice.'));
            }
            if (!$order->canInvoice()) {
                throw Mage::exception(
                    'Sezzle_Sezzlepay',
                    $this->__("Order can't be invoiced."));
            }
            $invoice = $order->prepareInvoice();
            if ($invoice->getTotalQty() > 0) {
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                if ($order->getPayment()->getLastTransId()) {
                    $invoice->setTransactionId($order->getPayment()->getLastTransId());
                }
                $invoice->register();
                $transaction = Mage::getModel('core/resource_transaction');
                $transaction->addObject($invoice)->addObject($invoice->getOrder());
                $transaction->save();
                $invoice->addComment(
                    $this->__('Sezzlepay automatic invoice.'),
                    false);
                // Send invoice email
                if (!$invoice->getEmailSent()) {
                    $invoice->sendEmail()->setEmailSent(true);
                }
                $invoice->save();
            }
        }
        return $this;
    }

    /**
     * Set store credit session
     *
     * @param $quote
     * @return mixed
     * @throws Mage_Core_Model_Store_Exception
     */
    public function setStoreCreditSession($quote)
    {
        $params = Mage::app()->getRequest()->getParams();
        $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
        if ($isLoggedIn && $quote->getCustomerBalanceAmountUsed()) {
            Mage::getSingleton('checkout/session')
                ->setData('sezzleCustomerBalance', $quote->getCustomerBalanceAmountUsed());
        } else if ($isLoggedIn &&
            !empty($params) &&
            !empty($params["payment"]) &&
            isset($params["payment"]["use_customer_balance"]) &&
            $params["payment"]["use_customer_balance"]
        ) {
            // Handler for Default One Page Checkout
            $customerId = Mage::getSingleton('customer/session')->getId();
            $websiteId = Mage::app()->getStore()->getWebsiteId();
            $balance = Mage::getModel('enterprise_customerbalance/balance')
                ->setCustomerId($customerId)
                ->setWebsiteId($websiteId)
                ->loadByCustomer();
            $quote->setUseCustomerBalance(1);
            $quote->setCustomerBalanceAmountUsed($balance->getAmount());
            $grandTotal = $quote->getGrandTotal();
            $quote->setGrandTotal($grandTotal - $balance->getAmount());
            $quote->save();
            Mage::getSingleton('checkout/session')->setData('sezzleCustomerBalance', $balance->getAmount());
        }
        Mage::getSingleton('checkout/session')->setData('sezzleGrandTotal', $quote->getGrandTotal());
        Mage::getSingleton('checkout/session')->setData('sezzleSubtotal', $quote->getSubtotal());
        return $quote;
    }

    /**
     * Unset credit session data
     */
    public function unsetStoreCreditSession()
    {
        if (Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance')) {
            Mage::getSingleton('checkout/session')->unsetData('sezzleCustomerBalance');
        }
        if (Mage::getSingleton('checkout/session')->getData('sezzleGrandTotal')) {
            Mage::getSingleton('checkout/session')->unsetData('sezzleGrandTotal');
        }
        if (Mage::getSingleton('checkout/session')->getData('sezzleSubtotal')) {
            Mage::getSingleton('checkout/session')->unsetData('sezzleSubtotal');
        }
    }

    /**
     * Capturing store credit
     *
     * @param $quote
     * @return mixed
     */
    public function storeCreditCapture($quote)
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn() &&
            Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance')
        ) {
            $grandTotal = Mage::getSingleton('checkout/session')->getData('sezzleGrandTotal');
            $subtotal = Mage::getSingleton('checkout/session')->getData('sezzleSubtotal');
            $balance = Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance');
            $quote->setUseCustomerBalance(1);
            $quote->setCustomerBalanceAmountUsed($balance);
            $quote->setBaseCustomerBalanceAmountUsed($balance);
            if ($quote->getSubtotal() == $subtotal) {
                $quote->setGrandTotal($grandTotal)->save();
            }
            $this->log($this->__('Store Credit being used: ' . $balance . ", Grand Total: " . $grandTotal));
            return $quote;
        }
        return $quote;
    }

    /**
     * Set gift cards session
     *
     * @param $quote
     * @return mixed
     */
    public function setGiftCardsSession($quote)
    {
        if ($quote->getGiftCardsAmountUsed()) {
            Mage::getSingleton('checkout/session')->setData('sezzleGiftCards', $quote->getGiftCards());
            Mage::getSingleton('checkout/session')->setData('sezzleGiftCardsAmount', $quote->getGiftCardsAmountUsed());
        }
        return $quote;
    }

    /**
     * Unset gift cards session
     */
    public function unsetGiftCardsSession()
    {
        if (Mage::getSingleton('checkout/session')->getData('sezzleGiftCards')) {
            Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCards');
            Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCardsAmount');
        }
    }

    /**
     * Capture gift cards
     *
     * @param $quote
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function giftCardsCapture($quote)
    {
        $balance = Mage::getSingleton('checkout/session')->getData('sezzleGiftCardsAmount');
        $giftCards = Mage::getSingleton('checkout/session')->getData('sezzleGiftCards');
        try {
            if (!empty($balance) && $balance > 0) {
                $quote->setGiftCardsAmountUsed($balance);
                $quote->setGiftCards($giftCards);
                //deduct the gift card
                $giftCardsData = unserialize($giftCards);
                $giftCardsAccount = Mage::getModel('enterprise_giftcardaccount/giftcardaccount')
                    ->loadByCode($giftCardsData[0]['c']);
                if (!$giftCardsAccount->getId()) {
                    Mage::throwException('Gift Card Code Not Found');
                } else {
                    if (!empty($giftCardsAccount)
                        && $giftCardsAccount->getGiftCardsAmount() >= $balance) {
                        $giftCardsNewAmount = $balance;
                        $giftCardsAccount->charge($giftCardsNewAmount);
                        $giftCardsAccount->save();
                        $this->log($this->__('Gift Cards used: ' . $giftCards . ' Amount being used: ' . $balance));
                    } else {
                        $this->log($this->__('Gift Cards used: ' . $giftCards . ' Amount is deducted already'));
                    }
                    Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCards');
                    Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCardsAmount');
                }
                return $quote;
            }
        } catch (Exception $exception) {
            $this->log(
                $this->__(
                    'Error capturing gift cards. %s.', $exception->getMessage(),
                    Zend_Log::ERR
                )
            );
        }
        return $quote;
    }

    /**
     * @param $message
     * @param null $level
     */
    public function log($message, $level = null)
    {
        Mage::log($message, $level, $this->_logFileName);
    }

    /**
     * Order placement with store credit
     *
     * @return bool
     */
    public function storeCreditPlaceOrder()
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()
            && Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance')
        ) {
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

    /**
     * Customer balance deduction fallback
     *
     * @param $orderId
     * @param $balanceUsed
     * @throws Mage_Core_Exception
     */
    public function customerBalanceDeductionFallback($orderId, $balanceUsed)
    {
        // Get the first customer in the store's ID
        $customerId = Mage::getSingleton('customer/session')->getId();
        $balance = Mage::getModel('enterprise_customerbalance/balance')
            ->setCustomerId($customerId)
            ->setWebsiteId(Mage::app()->getWebsite()->getId($orderId))
            ->loadByCustomer();
        if ($balance->getAmount() > 0) {
            //safeguard against a possibility of minus balance
            $balance->setAmountDelta(-1 * $balanceUsed)
                ->setUpdatedActionAdditionalInfo("Order #" . $orderId);
            $this->log(
                "Customer Balance deduction fallback engaged. Order: "
                . $orderId
                . " Balance Delta: "
                . $balanceUsed
            );
            $balance->save();
        }
    }

    /**
     * Order placement with gift cards
     *
     * @return bool
     */
    public function giftCardsPlaceOrder()
    {
        if (Mage::getSingleton('checkout/session')->getData('sezzleGiftCards')) {
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
            $giftCards = Mage::getSingleton('checkout/session')->getData('sezzleGiftCards');
            $balanceUsed = Mage::getSingleton('checkout/session')->getData('sezzleGiftCardsAmount');
            $order->setGiftCards($giftCards);
            $order->setGiftCardsAmount($balanceUsed);
            $order->setGiftCardsInvoiced($balanceUsed);
            $order->save();
            Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCards');
            Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCardsAmount');
        }
        return true;
    }
}