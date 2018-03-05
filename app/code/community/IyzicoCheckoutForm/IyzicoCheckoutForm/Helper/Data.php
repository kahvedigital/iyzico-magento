<?php
class IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data extends Mage_Core_Helper_Abstract {

    const BASE_URL = "https://sandbox-api.iyzipay.com";
    const INITIAILIZE_CHECKOUT_API = 1;
    const AUTH_RESPONSE_API = 2;
    const CANCEL_API = 3;
    const REFUND_API = 4;
    const IYZICO_CREDITCARD = 'iyzicocheckoutform_creditcard';
    const CURRENCY_CODE = 'TRY';

    public function getQuote() {
        return Mage::getModel('checkout/session')->getQuote();
    }

    public function getOrder($id) {
        return Mage::getSingleton('sales/order')->load($id);
    }

    public function getUserNameData(Mage_Sales_Model_Order $order) {
        $billingParamsObj = $order->getBillingAddress();
        if (!empty($billingParamsObj)) {
            $firstName = $billingParamsObj->getFirstname();
            $lastName = $billingParamsObj->getLastname();
            $company = $billingParamsObj->getCompany();
        } else {
            $firstName = '';
            $lastName = '';
            $company = '';
        }
        $data = array(
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => $company,
        );

        return $data;
    }

    public function getUserAddressData(Mage_Sales_Model_Order $order) {
        $shippingParamObj = $order->getShippingAddress();
        $billingParamObj = $order->getBillingAddress();
        $data = array();
        if (!empty($shippingParamObj)) {
            $countryNameShipping = '';
            $shippingCountryCode = $shippingParamObj->getCountryId();

            if (!empty($shippingCountryCode)) {
                $country = Mage::getModel('directory/country')->loadByCode($shippingCountryCode);
                $countryNameShipping = $country->getName();
            }

            $data['customer_shipping_address_zip'] = $shippingParamObj->getPostcode();
            $data['customer_shipping_address_city'] = $shippingParamObj->getCity();
            $data['customer_shipping_address_street'] = $shippingParamObj->getStreetFull();
            $data['customer_shipping_address_state'] = $shippingParamObj->getRegion();
            $data['customer_shipping_address_country'] = $countryNameShipping;
            $data['customer_shipping_name'] = $shippingParamObj->getName();
        }

        if (!empty($billingParamObj)) {
            $billingCountryCode = $billingParamObj->getCountryId();
            $countryNameBilling = '';

            if (!empty($billingCountryCode)) {
                $country = Mage::getModel('directory/country')->loadByCode($billingCountryCode);
                $countryNameBilling = $country->getName();
            }

            $data['zip'] = $billingParamObj->getPostcode();
            $data['street'] = $billingParamObj->getStreetFull();
            $data['city'] = $billingParamObj->getCity();
            $data['state'] = $billingParamObj->getRegion();
            $data['country_billing'] = $countryNameBilling;
            $data['customer_name'] = $billingParamObj->getName();
        }

        return $data;
    }

    public function getUserContactData(Mage_Sales_Model_Order $order) {
        $billingParams = $order->getBillingAddress();
        $phone = '';
        if (!empty($billingParams)) {
            $phone = $billingParams->getTelephone();
        }
        $data = array(
            'email' => $order->getCustomerEmail(),
            'phone' => $phone,
            'created' => $order->getCreatedAt(),
            'updated' => $order->getUpdatedAt(),
            'ip' => Mage::helper('core/http')->getRemoteAddr(false)
        );

        return $data;
    }

    public function getLocaleIsoCode() {
        return substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
    }

    public function getCategoryNamesById($categoryIds) {
        $categories = array();
        $index = 1;
        foreach ($categoryIds as $ids) {
            if ($index > 2) {
                break;
            }
            $categories[$index] = Mage::getModel('catalog/category')->load($ids)->getName();
            $index++;
        }
        if (empty($categories)) {
            $categories[1] = 'NOT PROVIDED';
            $categories[2] = 'NOT PROVIDED';
        }
        return $categories;
    }

    public function getIyzicoCredentials() {
        $iyzicoCredential = array(
            'api_id' => Mage::getStoreConfig('payment/iyzicocheckoutform_creditcard/api_id', Mage::app()->getStore()->getStoreId()),
            'secret' => Mage::getStoreConfig('payment/iyzicocheckoutform_creditcard/secret_key', Mage::app()->getStore()->getStoreId()),
        );
        return $iyzicoCredential;
    }

    public function getIframeClass() {
        $iframeClass = Mage::getStoreConfig('payment/iyzicocheckoutform_creditcard/iframe_class', Mage::app()->getStore()->getStoreId());
        if (!in_array($iframeClass, array('responsive', 'popup'))) {
            $iframeClass = 'responsive';
        }
        return $iframeClass;
    }

    public function saveIyziTransactionApiLog($data, $id = null) {


        try {
            $apiLogDataArr = array();
            foreach ($data as $key => $value) {
                $apiLogDataArr[$key] = Mage::getSingleton('core/resource')->getConnection('default_write')->quote($value);
            }

            if (!empty($id)) {

                $model = Mage::getModel('iyzicocheckoutform/iyziTransactionLog')->load($id)->addData($apiLogDataArr);
                $model->setId($id)->save();
            
            } else {
            
                $iyziTransactionApiLogModel = Mage::getModel('iyzicocheckoutform/iyziTransactionLog')->setData($apiLogDataArr);
                $id = $iyziTransactionApiLogModel->save()->getId();
            
            }
            return $id;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    public function saveIyziCardSaveLog($data, $id) {
        try {
            $apiLogDataArr = array();
            foreach ($data as $key => $value) {
                $apiLogDataArr[$key] = $value;
            }
            if (!empty($id)) {
				$resource = Mage::getSingleton('core/resource');
                $readConnection = $resource->getConnection('core_read');
                $table = $resource->getTableName('iyzico_card_save_log');
                $query = 'SELECT * FROM ' . $table . ' WHERE customer_id = '
                        . (int) $id . ' LIMIT 1';
                $results = $readConnection->fetchAll($query);
				if($results){
					  $card_id = $results[0]['id'];
				$model = Mage::getModel('iyzicocardsave/iyziCardSaveLog')->load($card_id)->addData($apiLogDataArr);
		
                $model->setId($card_id)->save();
				}else{
				$iyziTransactionApiLogModel = Mage::getModel('iyzicocardsave/iyziCardSaveLog')->setData($apiLogDataArr);
                $id = $iyziTransactionApiLogModel->save()->getId();
				}

            } else {
                $iyziTransactionApiLogModel = Mage::getModel('iyzicocardsave/iyziCardSaveLog')->setData($apiLogDataArr);
                $id = $iyziTransactionApiLogModel->save()->getId();
            }
            return $id;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    public function cardkeyListLog($customer_id = '') {


        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $customer_ID = $customerData->getId();

            if (!empty($customer_ID)) {
                $resource = Mage::getSingleton('core/resource');
                $readConnection = $resource->getConnection('core_read');
                $table = $resource->getTableName('iyzico_card_save_log');
                $query = 'SELECT * FROM ' . $table . ' WHERE customer_id = '
                        . (int) $customer_ID . ' LIMIT 1';
                $results = $readConnection->fetchAll($query);


                $apicard = $results[0]['apikey'];
                $credentials = Mage::helper('iyzicocheckoutform')->getIyzicoCredentials();
                $apikey = $credentials['api_id'];
                if ($apicard == $apikey) {
                    return $results;
                }
            }
        }
    }

    public function transactionLogList($orderId) {
        try {
            $order = Mage::getModel('sales/order')->load($orderId);
            $orderIncermentedId = $order->getIncrementId();

            $transactionLogCollection = Mage::getModel("iyzicocheckoutform/iyziTransactionLog")->getCollection();
            $transactionLogCollection->addFieldToFilter("order_increment_id", $orderIncermentedId);

            return $transactionLogCollection;
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    public function getPaymentMethodCode($orderId) {
        $order = Mage::getModel('sales/order')->load($orderId);
        $paymentMethodCode = $order->getPayment()->getMethodInstance()->getCode();
        return $paymentMethodCode;
    }

    public function getCurrencyConstant($currencyCode = '') {
        if (empty($currencyCode)) {
            $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        }
        $currency = \Iyzipay\Model\Currency::TL;
        switch ($currencyCode) {
            case "TRY":
                $currency = \Iyzipay\Model\Currency::TL;
                break;
            case "USD":
                $currency = \Iyzipay\Model\Currency::USD;
                break;
            case "GBP":
                $currency = \Iyzipay\Model\Currency::GBP;
                break;
            case "EUR":
                $currency = \Iyzipay\Model\Currency::EUR;
                break;
            case "IRR":
                $currency = \Iyzipay\Model\Currency::IRR;
                break;
        }
        return $currency;
    }

    public function getCurrencyArr() {
        return array('TRY', 'EUR', 'USD', 'GBP', 'IRR');
    }

}
