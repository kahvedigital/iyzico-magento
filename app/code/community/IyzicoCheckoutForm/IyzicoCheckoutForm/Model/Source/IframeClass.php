<?php
class IyzicoCheckoutForm_IyzicoCheckoutForm_Model_Source_IframeClass {

    public function toOptionArray() {
        $iframeClass = array(
            array(
                'label' => Mage::helper('core')->__('Popup'),
                'value' => 'popup'
            ),
            array(
                'label' => Mage::helper('core')->__('Responsive'),
                'value' => 'responsive'
            )
        );
        return $iframeClass;
    }

}
