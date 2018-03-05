<?php
require_once(Mage::getBaseDir('lib') . '/IyzipayBootstrap.php');
class IyzicoCheckoutForm_IyzicoCheckoutForm_Model_Refund_Observer {

    private $_configuration;
    private $_request;
    private $_conversationId = '';
    private $_paymentTransactionId = '';
    private $_price = 0;

    public function iyzicoItemRefund(Varien_Event_Observer $observer) {

        $creditMemo = $observer->getEvent()->getCreditmemo();
        $getEscape     = Mage::getSingleton('core/resource')->getConnection('default_write');
        $postItems = $_POST;
        $order = $creditMemo->getOrder();
        $orderData = $order->getOrigData();
        $orderIncrementId = $orderData['increment_id'];
        $paymentMethodCode = $order->getPayment()->getMethodInstance()->getCode();
        if ($paymentMethodCode == IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::IYZICO_CREDITCARD) {
            $currencyArr = Mage::helper('iyzicocheckoutform')->getCurrencyArr();
            $items = $order->getAllVisibleItems();
            $customInfo = $order->getPayment()->getAdditionalInformation('custom_info');
            $orderCurrency = $order->getOrderCurrencyCode();
            $this->_initIyzipayBootstrap();
            $currency = Mage::helper('iyzicocheckoutform')->getCurrencyConstant($orderCurrency);
            if (!in_array($currency, $currencyArr)) {
                Mage::throwException(Mage::helper('iyzicocheckoutform')->__("Please make sure that the currency value in your payment request is one of the (USD, EUR, GBP, IRR, TL) valid values"));
            }
            foreach ($items as $item) {
                $itemId = (int) $item->getItemId();
                $refundItemQty = $getEscape->quote($postItems['creditmemo']['items'][$itemId]['qty']);
                $itemPrice = $item->getPrice();
                $refundAmt = $itemPrice * $refundItemQty;
                $productItemId = $item->getProductId();
                if ($refundItemQty > 0) {
                    $customInfoArr = unserialize($customInfo);
                    $this->_conversationId = (int) $customInfoArr['conversation_id'];
                    $this->_paymentTransactionId = (int) $customInfoArr['items'][$productItemId]['payment_transaction_id'];
                    $this->_price = $refundAmt;
                    $this->_setClientConfiguration();
                    $this->_setCreateRequest($currency);


                    $apiLogData = array(
                        'method_type' => IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::REFUND_API,
                        'order_increment_id' => $orderIncrementId,
                        'request_data' => $this->_request->toJsonString(),
                        'response_data' => '',
                        'status' => 'pending',
                        'created' => date('Y-m-d H:i:s'),
                        'modified' => date('Y-m-d H:i:s'),
                    );

                    $lastInsertedId = Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog($apiLogData);

                    $response = \Iyzipay\Model\Refund::create($this->_request, $this->_configuration);

                    $status = $getEscape->quote($response->getStatus());

                    Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog(array('response_data' => $response->getRawResult(), 'status' => $status), $lastInsertedId);

                    $refundPaymentStatus = $response->getStatus();
                    if ('success' != $refundPaymentStatus) {
                        $errorMessage = $response->geterrorMessage();
                        if (empty($errorMessage)) {
                            $errorMessage = 'There are some internal error, please try again.';
                        }
                        Mage::getSingleton('core/session')->addError($errorMessage);
                    }
                }
            }
        }
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

    protected function _setCreateRequest($currency) {
        $ipAddr = Mage::helper('core/http')->getRemoteAddr(false);
        $this->_request = new \Iyzipay\Request\CreateRefundRequest();
        $siteLang = explode('_', Mage::app()->getLocale()->getLocaleCode());
        $locale = ($siteLang[0] == "tr") ? Iyzipay\Model\Locale::TR : Iyzipay\Model\Locale::EN;
        $this->_request->setLocale($locale);
        $this->_request->setConversationId($this->_conversationId);
        $this->_request->setIp($ipAddr);
        $this->_request->setPaymentTransactionId($this->_paymentTransactionId);
        $this->_request->setCurrency($currency);
        $this->_request->setPrice($this->_price);
    }

    protected function _saveCreditmemo($creditmemo) {
        $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($creditmemo->getOrder());
        if ($creditmemo->getInvoice()) {
            $transactionSave->addObject($creditmemo->getInvoice());
        }
        $transactionSave->save();

        return $this;
    }

}
