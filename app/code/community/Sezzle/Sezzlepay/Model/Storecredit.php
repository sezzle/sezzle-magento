<?php

/**
 * Sezzlepay Store credit model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Storecredit
{
    const ENTERPRISE_STORE_CREDIT = "Enterprise";
    const AMASTY_STORE_CREDIT = "Amasty_StoreCredit";

    /**
     * Set store credit session
     *
     * @param $quote
     * @param $module
     *
     */
    public function setStoreCreditSession($quote, $module)
    {
        try {
            $params = Mage::app()->getRequest()->getParams();
            $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
            $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
            $helper = $sezzlePaymentModel->helper();
            if ($isLoggedIn) {
                switch ($module) {
                    case self::ENTERPRISE_STORE_CREDIT:
                        if ($quote->getCustomerBalanceAmountUsed()) {
                            Mage::getSingleton('checkout/session')
                                ->setData('sezzleCustomerBalance', $quote->getCustomerBalanceAmountUsed());
                        } elseif (!empty($params) && isset($params['payment'])) {
                            if (isset($params['payment']['use_customer_balance'])) {
                                $this->updateQuote($quote, $module);
                            }
                        }
                        break;
                    case self::AMASTY_STORE_CREDIT:
                        if ($quote->getCustomerBalanceAmountUsed()) {
                            Mage::getSingleton('checkout/session')
                                ->setData('sezzleCustomerBalance', $quote->getCustomerBalanceAmountUsed());
                        } elseif (!empty($params) && isset($params['payment'])) {
                            if (isset($params['payment']['amstcred_use_customer_balance'])) {
                                $this->updateQuote($quote, $module);
                            }
                        }
                        break;
                    default:
                }
                Mage::getSingleton('checkout/session')->setData('sezzleGrandTotal', $quote->getGrandTotal());
                Mage::getSingleton('checkout/session')->setData('sezzleSubtotal', $quote->getSubtotal());
            }
        } catch (Exception $e) {
            $helper->log(
                $this->__(
                    'Error in storing credit data in session. %s.',
                    $e->getMessage(),
                    Zend_Log::ERR
                )
            );
        }
    }

    /**
     * Update quote
     *
     * @param $quote
     * @param $module
     */
    private function updateQuote($quote, $module)
    {
        $customerId = Mage::getSingleton('customer/session')->getId();
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        $balance = 0;
        switch ($module) {
            case self::ENTERPRISE_STORE_CREDIT:
                $balance = Mage::getModel('enterprise_customerbalance/balance')
                    ->setCustomerId($customerId)
                    ->setWebsiteId($websiteId)
                    ->loadByCustomer()
                    ->getAmount();
                break;
            case self::AMASTY_STORE_CREDIT:
                $balance = Mage::getModel('amstcred/balance')
                    ->setCustomerId($customerId)
                    ->setWebsiteId($websiteId)
                    ->loadByCustomer()
                    ->getAmount();
                break;
            default:
        }
        $quote->setUseCustomerBalance(1);
        $quote->setCustomerBalanceAmountUsed($balance->getAmount());
        $grandTotal = $quote->getGrandTotal();
        $quote->setGrandTotal($grandTotal - $balance->getAmount());
        $quote->save();
        Mage::getSingleton('checkout/session')->setData('sezzleCustomerBalance', $balance->getAmount());
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
        try {
            if (Mage::getSingleton('customer/session')->isLoggedIn() &&
                Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance')
            ) {
                $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
                $helper = $sezzlePaymentModel->helper();
                $grandTotal = Mage::getSingleton('checkout/session')->getData('sezzleGrandTotal');
                $subtotal = Mage::getSingleton('checkout/session')->getData('sezzleSubtotal');
                $balance = Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance');
                $quote->setUseCustomerBalance(1);
                $quote->setCustomerBalanceAmountUsed($balance);
                $quote->setBaseCustomerBalanceAmountUsed($balance);
                if ($quote->getSubtotal() == $subtotal) {
                    $quote->setGrandTotal($grandTotal)->save();
                }
                $helper->log($this->__('Store Credit being used: ' . $balance . ", Grand Total: " . $grandTotal));
                return $quote;
            }
        } catch (Exception $e) {
            $helper->log(
                $this->__(
                    'Error in capturing payment with store credit. %s.',
                    $e->getMessage(),
                    Zend_Log::ERR
                )
            );
        }
        return $quote;
    }

    /**
     * Order placement with store credit
     *
     * @param $module
     * @return bool
     */
    public function storeCreditPlaceOrder($module)
    {
        try {
            if (Mage::getSingleton('customer/session')->isLoggedIn()
                && Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance')
            ) {
                $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
                $helper = $sezzlePaymentModel->helper();
                $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
                $order = Mage::getSingleton('sales/order')->loadByIncrementId($orderId);
                $balanceUsed = Mage::getSingleton('checkout/session')->getData('sezzleCustomerBalance');
                $order->setCustomerBalanceAmount($balanceUsed);
                $order->setBaseCustomerBalanceAmount($balanceUsed);
                $order->setCustomerBalanceInvoiced($balanceUsed);
                $order->setBaseCustomerBalanceInvoiced($balanceUsed);
                $order->setTotalPaid($order->getGrandTotal());
                $order->save();
                $this->customerBalanceDeductionFallback($orderId, $balanceUsed, $module);
                Mage::getSingleton('checkout/session')->unsetData('sezzleCustomerBalance');
            }
            return true;
        } catch (Exception $e) {
            $helper->log(
                $this->__(
                    'Error in placing order with store credit. %s.',
                    $e->getMessage(),
                    Zend_Log::ERR
                )
            );
        }
        return false;
    }

    /**
     * Customer balance deduction fallback
     *
     * @param $orderId
     * @param $balanceUsed
     * @param $module
     * @throws Mage_Core_Exception
     */
    protected function customerBalanceDeductionFallback($orderId, $balanceUsed, $module)
    {
        // Get the first customer in the store's ID
        $customerId = Mage::getSingleton('customer/session')->getId();
        $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
        $helper = $sezzlePaymentModel->helper();
        $balance = 0;
        switch ($module) {
            case self::ENTERPRISE_STORE_CREDIT:
                $balanceModel = Mage::getModel('enterprise_customerbalance/balance')
                    ->setCustomerId($customerId)
                    ->setWebsiteId(Mage::app()->getWebsite()->getId($orderId))
                    ->loadByCustomer();
                $balance = $balanceModel->getAmount();
                break;
            case self::AMASTY_STORE_CREDIT:
                $balanceModel = Mage::getModel('amstcred/balance')
                    ->setCustomerId($customerId)
                    ->setWebsiteId(Mage::app()->getWebsite()->getId($orderId))
                    ->loadByCustomer();
                $balance = $balanceModel->getAmount();
                break;
            default:
        }
        if ($balance > 0) {
            //safeguard against a possibility of minus balance
            $balanceModel->setAmountDelta(-1 * $balanceUsed)
                ->setUpdatedActionAdditionalInfo("Order #" . $orderId);
            $helper->log(
                "Customer Balance deduction fallback engaged. Order: "
                . $orderId
                . " Balance Delta: "
                . $balanceUsed
            );
            $balanceModel->save();
        }
    }
}