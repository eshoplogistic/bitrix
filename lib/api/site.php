<?
namespace Eshoplogistic\Delivery\Api;

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
        $httpClient = new Client('client/state');
        return $httpClient;
    }

    private static $rawClientStateFetched = false;
    private static $rawClientState;

    // getAuthStatus() и getSendPoint() кэшируют один и тот же ответ client/state под разными
    // ключами (авторизация/баланс и город/список служб отправки — разные срезы одного и того же
    // JSON), поэтому при холодном кэше обоих на один рендер чекаута уходило два одинаковых
    // POST на api.esplc.ru вместо одного. Файловое кэширование каждого метода (TTL, поведение
    // при неуспехе) не трогаем — только сам сетевой вызов теперь на запрос выполняется не более
    // одного раза.
    private static function fetchRawClientState()
    {
        if (!self::$rawClientStateFetched) {
            $httpClient = self::getHttpClient();
            self::$rawClientState = $httpClient->request('POST', array());
            self::$rawClientStateFetched = true;
        }
        return self::$rawClientState;
    }

    /** Getting status of authorization and account balance
     * @return array
     */
    public function getAuthStatus()
    {
        $cacheKey = 'authstatus';
        $cache = Cache::createInstance();

        if ($cache->initCache(self::$cacheTime, $cacheKey, self::$cacheDir)) {
            $vars = $cache->getVars();
            return $vars['authstatus'];
        } elseif ($cache->startDataCache()) {
            $response = self::fetchRawClientState();

            $isSuccess = !empty($response['success']) || (isset($response['http_status']) && $response['http_status'] == 200);
            $result = array(
                'success'   => $isSuccess,
                'blocked'   => $response['data']['blocked'] ?? 0,
                'free_days' => $response['data']['free_days'] ?? 0,
                'balance'   => $response['data']['balance'] ?? 0,
                'paid_days' => $response['data']['paid_days'] ?? 0,
                'settings'  => $response['data']['services'] ?? array(),
            );
            $cache->endDataCache(array('authstatus' => $result));
            return $result;
        }

        return array();
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
            $response = self::fetchRawClientState();

            if (!empty($response['success']) && !empty($response['data']['settings']['city_fias'])) {
                $result = array(
                    'city_fias' => $response['data']['settings']['city_fias'],
                    'city_name' => $response['data']['settings']['city_name'] ?? '',
                    'services'  => $response['data']['services'] ?? array(),
                );
            } elseif (isset($response['http_status']) && $response['http_status'] == 200) {
                $result = array(
                    'services' => $response['data']['services'] ?? array(),
                );
            } else {
                $cache->abortDataCache();
                return array();
            }

            $cache->endDataCache(array('sendpoint' => $result));
            return $result;
        }
        return array();
    }
}
