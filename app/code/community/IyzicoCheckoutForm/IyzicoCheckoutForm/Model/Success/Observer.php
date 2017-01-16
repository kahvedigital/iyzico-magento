<?php
class IyzicoCheckoutForm_IyzicoCheckoutForm_Model_Success_Observer {

    public function activateQuote(Varien_Event_Observer $observer) {
        $paymentMethod = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode();
        if ($paymentMethod == IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::IYZICO_CREDITCARD) {
            $observer->getEvent()->getQuote()->setIsActive(true)->save();
        } else {
            return;
        }
    }

    public function salesOrderPlaceAfter(Varien_Event_Observer $observer) {
        $paymentMethod = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode();
        if ($paymentMethod == IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::IYZICO_CREDITCARD) {
            $makeIyzicoApiCall = Mage::getSingleton('core/session')->getMakeIyzicoApiCall();
            if (false == $makeIyzicoApiCall) {
                $order = $observer->getEvent()->getOrder();
                if (!$order) {
                    return $this;
                }
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
                $quote = Mage::getSingleton('checkout/session')->getQuote();
                $quote->delete();
                Mage::getSingleton('core/session')->unsMakeIyzicoApiCall();
            }
        } else {
            return;
        }
    }

}
