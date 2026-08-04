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
            $this->eslWriteLog($httpResult, $this->url, $apiParams);

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

    public function eslWriteLog($log, $url, $params)
    {
        if(isset($params['target']))
            return false;

        $d = date("j-M-Y H:i:s e");
        $header = ' ####################### ';

        $path = \Bitrix\Main\Application::getDocumentRoot() . '/bitrix/tmp/eshoplogistic/esl.log';
        \CheckDirPath($path);

        $htaccess = dirname($path) . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from All");
        }

        if (file_exists($path)) {
            $size = filesize($path);
            $sizeMb = round($size / 1024 / 1024, 2);
            if ($sizeMb > 10) {
                file_put_contents($path, '');
            }
        }

        $sanitizedParams = $params;
        unset($sanitizedParams['key'], $sanitizedParams['partner_key']);

        if (is_array($log) || is_object($log) || $log = json_decode($log, true)) {
            $logRecord = [
                'request'  => $sanitizedParams,
                'response' => $log,
            ];
            error_log($header . $d . $header . print_r($logRecord, true), 3, $path);
        } else {
            error_log($header . $d . $header . $log, 3, $path);
        }

    }

}