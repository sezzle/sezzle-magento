<?php

/**
 * Sezzlepay gift card model
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Model_Giftcard
{

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
        $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
        $helper = $sezzlePaymentModel->helper();
        try {
            if (!empty($balance) && $balance > 0) {
                $quote->setGiftCardsAmountUsed($balance);
                $quote->setGiftCards($giftCards);
                //deduct the gift card
                $giftCardsData = Mage::helper('core/unserializeArray')->unserialize($giftCards);
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
                        $helper->log($this->__('Gift Cards used: ' . $giftCards . ' Amount being used: ' . $balance));
                    } else {
                        $helper->log($this->__('Gift Cards used: ' . $giftCards . ' Amount is deducted already'));
                    }
                    Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCards');
                    Mage::getSingleton('checkout/session')->unsetData('sezzleGiftCardsAmount');
                }
                return $quote;
            }
        } catch (Exception $exception) {
            $helper->log(
                $this->__(
                    'Error capturing gift cards. %s.', $exception->getMessage(),
                    Zend_Log::ERR
                )
            );
        }
        return $quote;
    }

    /**
     * Order placement with gift cards
     *
     * @return bool
     */
    public function giftCardsPlaceOrder()
    {
        try {
            if (Mage::getSingleton('checkout/session')->getData('sezzleGiftCards')) {
                $sezzlePaymentModel = Mage::getModel('sezzle_sezzlepay/sezzlepay');
                $helper = $sezzlePaymentModel->helper();
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
        catch (Exception $e) {
            $helper->log(
                $this->__(
                    'Error in placing order with gift card. %s.', $e->getMessage(),
                    Zend_Log::ERR
                )
            );
        }
    }
}