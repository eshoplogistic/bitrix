<?
namespace Eshoplogistic\Delivery\Api;

use \Bitrix\Main\Data\Cache,
    \Eshoplogistic\Delivery\Config,
    \Eshoplogistic\Delivery\Helpers\Client;

/** Class for class for getting delivery price
 * Class Delivery
 * @package Eshoplogistic\Delivery\Api
 * @author negen
 */

class Delivery
{

    static $cacheTime = Config::CACHE_TIME;
    static $cacheKey   = 'deliveryLocation';
    static $cacheDir = Config::CACHE_DIR;

    // In-memory кэш на запрос — см. пояснение у формирования $cacheKey в getLocationDeliveryData().
    private static $requestMemo = [];

    // Сколько секунд держать в кэше сбой связи с API (см. getLocationDeliveryData()).
    private static $failCacheTime = 60;

    /** Ответ API получен и окончательный (успех или ошибка данных 4xx), а не сбой
     * связи/сервера, после которого стоит повторить запрос
     * @param mixed $data
     * @return bool
     */
    private static function isFinalAnswer($data)
    {
        if (!is_array($data)) {
            return false;
        }
        if (!empty($data['success'])) {
            return true;
        }
        $status = isset($data['http_status']) ? (int)$data['http_status'] : 0;
        return $status >= 200 && $status < 500 && $status !== 408 && $status !== 429;
    }

    /**
     * @param string $service
     * @return Client
     */
    private static function getHttpClient($service)
    {
        $httpClient = new Client('delivery/calculation');
        return $httpClient;
    }

    /** Getting delivery data for location
     * @param string $service
     * @param string $from
     * @param string $to
     * @param array $orderData
     * @return array
     */

    public static function getLocationDeliveryData($service, $from, $to, $orderData)
    {

        global $USER;
        $currentUser = $USER->GetID();
        $basketEncrypt = $orderData;
        $basketEncrypt['from'] = $from;
        $basketEncrypt['to'] = $to;

        if($currentUser) {
            $currentUser = 'U-'. $currentUser;
        } else {
            $currentUser = 'F-'. \Bitrix\Sale\Fuser::getId();
        }

        $encodeData = $basketEncrypt;
        $encodeData['from'] = $from;
        $encodeData['to'] = $to;


        $serialized = serialize($encodeData);
        $basketHash = hash('md5', $serialized);

        // Хэш — часть ключа, а не отдельное поле для сверки внутри одной ячейки кэша. Раньше
        // ключ был только 'user-service', и при смене состояния корзины/оплаты код не заводил новую
        // запись, а перезаписывал ту же ячейку (см. ветку "хэш не совпал" ниже, которая была). Bitrix
        // Sale (sale.order.ajax) на одном построении заказа несколько раз подряд пересчитывает
        // CHECKED-доставку (SaleOrderAjax::getOrder(): initDelivery() -> recalculatePayment() ->
        // calculateDeliveries()), и способ оплаты между первым и последующими проходами меняется
        // (empirически: payment="" -> payment="card") — то есть у ОДНОГО рендера легитимно два разных
        // $basketHash. Из-за общей на user+service ячейки второй проход затирал то, что записал
        // первый, а на СЛЕДУЮЩЕМ рендере первый проход снова заставал в кэше чужой (последний)
        // хэш — кэш промахивался на каждом единственном рендере, независимо от TTL. Теперь у каждого
        // варианта входных данных своя ячейка — переиспользуются оба между рендерами, устаревшие
        // сами истекут по TTL.
        $cacheKey = self::$cacheKey.'-'.$currentUser.'-'.$service.'-'.$basketHash;

        if (array_key_exists($cacheKey, self::$requestMemo)) {
            return self::$requestMemo[$cacheKey];
        }

        $cache = Cache::createInstance();
        $isCached = false;

        if ($cache->initCache(self::$cacheTime, $cacheKey, self::$cacheDir)) {
            $vars = $cache->getVars();
            if (empty($vars['failed_at']) || time() - $vars['failed_at'] < self::$failCacheTime) {
                $currentDeliveryData = $vars['data'];
                $isCached = true;
            } else {
                $cache->clean($cacheKey, self::$cacheDir);
            }
        }

        if (!$isCached) {
            $requestDeliveryData = self::getDeliveryData($orderData, $from, $to, $service, $basketHash);
            $currentDeliveryData = $requestDeliveryData['data'];
            // Сбой связи/5xx/429 раньше кэшировался на весь CACHE_TIME — служба час была "без
            // расчёта", а повторные выборы до API не доходили и не попадали в лог. Совсем не
            // кэшировать тоже нельзя: при SHOW_NOT_CALCULATED_DELIVERIES = L/N sale.order.ajax
            // считает ВСЕ службы на каждом обновлении, и при недоступном API каждая ждала бы
            // таймаут. Поэтому сбой живёт в кэше $failCacheTime (метка в данных — срок жизни
            // записи разные движки кэша проверяют по-разному). Окончательный ответ API, в т.ч.
            // 4xx "ТК не возит в этот город", кэшируется как обычно.
            if (!self::isFinalAnswer($currentDeliveryData)) {
                $requestDeliveryData['failed_at'] = time();
            }
            if ($cache->startDataCache(self::$cacheTime, $cacheKey, self::$cacheDir)) {
                $cache->endDataCache($requestDeliveryData);
            }
        }

        self::$requestMemo[$cacheKey] = $currentDeliveryData;
        return $currentDeliveryData;
    }

    /** Prepare params for http query
     * @param $orderData
     * @param $from
     * @param $to
     * @param $service
     * @param $basketHash
     * @return array
     */

    private static function getDeliveryData($orderData, $from, $to, $service, $basketHash)
    {
        $requestData = $orderData;
        $requestData['to'] = $to;
        $requestData['all_comments'] = 2;
        $requestData['service'] = $service;
        $requestData['debug'] = 1;

        $deliveryRequest = self::query($service, $requestData);
        if(isset($deliveryRequest['debug']))
            $deliveryRequest['data']['debug'] = $deliveryRequest['debug'];

        $deliveryLocation = array(
            'data' => $deliveryRequest,
            'hash' => $basketHash
        );
        return $deliveryLocation;
    }

    /** Send http query
     * @param $service
     * @param $requestData
     * @return array
     */

    private static function query($service, $requestData)
    {
        $httpClient = self::getHttpClient($service);
        $httpMethod = 'POST';
        return $httpClient->request($httpMethod, $requestData);
    }
}
?>