<?php
require_once(Mage::getBaseDir('lib') . '/IyzipayBootstrap.php');

class IyzicoCheckoutForm_IyzicoCheckoutForm_Model_IyzicoCheckoutForm_Request extends Mage_Core_Model_Abstract {
	
    private $_configuration;
    private $_request;
    private $_buyer;
    private $_billingAddress;
    private $_shippingAddress;
    private $_makeIyzicoApiCall = true;

    private $_order;

    public function __construct(array $params) {
        $this->_setOrder($params[0]);
    }

    public function removeSIDqueryStrVar() {
        return strtok(Mage::getUrl('', array('_secure' => true)), '?');
    }

    protected function _setClientConfiguration() {
        $credentials = $this->_getPaymentObject()->getCredentials();
        if ((!empty($credentials['api_id'])) && (!empty($credentials['secret']))) {
            $this->_configuration = new \Iyzipay\Options();
            $this->_configuration->setApiKey($credentials['api_id']);
            $this->_configuration->setSecretKey($credentials['secret']);
            $this->_configuration->setBaseUrl(IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::BASE_URL);
        } else {
            throw new Exception('The credentials are empty!', -2);
        }
    }

    protected function _setCreateRequest() {
        $currency = Mage::helper('iyzicocheckoutform')->getCurrencyConstant();
        $externalId = $this->_getExternalId();
        $siteLang = explode('_', Mage::app()->getLocale()->getLocaleCode());
        $locale = ($siteLang[0] == "tr") ? Iyzipay\Model\Locale::TR : Iyzipay\Model\Locale::EN;
        $this->_request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
        $this->_request->setLocale($locale);
        $this->_request->setConversationId($externalId);
        $this->_request->setCurrency($currency);
        $this->_request->setBasketId($this->_getOrder()->getQuoteId() . '_' . $externalId . '_' . $this->_getOrderId());
        $this->_request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
        $this->_request->setCallbackUrl($this->removeSIDqueryStrVar() . 'iyzicocheckoutform/response/handleIyzicoPostResponse');
        $collection = Mage::helper('iyzicocheckoutform')->cardkeyListLog();
        $this->_request->setCardUserKey($collection[0]['carduserkey']);
    }

    private function _getExternalId() {
        $externalId = str_replace(".", "", uniqid('magento_MBCR_', true));
        return $externalId;
    }

    private function _getOrderId() {
        if ($this->_getOrder() instanceof Mage_Sales_Model_Order) {
            $orderId = $this->_getOrder()->getIncrementId();
        } else {
            $orderId = $this->_getOrder()->getReservedOrderId();
        }
        return $orderId;
    }

    protected function _setPaymentBuyerDetails() {
        $this->_buyer = new \Iyzipay\Model\Buyer();
        $userContactData = Mage::helper('iyzicocheckoutform')->getUserContactData($this->_getOrder());

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {

            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $customerId = str_pad($customer->getId(), 11, '0', STR_PAD_LEFT);
            $customerFirstName = $customer->getFirstname();
            $customerFirstName = !empty($customerFirstName) ? $customerFirstName : 'NOT PROVIDED';
            $customerLastName = $customer->getLastname();
            $customerLastName = !empty($customerLastName) ? $customerLastName : 'NOT PROVIDED';
            $customerEmail = $customer->getEmail();
            $customerEmail = !empty($customerEmail) ? $customerEmail : 'NOT PROVIDED';
            $customerUpdatedDate = $customer->getUpdatedAt();
            $customerUpdatedDate = !empty($customerUpdatedDate) ? date("Y-m-d H:i:s", strtotime($customerUpdatedDate)) : 'NOT PROVIDED';
            $customerCreatedDate = $customer->getCreatedAt();
            $customerCreatedDate = !empty($customerCreatedDate) ? date("Y-m-d H:i:s", strtotime($customerCreatedDate)) : 'NOT PROVIDED';
        } else {

            $userNameData = Mage::helper('iyzicocheckoutform')->getUserNameData($this->_getOrder());
            $customerId = str_pad($this->_getOrder()->getIncrementId(), 11, '0', STR_PAD_LEFT);
            if (!empty($userNameData)) {
                $customerFirstName = !empty($userNameData['first_name']) ? $userNameData['first_name'] : 'NOT PROVIDED';
                $customerLastName = !empty($userNameData['last_name']) ? $userNameData['last_name'] : 'NOT PROVIDED';
            }
            if (!empty($userContactData)) {
                $customerEmail = !empty($userContactData['email']) ? $userContactData['email'] : 'NOT PROVIDED';
                $customerUpdatedDate = !empty($userContactData['updated']) ? $userContactData['updated'] : 'NOT PROVIDED';
                $customerCreatedDate = !empty($userContactData['created']) ? $userContactData['created'] : 'NOT PROVIDED';
            } else {
                throw new Exception('The contact data are empty!', -3);
            }
        }

        $customerTelephone = !empty($userContactData['phone']) ? $userContactData['phone'] : 'NOT PROVIDED';
        $this->_buyer->setId($customerId);
        $this->_buyer->setIdentityNumber($customerId);
        $this->_buyer->setName($customerFirstName);
        $this->_buyer->setSurname($customerLastName);
        $this->_buyer->setGsmNumber($customerTelephone);
        $this->_buyer->setEmail($customerEmail);
        $this->_buyer->setLastLoginDate($customerUpdatedDate);
        $this->_buyer->setRegistrationDate($customerCreatedDate);

        $addressDetails = Mage::helper('iyzicocheckoutform')->getUserAddressData($this->_getOrder());

        if (!empty($addressDetails['city'])) {
            $buyerCity = $addressDetails['city'];
        } else if (!empty($addressDetails['state'])) {
            $buyerCity = $addressDetails['state'];
        } else {
            $buyerCity = 'NOT PROVIDED';
        }
        $ipAddress = Mage::helper('core/http')->getRemoteAddr(false);
        $buyerStreet = !empty($addressDetails['street']) ? $addressDetails['street'] : 'NOT PROVIDED';
        $buyerCountry = !empty($addressDetails['country_billing']) ? $addressDetails['country_billing'] : 'NOT PROVIDED';
        $buyerZip = !empty($addressDetails['zip']) ? $addressDetails['zip'] : 'NOT PROVIDED';
        $this->_buyer->setRegistrationAddress($buyerStreet);
        $this->_buyer->setCity($buyerCity);
        $this->_buyer->setIp($ipAddress);
        $this->_buyer->setCountry($buyerCountry);
        $this->_buyer->setZipCode($buyerZip);
        $this->_request->setBuyer($this->_buyer);
    }

    protected function _setBillingAddressDetails() {

        $addressDetails = Mage::helper('iyzicocheckoutform')->getUserAddressData($this->_getOrder());

        if (!empty($addressDetails)) {
            $billingCity = !empty($addressDetails['city']) ? $addressDetails['city'] : 'NOT PROVIDED';
            $billingStreet = !empty($addressDetails['street']) ? $addressDetails['street'] : 'NOT PROVIDED';
            $billingZip = !empty($addressDetails['zip']) ? $addressDetails['zip'] : 'NOT PROVIDED';
            $billingCountry = !empty($addressDetails['country_billing']) ? $addressDetails['country_billing'] : 'NOT PROVIDED';
            $billingCustomerName = !empty($addressDetails['customer_name']) ? $addressDetails['customer_name'] : 'NOT PROVIDED';

            $this->_billingAddress = new \Iyzipay\Model\Address();
            $this->_billingAddress->setContactName($billingCustomerName);
            $this->_billingAddress->setCity($billingCity);
            $this->_billingAddress->setCountry($billingCountry);
            $this->_billingAddress->setAddress($billingStreet);
            $this->_billingAddress->setZipCode($billingZip);
            $this->_request->setBillingAddress($this->_billingAddress);

            $shippingCity = !empty($addressDetails['customer_shipping_address_city']) ? $addressDetails['customer_shipping_address_city'] : $billingCity;
            $shippingStreet = !empty($addressDetails['customer_shipping_address_street']) ? $addressDetails['customer_shipping_address_street'] : $billingStreet;
            $shippingZip = !empty($addressDetails['customer_shipping_address_zip']) ? $addressDetails['customer_shipping_address_zip'] : $billingZip;
            $shippingCountry = !empty($addressDetails['customer_shipping_address_country']) ? $addressDetails['customer_shipping_address_country'] : $billingCountry;
            $shippingCustomerName = !empty($addressDetails['customer_shipping_name']) ? $addressDetails['customer_shipping_name'] : $billingCustomerName;

            $this->_shippingAddress = new \Iyzipay\Model\Address();
            $this->_shippingAddress->setContactName($shippingCustomerName);
            $this->_shippingAddress->setCity($shippingCity);
            $this->_shippingAddress->setCountry($shippingCountry);
            $this->_shippingAddress->setAddress($shippingStreet);
            $this->_shippingAddress->setZipCode($shippingZip);
            $this->_request->setShippingAddress($this->_shippingAddress);
        } else {
            throw new Exception('The address data are empty!', -3);
        }
    }

    protected function _setCreatePaymentBasketItems() {
        $itemsArr = array();
        $grandTotal = Mage::getModel('checkout/cart')->getQuote()->getGrandTotal();
        $cartItems = Mage::getModel("checkout/cart")->getItems();
        $totalShippingAmountIncludingTax = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingInclTax();
        $subTotalIncludingTax = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getBaseSubtotalInclTax();
        $totalDiscountAmount = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getBaseDiscountAmount();

        $appliedRuleIds = Mage::getSingleton('checkout/session')->getQuote()->getAppliedRuleIds();
        if (!empty($appliedRuleIds)) {
            $appliedRuleIds = explode(',', $appliedRuleIds);
            $rules = Mage::getModel('salesrule/rule')->getCollection()->addFieldToFilter('rule_id', array('in' => $appliedRuleIds));
            foreach ($rules as $value) {
                if (!empty($value)) {
                    $ruleInfo = $value->getData();
                    $currentRuleAction = $ruleInfo['simple_action'];
                    $discountApplyToShippingRule = $ruleInfo['apply_to_shipping'];
                    $discountAmount = $ruleInfo['discount_amount'];
                    if ($discountApplyToShippingRule == 1) {
                        switch ($currentRuleAction) {
                            case 'by_percent':
                                $byPercentDiscountAmount = $totalShippingAmountIncludingTax * $discountAmount / 100;
                                $byPercentDiscountAmount = round($byPercentDiscountAmount, 2);
                                $totalShippingAmountIncludingTax = $totalShippingAmountIncludingTax - $byPercentDiscountAmount;
                                break;
                            case 'by_fixed':
                                $totalShippingAmountIncludingTax = $totalShippingAmountIncludingTax - $discountAmount;
                                break;
                            case 'cart_fixed':
                                if (abs($totalDiscountAmount) > $subTotalIncludingTax) {
                                    $addittionalDiscountAmount = abs($totalDiscountAmount) - $subTotalIncludingTax;
                                    $addittionalDiscountAmount = round($addittionalDiscountAmount, 2);
                                    $totalShippingAmountIncludingTax = $totalShippingAmountIncludingTax - $addittionalDiscountAmount;
                                }
                     
                                break;
                            case 'buy_x_get_y':
                    
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
        }

        $totalPrice = 0.0;
        $finalItemPrice = 0.0;
        $itemObj = '';
        if (!empty($cartItems)) {
            foreach ($cartItems as $item) {
                $itemQty = $item->getQty();
                $inclTax = $item->getPriceInclTax();
                $itemPrice = $itemQty * $inclTax;

                $itemShippingAmount = ($itemPrice / $subTotalIncludingTax) * $totalShippingAmountIncludingTax;
                $roundedItemShippingAmount = round($itemShippingAmount, 2);

                $itemWithShippingAmount = $itemPrice + $roundedItemShippingAmount;

                $discountAmt = $item->getDiscountAmount();
                $finalItemPrice = $itemWithShippingAmount - $discountAmt;
                if ($finalItemPrice > 0) {
                    $itemObj = new \Iyzipay\Model\BasketItem();
                    $itemObj->setId($item->getProductId());
                    $itemObj->setName($item->getProduct()->getName());
                    $_product = Mage::getModel('catalog/product')->load($item->getProductId());
                    $categories = Mage::helper('iyzicocheckoutform')->getCategoryNamesById($_product->getCategoryIds());
                    foreach ($categories as $key => $value) {
                        $methodName = "setCategory" . $key;
                        $itemObj->$methodName($value);
                    }
                    $itemObj->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
                    $itemProductType = $item->getProductType();
                    if (in_array($itemProductType, array('virtual', 'downloadable'))) {
                        $itemObj->setItemType(\Iyzipay\Model\BasketItemType::VIRTUAL);
                    }
                    $itemObj->setPrice($finalItemPrice);
                    $itemsArr[] = $itemObj;
                    $totalPrice += $finalItemPrice;
                }
            }
        }
        $roundedTotalPrice = round($totalPrice, 2);
        Mage::getSingleton('core/session')->setMakeIyzicoApiCall(true);
        if ($grandTotal <= 0) {
            $this->_makeIyzicoApiCall = false;
            Mage::getSingleton('core/session')->setMakeIyzicoApiCall(false);
        } else if ($grandTotal != $roundedTotalPrice) {
            $differentiateAmt = round($grandTotal - $roundedTotalPrice, 2);
            $itemObj->setPrice($finalItemPrice + $differentiateAmt);
        }
        $this->_request->setPrice($roundedTotalPrice);
        $this->_request->setPaidPrice($grandTotal);
        $this->_request->setBasketItems($itemsArr);
    }

    protected function _initIyzipayBootstrap() {
        IyzipayBootstrap::init();
    }

    public function request() {
        try {
            $this->_initIyzipayBootstrap();
            $this->_setClientConfiguration();
            $this->_setCreateRequest();
            $this->_setPaymentBuyerDetails();
            $this->_setBillingAddressDetails();
            $this->_setCreatePaymentBasketItems();

            if ($this->_makeIyzicoApiCall) {
                $apiLogData = array(
                    'method_type' => IyzicoCheckoutForm_IyzicoCheckoutForm_Helper_Data::INITIAILIZE_CHECKOUT_API,
                    'order_increment_id' => $this->_getOrderId(),
                    'request_data' => $this->_request->toJsonString(),
                    'response_data' => '',
                    'status' => 'pending',
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                );

                $lastInsertedId = Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog($apiLogData);

                $response = \Iyzipay\Model\CheckoutFormInitialize::create($this->_request, $this->_configuration);
				
                $status = Mage::getSingleton('core/resource')->getConnection('default_write')->quote($response->getStatus());

                
                Mage::helper('iyzicocheckoutform')->saveIyziTransactionApiLog(array('response_data' => $response->getRawResult(), 'status' => $status), $lastInsertedId);
                return $response;
            } else {
                return 'make_iyzico_api_call_false';
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    private function _getOrder() {
        return $this->_order;
    }

    private function _setOrder(Mage_Sales_Model_Order $order) {
        $this->_order = $order;
    }

    private function _getPaymentObject() {
        return $this->_getOrder()->getPayment()->getMethodInstance();
    }

}
