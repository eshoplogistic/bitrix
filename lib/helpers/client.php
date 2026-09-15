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
        // setTimeout() задаёт только TCP-коннект (Bitrix\Main\Web\HttpClient::$socketTimeout).
        // Чтение самого ответа (streamTimeout) без явной настройки живёт по умолчанию 60 секунд
        // (HttpClient::DEFAULT_STREAM_TIMEOUT) — если api.esplc.ru принял соединение, но медленно
        // отдаёт/подвешивает ответ, calculate() у профиля доставки может блокировать рендер
        // чекаута (или само оформление заказа) почти на минуту, прежде чем сработает fallback
        // на raw curl ниже. Ограничиваем и чтение ответа тем же бюджетом, что и коннект.
        $this->httpClient->setTimeout(5);
        $this->httpClient->setStreamTimeout(5);
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

        // Пишем сам запрос ОТДЕЛЬНОЙ записью ДО обращения к api.esplc.ru — то есть до
        // сетевого таймаута/обрыва/любой другой причины, по которой строка с ответом
        // может не записаться целиком или вообще потеряться (на проде наблюдали случаи,
        // когда DESCRIPTION обрывался сразу после URL — причину со стороны кода не
        // подтвердили, но именно запрос — то, что нужнее всего для диагностики: что
        // реально ушло в API, — не должен зависеть от судьбы записи с ответом).
        $this->eslWriteRequestLog($apiParams);

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
        // Это уже fallback после сбоя основного клиента — держим его в том же бюджете (5с
        // на весь запрос, CURLOPT_TIMEOUT ограничивает коннект+чтение вместе), а не даём ему
        // отдельные 10 секунд поверх уже потраченных на первую попытку.
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    /** Пишет исходящий запрос отдельной, самодостаточной записью — до сетевого вызова
     * (см. вызов из request()). Специально не зависит от ответа сервера: именно это
     * содержимое нужнее всего при разборе "что реально ушло в API" (например, ушёл ли
     * address для Dostavista), и оно не должно теряться вместе с записью про ответ.
     * @param array $params
     */
    private function eslWriteRequestLog(array $params): void
    {
        if (!Logger::isEnabled() || isset($params['target'])) {
            return;
        }

        $sanitizedParams = $params;
        unset($sanitizedParams['key'], $sanitizedParams['partner_key']);

        Logger::log('API_REQUEST', $this->url . '<br>' . 'Запрос:<br>' . Logger::pretty($sanitizedParams));
    }

    /** Пишет ответ сервера отдельной записью (см. eslWriteRequestLog() для запроса).
     */
    public function eslWriteLog($log, $url, $params, $querySuccess = true)
    {
        if(isset($params['target']))
            return false;

        if (is_array($log) || is_object($log)) {
            $response = $log;
        } else {
            $decoded = json_decode($log, true);
            $response = $decoded !== null ? $decoded : $log;
        }

        $description = $url . '<br>' . 'Ответ:<br>' . Logger::pretty(self::trimBulkyFields($response));

        if ($querySuccess) {
            Logger::log('API_RESPONSE', $description);
        } else {
            Logger::error('API_ERROR', $description);
        }
    }

    /** Список ПВЗ ("terminals") в ответе delivery/calculation — тот же массив, который
     * CalculateHandler всё равно выбрасывает сразу после получения (unset перед расчётом,
     * см. calculatehandler.php) и не использует для цены/срока. При этом это САМАЯ
     * объёмная часть ответа (десятки точек с полным адресом/телефонами/координатами
     * каждая) — в лог кладём только количество, а не все точки целиком.
     * @param mixed $response
     * @return mixed
     */
    private static function trimBulkyFields($response)
    {
        if (is_array($response) && isset($response['data']['terminals']) && is_array($response['data']['terminals'])) {
            $response['data']['terminals'] = '[скрыто в логе: ' . count($response['data']['terminals']) . ' шт.]';
        }

        return $response;
    }

}