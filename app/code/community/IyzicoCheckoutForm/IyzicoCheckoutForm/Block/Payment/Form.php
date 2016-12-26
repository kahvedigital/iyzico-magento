<?php

class IyzicoCheckoutForm_IyzicoCheckoutForm_Block_Payment_Form extends Mage_Payment_Block_Form
{

    protected function _construct()
    {

        parent::_construct();
        $this->setTemplate('iyzicocheckoutform/payment/form.phtml');
    }

    public function getIyzicoImageSrc()
    {
        $imgSrc = $this->getSkinUrl('images/iyzicocheckoutform/iyzicoPayment.png');
        return $imgSrc;
    }

    public function removeSIDqueryStrVar()
    {
        return strtok(Mage::getUrl('', array('_secure' => true)), '?');
    }

}
