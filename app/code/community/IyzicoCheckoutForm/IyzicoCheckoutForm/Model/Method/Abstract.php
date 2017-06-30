<?php
abstract class IyzicoCheckoutForm_IyzicoCheckoutForm_Model_Method_Abstract extends Mage_Payment_Model_Method_Abstract {

    protected $_isGateway = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = false;
    protected $_methodCode = '';
    protected $_methodTitle = '';
    protected $_code = 'iyzicocheckout_abstract';
    protected $_implementation = 'iframe';
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    public function getCredentials() {

        $credentials = array(
            'api_id' => Mage::getStoreConfig('payment/' . $this->getCode() . '/api_id', $this->getOrder()->getStoreId()),
            'secret' => Mage::getStoreConfig('payment/' . $this->getCode() . '/secret_key', $this->getOrder()->getStoreId()),
            'prefix' => Mage::getStoreConfig('payment/' . $this->getCode() . '/prefix', $this->getOrder()->getStoreId()),
        );
        return $credentials;
    }

    public function getOrder() {
        $paymentInfo = $this->getInfoInstance();

        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            return $paymentInfo->getOrder();
        }

        return $paymentInfo->getQuote();
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getSingleton('customer/session')->getRedirectUrl();
    }

    public function authorize(Varien_Object $payment, $amount) {
        try {
            $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            $currencyArr = Mage::helper('iyzicocheckoutform')->getCurrencyArr();
            if (!in_array($currentCurrencyCode, $currencyArr)) {
                Mage::throwException(Mage::helper('iyzicocheckoutform')->__("Please make sure that the currency value in your payment request is one of the (USD, EUR, GBP, IRR, TL) valid values"));
            }
            $response = Mage::getSingleton('iyzicocheckoutform/iyzicoCheckoutForm_request', array($this->getOrder()))->request();
            if ('make_iyzico_api_call_false' != $response) {
                if ("success" == $response->getStatus()) {
                    if ('iframe' == $this->_getImplementation()) {
                        $this->_iframe($response);
                    } else {
                        $this->_redirect($response);
                    }
                } else {
                    $errorMsg = $response->getErrorMessage();
                    if (empty($errorMsg)) {
                        $errorMsg = 'There are some internal error, please try again';
                    }
                    Mage::throwException(Mage::helper('iyzicocheckoutform')->__($errorMsg));
                }
            } else {
                Mage::getSingleton('customer/session')->setRedirectUrl(Mage::getUrl('checkout/onepage/success/', array('_forced_secure' => true)));
            }
        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->setGotoSection('payment');
            Mage::throwException($e->getMessage());
        }
        return $this;
    }

    public function getTitle() {
        return Mage::helper('iyzicocheckoutform')->__($this->_methodTitle);
    }

    protected function _iframe($response) {
        Mage::getSingleton('customer/session')->setIframeUrl(Mage::getUrl('iyzicocheckoutform/response/handleIyzicoPostResponse/', array('_forced_secure' => true)));
        Mage::getSingleton('customer/session')->setIframeFlag(true);
        Mage::getSingleton('customer/session')->setToken($response->getToken());
        Mage::getSingleton('customer/session')->setCodeSnippet($response->getCheckoutFormContent());
        Mage::getSingleton('customer/session')->setRedirectUrl(Mage::getUrl('iyzicocheckoutform/response/renderIframe/', array('_forced_secure' => true)));
    }

    protected function _redirect($response) {
        Mage::getSingleton('customer/session')->setIframeFlag(false);
    }

    protected function _getImplementation() {
        return $this->_implementation;
    }

    public function canRefund() {
        return $this->_canRefund;
    }

}
