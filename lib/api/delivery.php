<?
namespace Eshoplogistic\Delivery\Api;

use \Bitrix\Main\Application,
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

    /**
     * @param string $service
     * @return Client
     */
    private static function getHttpClient($service)
    {
        $configClass = new Config();
        $apiV = $configClass->apiV;
        if($apiV){
            $apiObject = 'delivery/calculation';
        }else{
            $apiObject = 'delivery/'.$service;
        }
        $httpClient = new Client($apiObject);
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

        $cacheKey = self::$cacheKey.'-'.$currentUser.'-'.$service;
        $cache = Application::getInstance()->getManagedCache();

        if ($cache->read(self::$cacheTime, $cacheKey, self::$cacheDir)) {

            $vars = $cache->get($cacheKey);

            if($vars['hash'] === $basketHash) {
                $currentDeliveryData = $vars['data'];
            } else {
                /**
                 * Clear cache when basket is changed
                 */
                $requestDeliveryData = self::getDeliveryData($orderData, $from, $to, $service, $basketHash);
                $cache->clean($cacheKey, self::$cacheDir);
                $cache->set($cacheKey, $requestDeliveryData);
                $currentDeliveryData = $requestDeliveryData['data'];
            }
        } else {
            $requestDeliveryData = self::getDeliveryData($orderData, $from, $to, $service, $basketHash);
            $cache->set($cacheKey, $requestDeliveryData);
            $currentDeliveryData = $requestDeliveryData['data'];
        }
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
        $requestData['from'] = $from;
        $requestData['to'] = $to;
        $requestData['all_comments'] = 2;
        $configClass = new Config();
        $apiV = $configClass->apiV;
        if($apiV){
            $requestData['service'] = $service;
            $requestData['debug'] = 1;
            unset($requestData['from']);
        }

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