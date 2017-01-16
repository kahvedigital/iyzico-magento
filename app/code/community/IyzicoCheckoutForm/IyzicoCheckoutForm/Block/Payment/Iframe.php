<?php
class IyzicoCheckoutForm_IyzicoCheckoutForm_Block_Payment_Iframe extends Mage_Core_Block_Template {

    public function _construct() {
        parent::_construct();
        $this->setTemplate('iyzicocheckoutform/payment/iframe.phtml');
    }
	
    public function doShowIframe() {
        return Mage::getSingleton('customer/session')->getIframeFlag();
    }

    public function generateCodeSnippet() {
        return Mage::getSingleton('customer/session')->getCodeSnippet();
    }

    public function getIyzicoImageSrc() {
        $imgSrc = $this->getSkinUrl('images/iyzicocheckoutform/payment.png');
        return $imgSrc;
    }

}
