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
}