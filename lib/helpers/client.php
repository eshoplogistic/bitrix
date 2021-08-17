<?php
namespace Eshoplogistic\Delivery\Helpers;

use \Bitrix\Main\Config\Option,
    \Bitrix\Main\Web\HttpClient,
    \Eshoplogistic\Delivery\Config;

/** Класс для обмена данными с eShopLogistic
 * Class Client
 * @package Eshoplogistic\Delivery\Helpers
 * @author negen
 */

class Client
{
    private $httpClient;
    private $url;
    private $apiKey;
    private $isLog;

    function __construct($apiObject) {

        $this->httpClient = new HttpClient();
        $this->url = 'https://api.eshoplogistic.ru/api/'.$apiObject;
        $this->apiKey = Option::get(Config::MODULE_ID, 'api_key');

        $this->isLog = Option::get(Config::MODULE_ID, 'is_log');
    }

    /** Http - запрос для обмена данными с eSputnik
     * @param string $httpMethod
     * @param array $apiParams
     * @return array
     */

    public function request($httpMethod, $apiParams = array())
    {
        $apiParams['key'] = $this->apiKey;
        $this->httpClient->query($httpMethod, $this->url, $apiParams, false);
        $httpResult = $this->httpClient->getResult();

        return json_decode($httpResult, true);
    }

}