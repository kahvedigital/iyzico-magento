<?php
require_once(Mage::getBaseDir('lib') . '/IyzipayBootstrap.php');

class IyzicoCheckoutForm_IyzicoCheckoutForm_Model_Cancel_Observer {

    private $_configuration;
    private $_request;
    private $_conversationId = '';
    private $_paymentId = '';

    public function iyzicoOrderCancel(Varien_Event_Observer $observer) {

        $paymentMethod = $observer->getPayment()->getMethodInstance()->getCode();
        if ($paymentMethod == IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::IYZICO_CREDITCARD) {
            $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            $currencyArr = Mage::helper('iyzicocheckoutform')->getCurrencyArr();
            if (!in_array($currentCurrencyCode, $currencyArr)) {
                Mage::throwException(Mage::helper('iyzicocheckoutform')->__("Please make sure that the currency value in your payment request is one of the (USD, EUR, GBP, IRR, TL) valid values"));
            }

            $customInfo = $observer->getPayment()->getAdditionalInformation('custom_info');

            $customInfoArr = unserialize($customInfo);
            $getEscape     = Mage::getSingleton('core/resource')->getConnection('default_write');

            $this->_conversationId = (int) $customInfoArr['conversation_id'];
            $this->_paymentId = (int) $customInfoArr['payment_id'];

            $payment = $observer->getEvent()->getPayment();
            $order = $payment->getOrder();
            $orderData = $order->getOrigData();

            $orderIncrementId = $orderData['increment_id'];


            if (($order->getStatus() != 'pending_payment') && (!empty($this->_paymentId))) {
                $this->_initIyzipayBootstrap();
                $this->_setClientConfiguration();
                $this->_setCreateRequest();
				
                $apiLogData = array(
                    'method_type' => IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::CANCEL_API,
                    'order_increment_id' => $orderIncrementId,
                    'request_data' => $this->_request->toJsonString(),
                    'response_data' => '',
                    'status' => 'pending',
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                );



                $lastInsertedId = Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog($apiLogData);

                $response = \Iyzipay\Model\Cancel::create($this->_request, $this->_configuration);

                $status = $getEscape->quote($response->getStatus());


                Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog(array('response_data' => $response->getRawResult(), 'status' => $status), $lastInsertedId);
                $cancelPaymentStatus = $response->getStatus();
                if ('success' != $cancelPaymentStatus) {
                    $orderId = $order->getEntityId();
                    $order->setActionFlag(Mage_Sales_Model_Order::ACTION_FLAG_CANCEL, false);
                    Mage::getSingleton('core/session')->getMessages(true);
                    Mage::getSingleton('core/session')->addError('There are some internal error, please try again.');
                    $url = Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id' => $orderId));
                    Mage::app()->getFrontController()->getResponse()->setRedirect($url);
                    Mage::app()->getResponse()->sendResponse();
                    exit;
                }
            }
        }
        return;
    }

    protected function _initIyzipayBootstrap() {
        IyzipayBootstrap::init();
    }

    protected function _setClientConfiguration() {
        $credentials = Mage::helper('iyzicocheckoutform')->getIyzicoCredentials();
        if (!empty($credentials)) {
            $this->_configuration = new \Iyzipay\Options();
            $this->_configuration->setApiKey($credentials['api_id']);
            $this->_configuration->setSecretKey($credentials['secret']);
            $this->_configuration->setBaseUrl(IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::BASE_URL);
        } else {
            throw new Exception('The credentials are empty!', -2);
        }
    }

    protected function _setCreateRequest() {
        $ipAddr = Mage::helper('core/http')->getRemoteAddr(false);
        $this->_request = new \Iyzipay\Request\CreateCancelRequest();
        $siteLang = explode('_', Mage::app()->getLocale()->getLocaleCode());
        $locale = ($siteLang[0] == "tr") ? Iyzipay\Model\Locale::TR : Iyzipay\Model\Locale::EN;
        $this->_request->setLocale($locale);
        $this->_request->setConversationId($this->_conversationId);
        $this->_request->setIp($ipAddr);
        $this->_request->setPaymentId($this->_paymentId);
    }

}
