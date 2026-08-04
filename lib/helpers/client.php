<?php

namespace Eshoplogistic\Delivery\Helpers;

use \Bitrix\Main\Config\Option,
    \Bitrix\Main\Web\HttpClient,
    \Eshoplogistic\Delivery\Config,
    \Eshoplogistic\Delivery\Logger\Logger;

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
    private $partnerKey = Config::PARTNER_KEY;

    function __construct($apiObject)
    {

        $this->httpClient = new HttpClient();
        $this->httpClient->setTimeout(5);
        $this->url = 'https://api.esplc.ru/' . $apiObject;
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
        global $APPLICATION;
        $apiParams['key'] = $this->apiKey;
        $apiParams['partner_key'] = $this->partnerKey;

        if (strtolower(SITE_CHARSET) != 'utf-8') {
            $apiParams = $APPLICATION->ConvertCharsetArray($apiParams, SITE_CHARSET, 'utf-8');
        }

        $querySuccess = $this->httpClient->query($httpMethod, $this->url, $apiParams);
        $httpResult = $this->httpClient->getResult();

        if (!$querySuccess) {
            // Повторяем запрос только при настоящем сетевом сбое (query() вернул false).
            // Раньше проверялось !$httpResult, из-за чего валидный, но пустой/"0" ответ
            // сервера трактовался как ошибка и запрос (в т.ч. создание отправления у ТК) дублировался.
            $httpResult = $this->alternativeCurlPost($this->url, $apiParams);
        }

        if($this->log == 'Y')
            $this->eslWriteLog($httpResult, $this->url, $apiParams, $querySuccess);

        $result = json_decode($httpResult);
        if ($result)
            return \Bitrix\Main\Web\Json::decode($httpResult);
    }

    public function alternativeCurlPost($url, $body = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    public function eslWriteLog($log, $url, $params, $querySuccess = true)
    {
        if(isset($params['target']))
            return false;

        $sanitizedParams = $params;
        unset($sanitizedParams['key'], $sanitizedParams['partner_key']);

        if (is_array($log) || is_object($log)) {
            $response = $log;
        } else {
            $decoded = json_decode($log, true);
            $response = $decoded !== null ? $decoded : $log;
        }

        $description = $url . '<br>'
            . 'Запрос:<br>' . Logger::pretty($sanitizedParams) . '<br>'
            . 'Ответ:<br>' . Logger::pretty($response);

        if ($querySuccess) {
            Logger::log('API_REQUEST', $description);
        } else {
            Logger::error('API_ERROR', $description);
        }
    }

}