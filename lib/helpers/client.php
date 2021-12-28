<?php

namespace Eshoplogistic\Delivery\Helpers;

use \Bitrix\Main\Config\Option,
    \Bitrix\Main\Web\HttpClient,
    \Eshoplogistic\Delivery\Config;

/** eShopLogistic
 * Class Client
 * @package Eshoplogistic\Delivery\Helpers
 * @author negen
 */
class Client
{
    private $httpClient;
    private $url;
    private $apiKey;
    private $log;

    function __construct($apiObject)
    {

        $this->httpClient = new HttpClient();
        $this->url = 'https://api.eshoplogistic.ru/api/' . $apiObject;
        $this->apiKey = Option::get(Config::MODULE_ID, 'api_key');

        $this->log = Option::get(Config::MODULE_ID, 'api_log');
    }

    /** Http - eSputnik
     *
     * @param string $httpMethod
     * @param array $apiParams
     *
     * @return array
     */

    public function request($httpMethod, $apiParams = array())
    {
        $apiParams['key'] = $this->apiKey;
        $this->httpClient->query($httpMethod, $this->url, $apiParams, false);
        $httpResult = $this->httpClient->getResult();

        if (!$httpResult) {
            $httpResult = $this->alternativeCurlPost($this->url, $apiParams);
        }

        if($this->log == 'Y')
            $this->eslWriteLog($httpResult, $this->url, $apiParams);

        return json_decode($httpResult, true);
    }

    public function alternativeCurlPost($url, $body = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    public function eslWriteLog($log, $url, $params)
    {
        if(isset($params['target']))
            return false;

        $d = date("j-M-Y H:i:s e");
        $header = ' ####################### ';

        $path = $_SERVER["DOCUMENT_ROOT"] . '/upload/esl.log';
        if (file_exists($path)) {
            $size = filesize($path);
            $sizeMb = round($size / 1024 / 1024, 2);
            if ($sizeMb > 10) {
                file_put_contents($path, '');
            }
        }

        if (is_array($log) || is_object($log) || $log = json_decode($log, true)) {
            if ($params) {
                $urlRequest = $url;
                $tmp['sendRequest'] = $params;
                $tmp['sendRequest']['url'] = $urlRequest;
                array_unshift($log, $tmp);
            }
            error_log($header . $d . $header . print_r($log, true), 3, $path);
        } else {
            error_log($header . $d . $header . $log, 3, $path);
        }

    }

}