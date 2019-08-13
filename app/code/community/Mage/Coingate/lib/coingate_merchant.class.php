<?php

/**
 * PHP CoinGate Merchant Class
 *
 * @author    CoinGate <info@coingate.com>
 * @version   1.0.2
 * @link      http://developer.coingate.com  
 * @license   MIT
 */

define('CLASS_VERSION', '1.0.2');

class CoingateMerchant {
    private $app_id             = '';
    private $api_key            = '';
    private $api_secret         = '';
    private $mode               = 'sandbox'; // live or sandbox
    private $version            = 'v1';
    private $api_url            = '';
    private $user_agent         = '';
    private $user_agent_origin  = 'CoinGate PHP Merchant Class';

    public function __construct($options = array())
    {
        foreach($options as $key => $value)
        {
            if (in_array($key, array('app_id', 'api_key', 'api_secret', 'mode', 'user_agent')))
            {
                $this->$key = trim($value);
            }

            if (empty($this->user_agent)) {
                $this->user_agent = $this->user_agent_origin . ' v' . CLASS_VERSION;
            }
        }

        $this->set_api_url();
    }

    public function create_order($params = array())
    {
        $this->request('/orders', 'POST', $params);
    }

    public function get_order($order_id)
    {
        $this->request('/orders/' . $order_id);
    }

    public function request($url, $method = 'GET', $params = array())
    {
        $url        = $this->api_url . $url;
        $nonce      = $this->nonce();
        $message    = $nonce . $this->app_id . $this->api_key;
        $signature  = hash_hmac('sha256', $message, $this->api_secret);

        $headers    = array();
        $headers[]  = 'Access-Key: ' . $this->api_key;
        $headers[]  = 'Access-Nonce: ' . $nonce;
        $headers[]  = 'Access-Signature: ' . $signature;

        $curl = curl_init();
        
        $curl_options = array(
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_URL             => $url
        );

        if ($method == 'POST')
        {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';

            array_merge($curl_options, array(CURLOPT_POST => 1));
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt_array($curl, $curl_options);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->user_agent);

        $response       = curl_exec($curl);
        $http_status    = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $this->success      = $http_status == 200;
        $this->status_code  = $http_status;
        $this->response     = $response;
    }

    private function nonce()
    {
        return time();
    }

    private function set_api_url()
    {
        $this->api_url = strtolower($this->mode) == 'live' ? 'https://coingate.com/api/' : 'https://sandbox.coingate.com/api/';
        $this->api_url .= $this->version;
    }
}

?>