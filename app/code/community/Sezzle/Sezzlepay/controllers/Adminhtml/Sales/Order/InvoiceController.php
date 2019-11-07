<?php

require_once(Mage::getModuleDir('controllers','Mage_Adminhtml').DS.'Sales'.DS.'Order'.DS.'InvoiceController.php');

/**
 * Sezzlepay Invoice Controller
 *
 * @category   Sezzle
 * @package    Sezzle_Sezzlepay
 * @author     Sezzle Team
 */
class Sezzle_Sezzlepay_Adminhtml_Sales_Order_InvoiceController extends Mage_Adminhtml_Sales_Order_InvoiceController
{

    const SUCCESS_CODE = 200;
    const CAPTURE_ONLINE = 'online';

    /**
     * Get Sezzlepay Model
     * 
     * @return Sezzle_Sezzlepay_Model_Sezzlepay
     */
    protected function getSezzlepayModel()
    {
        return Mage::getModel('sezzle_sezzlepay/sezzlepay');
    }

    /**
     * Save invoice
     * We can save only new invoice. Existing invoices are not editable
     */
    public function saveAction()
    {
        $data = $this->getRequest()->getPost('invoice');
        $orderId = $this->getRequest()->getParam('order_id');

        if (!empty($data['comment_text'])) {
            Mage::getSingleton('adminhtml/session')->setCommentText($data['comment_text']);
        }

        try {
            $invoice = $this->_initInvoice();
            $hasSezzleCaptured = false;
            if ($invoice) {

                if (!empty($data['capture_case'])) {
                    $order = Mage::getModel('sales/order')->load($orderId);
                    if ($order->getId() 
                        && $data['capture_case'] == self::CAPTURE_ONLINE
                        && $order->getIsCaptured() == Sezzle_Sezzlepay_Model_Sezzlepay::STATE_NOT_CAPTURED
                        && $order->getPayment()->getAdditionalInformation("payment_type") == Sezzle_Sezzlepay_Model_Sezzlepay::AUTH
                        && $order->getPayment()->getMethodInstance()->getCode() == Sezzle_Sezzlepay_Model_Sezzlepay::PAYMENT_CODE) {
                        $invoice->setRequestedCaptureCase($data['capture_case']);
                    }
                    else {
                        $invoice->setRequestedCaptureCase($data['capture_case']);
                    }
                }
                
                if (!empty($data['comment_text'])) {
                    $invoice->addComment(
                        $data['comment_text'],
                        isset($data['comment_customer_notify']),
                        isset($data['is_visible_on_front'])
                    );
                }

                $invoice->register();

                if (!empty($data['send_email'])) {
                    $invoice->setEmailSent(true);
                }

                $invoice->getOrder()->setCustomerNoteNotify(!empty($data['send_email']));
                $invoice->getOrder()->setIsInProcess(true);
                if ($invoice->getOrder()->getIsCaptured() == Sezzle_Sezzlepay_Model_Sezzlepay::STATE_NOT_CAPTURED && $hasSezzleCaptured) {
                    $invoice->getOrder()->setIsCaptured(Sezzle_Sezzlepay_Model_Sezzlepay::STATE_CAPTURED);
                }

                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $shipment = false;
                if (!empty($data['do_shipment']) || (int) $invoice->getOrder()->getForcedDoShipmentWithInvoice()) {
                    $shipment = $this->_prepareShipment($invoice);
                    if ($shipment) {
                        $shipment->setEmailSent($invoice->getEmailSent());
                        $transactionSave->addObject($shipment);
                    }
                }
                $transactionSave->save();

                if (isset($shippingResponse) && $shippingResponse->hasErrors()) {
                    $this->_getSession()->addError($this->__('The invoice and the shipment  have been created. The shipping label cannot be created at the moment.'));
                } elseif (!empty($data['do_shipment'])) {
                    $this->_getSession()->addSuccess($this->__('The invoice and shipment have been created.'));
                } else {
                    $this->_getSession()->addSuccess($this->__('The invoice has been created.'));
                }

                // send invoice/shipment emails
                $comment = '';
                if (isset($data['comment_customer_notify'])) {
                    $comment = $data['comment_text'];
                }
                try {
                    $invoice->sendEmail(!empty($data['send_email']), $comment);
                } catch (Exception $e) {
                    Mage::logException($e);
                    $this->_getSession()->addError($this->__('Unable to send the invoice email.'));
                }
                if ($shipment) {
                    try {
                        $shipment->sendEmail(!empty($data['send_email']));
                    } catch (Exception $e) {
                        Mage::logException($e);
                        $this->_getSession()->addError($this->__('Unable to send the shipment email.'));
                    }
                }
                Mage::getSingleton('adminhtml/session')->getCommentText(true);
                $this->_redirect('*/sales_order/view', array('order_id' => $orderId));
            } else {
                $this->_redirect('*/*/new', array('order_id' => $orderId));
            }
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Unable to save the invoice.'));
            Mage::logException($e);
        }
        $this->_redirect('*/*/new', array('order_id' => $orderId));
    }
}