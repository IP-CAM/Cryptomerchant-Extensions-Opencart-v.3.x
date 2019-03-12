<?php
namespace CryptoMerchant;

class CryptoMerchant
{
    const VERSION           = '1.0.0';
    const USER_AGENT_ORIGIN = 'CryptoMerchant PHP Library';

    public static $auth_token  = '';
    public static $user_agent  = '';
    public static $curlopt_ssl_verifypeer = FALSE;

    public static function config($authentication)
    {
        if (isset($authentication['auth_token']))
            self::$auth_token = $authentication['auth_token'];

        if (isset($authentication['user_agent']))
            self::$user_agent = $authentication['user_agent'];
    }

    public static function testConnection($authentication = array(),$params = array())
    {
        try {
            self::request('/test', 'POST', $params, $authentication);
            return true;
        } catch (\Exception $e) {
            return get_class($e) . ': ' . $e->getMessage();
        }
    }

    public static function request($url, $method = 'POST', $params = array(), $authentication = array())
    {
        $auth_token  = isset($authentication['auth_token']) ? $authentication['auth_token'] : self::$auth_token;
        $user_agent  = isset($authentication['user_agent']) ? $authentication['user_agent'] : (isset(self::$user_agent) ? self::$user_agent : (self::USER_AGENT_ORIGIN . ' v' . self::VERSION));
        $curlopt_ssl_verifypeer = isset($authentication['curlopt_ssl_verifypeer']) ? $authentication['curlopt_ssl_verifypeer'] : self::$curlopt_ssl_verifypeer;

        # Check if credentials was passed
        if (empty($auth_token))
            \CryptoMerchant\Exception::throwException(400, array('reason' => 'AuthTokenMissing'));

        # Check if right environment passed
        $url       = "https://api.cryptomerchant.eu".$url;
        $headers   = array();
        $headers[] = 'Authorization: Token ' . $auth_token;
        $curl      = curl_init();

        $curl_options = array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL            => $url
        );

        if ($method == 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            array_merge($curl_options, array(CURLOPT_POST => 1));
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt_array($curl, $curl_options);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $curlopt_ssl_verifypeer);

        $response    = json_decode(curl_exec($curl), TRUE);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if ($http_status === 200)
            return $response;
        else
            \CryptoMerchant\Exception::throwException($http_status, $response);
    }

    public static function decrypt($key, $garble) {
        list($encrypted_data, $iv) = explode('::', base64_decode($garble), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    }
}
