<?
namespace Eshoplogistic\Delivery\Api;

use Bitrix\Main\Config\Option;
use \Bitrix\Main\Data\Cache,
    \Eshoplogistic\Delivery\Config,
    \Eshoplogistic\Delivery\Helpers\Client;

/** Class for getting status of authorization, deg=fault settings and account balance
 * Class Site
 * @package Eshoplogistic\Delivery\Api
 * @author negen
 */

class Site
{

    static $cacheTime = Config::CACHE_TIME;
    static $cacheDir  = Config::CACHE_DIR;
    static $cacheKey   = 'sendpoint';
    /**
     * @param string $service
     * @return Client
     */

    private static function getHttpClient()
    {
        $configClass = new Config();
        $apiV = $configClass->apiV;

        if($apiV){
            $apiObject = 'client/state';
        }else{
            $apiObject = 'site';
        }

        $httpClient = new Client($apiObject);
        return $httpClient;
    }

    /** Getting status of authorization and account balance
     * @return array
     */
    public function getAuthStatus()
    {
        $httpClient = self::getHttpClient();
        $httpMethod = 'POST';
        $params = array();
        $response = $httpClient->request($httpMethod, $params);
        $configClass = new Config();
        $apiV = $configClass->apiV;

        $result = array(
            'success'   => ($apiV)?$response['http_status_message']:$response['success'],
            'blocked'   => $response['data']['blocked'],
            'free_days' => $response['data']['free_days'],
            'balance'   => $response['data']['balance'],
            'paid_days' => $response['data']['paid_days'],
            'settings'  => $response['data']['services'],
        );
        return $result;
    }

    /** Getting default setting of send point
     * @return array|bool
     */
    public static function getSendPoint()
    {

        $cacheKey = self::$cacheKey;
        $cache = Cache::createInstance();

        if ($cache->initCache(self::$cacheTime, $cacheKey, self::$cacheDir)) {
            $vars = $cache->getVars();
            return ($vars['sendpoint']);
        } elseif ($cache->startDataCache()) {
            $httpClient = self::getHttpClient();
            $httpMethod = 'POST';
            $params = array();
            $response = $httpClient->request($httpMethod, $params);
            if($response['success'] && $response['data']['settings']['city_fias']) {
                $result =  array(
                    'city_fias' => $response['data']['settings']['city_fias'],
                    'city_name' => $response['data']['settings']['city_name'],
                    'services'  => $response['data']['services']
                );
                $cache->endDataCache(array("sendpoint" => $result));
            }
            if(isset($response['http_status']) && $response['http_status'] == 200){
                $result =  array(
                    'services'  => $response['data']['services']
                );
                $cache->endDataCache(array("sendpoint" => $result));
            }
        }
        return $result;
    }
}
