<?php
require_once(Mage::getBaseDir('lib') . '/IyzipayBootstrap.php');

class IyzicoCheckoutForm_IyzicoCheckoutForm_ResponseController extends Mage_Core_Controller_Front_Action {

    private $_responseToken = '';
    private $_conversationId = '';
    private $_requestObj = '';
    private $_configObj = '';

    public function _construct() {
        parent::_construct();
    }

    protected function _initIyzipayBootstrap() {
        IyzipayBootstrap::init();
    }

    private function _setConfigurations() {
        $credentials = Mage::helper('iyzicocheckoutform')->getIyzicoCredentials();
        if (!empty($credentials)) {
            $this->_configObj = new \Iyzipay\Options();
            $this->_configObj->setApiKey($credentials['api_id']);
            $this->_configObj->setSecretKey($credentials['secret']);
            $this->_configObj->setBaseUrl(IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::BASE_URL);
        } else {
            throw new Exception('The credentials are empty!', -2);
        }
    }

    private function _setRequest() {
        $siteLang = explode('_', Mage::app()->getLocale()->getLocaleCode());
        $locale = ($siteLang[0] == "tr") ? Iyzipay\Model\Locale::TR : Iyzipay\Model\Locale::EN;
        $this->_requestObj = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
        $this->_requestObj->setLocale($locale);
        $this->_requestObj->setConversationId($this->_conversationId);
        $this->_requestObj->setToken($this->_responseToken);
    }

    public function handleIyzicoPostResponseAction() {

        $postDataResponse = Mage::app()->getRequest()->getPost();


        $this->_responseToken = $postDataResponse['token'];
        $this->_conversationId = !empty($postDataResponse['conversationId']) ? $postDataResponse['conversationId'] : '';
        $this->_initIyzipayBootstrap();
        $this->_setConfigurations();
        $this->_setRequest();

        $apiLogData = array(
            'method_type' => IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::AUTH_RESPONSE_API,
            'request_data' => $this->_requestObj->toJsonString(),
            'response_data' => '',
            'status' => 'pending',
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s')
        );

        $lastInsertedId = Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog($apiLogData);

        $authResponse = \Iyzipay\Model\CheckoutForm::retrieve($this->_requestObj, $this->_configObj);

        $status = Mage::getSingleton('core/resource')->getConnection('default_write')->quote($authResponse->getStatus());


        Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog(array('response_data' => $authResponse->getRawResult(), 'status' => $status), $lastInsertedId);

        if (!empty($authResponse)) {
            $order = Mage::getSingleton('sales/order');
            $this->_getPostResponseActionUrl($order, $authResponse, $lastInsertedId);
        } else {
            return $this->_setErrorRedirect('There is an some error, please try again');
        }
    }

    private function _getPostResponseActionUrl(Mage_Sales_Model_Order $order, $response, $lastInsertedId) {

        $getEscape = Mage::getSingleton('core/resource')->getConnection('default_write');
        $basketId = $response->getBasketId();

        $cartItem = Mage::helper('checkout/cart')->getCart()->getItemsCount();

        if (!empty($basketId) && !empty($cartItem)) {
            $dataArr = explode('_', $basketId);
            $orderId = $dataArr[count($dataArr) - 1];
            $order->loadByIncrementId($orderId);
            $orderState = $order->getStatus();
        } else {
            return $this->_setErrorRedirect("Invalid Order");
        }

        $basketId = $getEscape->quote($basketId);

        Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog(array('order_increment_id' => $orderId), $lastInsertedId);

        $paymentStatus = $response->getPaymentStatus();
        if ('SUCCESS' != $paymentStatus) {
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
            return $this->_setErrorRedirect('There is an some error, please try again');
        }
        if ('pending_payment' == $orderState) {
            $token = $getEscape->quote($response->getToken());
            $paymentId = (int) $response->getPaymentId();
            $conversationId = $getEscape->quote($response->getConversationId());
            $orderItemsArr = $getEscape->quote($response->getPaymentItems());
            $itemsArr = array();
            foreach ($orderItemsArr as $value) {
                $itemId = $getEscape->quote($value->getItemId());
                $itemsArr[$itemId]['id'] = $itemId;
                $itemsArr[$itemId]['payment_transaction_id'] = $getEscape->quote($value->getPaymentTransactionId());
                $itemsArr[$itemId]['price'] = $getEscape->quote($value->getPrice());
            }
            $customInfo = array(
                'IDENTIFICATION_REFERENCEID' => $token,
                'payment_id' => $paymentId,
                'conversation_id' => $conversationId,
                'items' => $itemsArr
            );
            $customInfo = serialize($customInfo);

            $order->getPayment()->setAdditionalInformation(
                    'custom_info', $customInfo
            );
            $iyzicoAmount = $getEscape->quote($response->getPaidPrice());
            $installmentCount = $getEscape->quote($response->getInstallment());

            if ($installmentCount > 1) {
                $granTotalCart = $order->getGrandTotal();
                $installmentFee = $iyzicoAmount - $granTotalCart;
                $order->setGrandTotal($iyzicoAmount);
                $order->setIyzicoinstallmentAmount($installmentFee);
                $order->setNumberOfInstallment($installmentCount);
            }


            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
        
                $credentials = Mage::helper('iyzicocheckoutform')->getIyzicoCredentials();
                $apikey = $credentials['api_id'];
                $card_user_key = $response->GetcardUserKey();
                $customerData = Mage::getSingleton('customer/session')->getCustomer();
				$customer_ID = $customerData->getId();
                $data = array('customer_id' => $customer_ID, 'carduserkey' => $card_user_key, 'apikey' => $apikey);
                $lastInsertedId = Mage::helper('iyzicocardsave')->saveIyziCardSaveLog($data, $customer_ID);
            }

            $order->save();
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();

            Mage::getModel('sales/quote')->load($order->getQuoteId())->setIsActive(false)->save();
            $pageName = 'checkout/onepage/success/';
            $order->sendNewOrderEmail();
            $url = Mage::getUrl('', array('_forced_secure' => true)) . $pageName;
            Mage::app()->getFrontController()->getResponse()->setRedirect($url)->sendResponse();
            Mage::getSingleton('core/session')->unsMakeIyzicoApiCall();
            return;
        } else {
            return $this->_setErrorRedirect("Invalid Order or Order is already placed");
        }
    }

    private function _getPaymentObject() {
        return $this->_getOrder()->getPayment()->getMethodInstance();
    }

    public function renderIframeAction() {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock('iyzicocheckoutform/payment_iframe');
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }


    private function _setErrorRedirect($message) {
        if (!empty($message)) {
            Mage::getSingleton('core/session')->getMessages(true);
            Mage::getSingleton('core/session')->addError($this->__($message));
        }
        Mage::getSingleton('core/session')->unsMakeIyzicoApiCall();
        $this->_redirect('checkout/cart/');
    }

}
