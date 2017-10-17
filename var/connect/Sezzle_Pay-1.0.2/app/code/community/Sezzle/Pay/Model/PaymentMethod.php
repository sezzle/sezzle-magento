<?php
class Sezzle_Pay_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Availability options
     */ 
    protected $_logFileName = 'sezzle-pay.log';
    protected $_code = 'pay';
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_isInitializeNeeded      = false;
    protected $_formBlockType           = 'sezzle_pay/form_paylater';

    /**
    * @return Mage_Checkout_Model_Session
    */
    protected function _getCheckout()
    {
       return Mage::getSingleton('checkout/session');
    }
    // Construct the redirect URL
    public function getOrderPlaceRedirectUrl()
    {   
        $redirectUrl = Mage::getUrl('pay/payment/redirect');
        Mage::Log("Step 2 Process: Getting the redirect URL: $redirectUrl", Zend_Log::DEBUG, $this->_logFileName);
        return $redirectUrl;
    }
}