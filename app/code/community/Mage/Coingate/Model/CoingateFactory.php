<?php

require_once(Mage::getBaseDir() . '/app/code/community/Mage/Coingate/lib/coingate_merchant.class.php');

define('COINGATE_MAGENTO_VERSION', '1.0.4');

class Mage_Coingate_Model_CoingateFactory extends Mage_Payment_Model_Method_Abstract
{
    protected $_isGateway = TRUE;
    protected $_canAuthorize = TRUE;

    protected $_code = 'coingate';

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('coingate/pay/redirect');
    }

    public function getRequest()
    {
        $order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getLastOrderId());

        $token = substr(md5(rand()), 0, 32);

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('coingate_order_token', $token);
        $payment->save();

        $title = Mage::app()->getWebsite()->getName();

        $description = array();

        foreach ($order->getAllItems() as $item) {
            $description[] = number_format($item->getQtyOrdered(), 0) . ' × ' . $item->getName();
        }

        $cgConfig = Mage::getStoreConfig('payment/coingate');

        $coingate = $this->initCoingateMerchantClass($cgConfig);

        $coingate->create_order(array(
            'order_id'         => $order->increment_id,
            'price'            => number_format($order->grand_total, 2, '.', ''),
            'currency'         => $order->order_currency_code,
            'receive_currency' => $cgConfig['receive_currency'],
            'success_url'      => Mage::getUrl('coingate/pay/success'),
            'cancel_url'       => Mage::getUrl('coingate/pay/cancel'),
            'callback_url'     => Mage::getUrl('coingate/pay/callback') . '?token=' . $token,
            'title'            => $title . ' Order #' . $order->increment_id,
            'description'      => join($description, ', ')
        ));

        if ($coingate->success) {
            $coingate_response = json_decode($coingate->response, TRUE);

            return $coingate_response['payment_url'];
        } else {
            $this->logMe('Create order', $cgConfig, $coingate);
        }

        return FALSE;
    }

    public function validateCallback()
    {
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($_REQUEST['order_id']);

            if (!$order || !$order->increment_id)
                throw new Exception('Order #' . $_REQUEST['order_id'] . ' does not exists');

            $payment = $order->getPayment();
            $token = $payment->getAdditionalInformation('coingate_order_token');

            if ($token == '' || $_GET['token'] != $token) {
                throw new Exception('Token: 1:' . $_GET['token'] . ' : 2:' . $token . ' do not match');
            }

            $cgConfig = Mage::getStoreConfig('payment/coingate');

            $coingate = $this->initCoingateMerchantClass($cgConfig);

            $coingate->get_order($_REQUEST['id']);

            if (!$coingate->success) {
                throw new Exception('CoinGate Order #' . $_REQUEST['id'] . ' does not exist');
            } else {
                $this->logMe('Validate order', $cgConfig, $coingate);
            }

            $coingate_response = json_decode($coingate->response, TRUE);

            if (!is_array($coingate_response)) {
                throw new Exception('Something wrong with callback');
            }

            if ($coingate_response['status'] == 'paid') {
                $mage_status = Mage_Sales_Model_Order::STATE_PROCESSING;
            }
            else if ($coingate_response['status'] == 'confirming') {
                $mage_status = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
            }
            else if (in_array($coingate_response['status'], array('invalid', 'expired', 'canceled'))) {
                $mage_status = Mage_Sales_Model_Order::STATE_CANCELED;
            }
            else {
                $mage_status = NULL;
            }

            if (!is_null($mage_status)) {
                $order->sendNewOrderEmail()
                    ->setState($mage_status, TRUE)
                    ->save();
            }
        } catch (Exception $e) {
            echo get_class($e) . ': ' . $e->getMessage();
        }
    }

    private function initCoingateMerchantClass($cgConfig) {
        return new CoingateMerchant(
            array(
                'app_id'        => $cgConfig['app_id'],
                'api_key'       => $cgConfig['api_key'],
                'api_secret'    => $cgConfig['api_secret'],
                'mode'          => $cgConfig['test'] == '1' ? 'sandbox' : 'live',
                'user_agent'    => 'CoinGate - Magento Extension v' . COINGATE_MAGENTO_VERSION
            )
        );
    }

    private function logMe($name, $cgConfig, $coingate, $customData = '')
    {
        Mage::Log($name
            . ' - App ID: ' . $cgConfig['app_id']
            . '; Mode: ' . ($cgConfig['test'] == '1' ? 'sandbox' : 'live')
            . '; HTTP Status: ' . $coingate->status_code
            . '; Response: ' . $coingate->response
            . '; cURL Error: ' . json_encode($coingate->curl_error)
            . '; PHP Version: ' . phpversion()
            . '; cURL Version: ' . json_encode(curl_version())
            . '; Magento Version: ' . Mage::getVersion()
            . '; Plugin Version: ' . COINGATE_MAGENTO_VERSION
            . $customData
            . "\n", null, 'coingate.log', true);
    }
}
