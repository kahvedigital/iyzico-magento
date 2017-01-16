<?php

abstract class IyzicoCheckoutForm_IyzicoCheckoutForm_Block_Payment_Form_Abstract extends Mage_Payment_Block_Form
{

    protected function _getDescription()
    {
        return $this->_paymentText;
    }

}
