<?php
class IyzicoCheckoutForm_IyzicoCheckoutForm_Model_Method_Creditcard extends IyzicoCheckoutForm_IyzicoCheckoutForm_Model_Method_Abstract {

    protected $_code = 'iyzicocheckoutform_creditcard';

    protected $_methodCode = 'CC';

    protected $_methodTitle = 'Credit Card';
    protected $_formBlockType = 'iyzicocheckoutform/payment_form';

    public function getSubtype() {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/creditcard', $this->getOrder()->getStoreId());
    }

}
