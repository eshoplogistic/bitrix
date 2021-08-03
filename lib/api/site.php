<?
namespace Webnauts\EshopLogistic\Api;

use \Bitrix\Main\Data\Cache,
    \Webnauts\EshopLogistic\Config,
    \Webnauts\EshopLogistic\Helpers\Client;

/** Class for getting status of authorization, deg=fault settings and account balance
 * Class Site
 * @package Webnauts\EshopLogistic\Api
 * @copyright webnauts.pro
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
    private function getHttpClient()
    {
        $apiObject = 'site';
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

        $result = array(
            'success'   => $response['success'],
            'blocked'   => $response['data']['blocked'],
            'free_days' => $response['data']['free_days'],
            'balance'   => $response['data']['balance'],
            'paid_days' => $response['data']['paid_days'],
        );
        return $result;
    }

    /** Getting default setting of send point
     * @return array|bool
     */
    public function getSendPoint()
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
        }
        return $result;
    }
}
?>