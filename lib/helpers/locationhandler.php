<?
namespace Eshoplogistic\Delivery\Helpers;

use \Bitrix\Main\Loader,
    \Bitrix\Sale\Location\LocationTable,
    \Bitrix\Main\Data\Cache,
    \Eshoplogistic\Delivery\Api\Search,
    \Eshoplogistic\Delivery\Config;

/** Class for handing deliveries by location
 * Class LocationHandler
 * @package Eshoplogistic\Delivery\Helpers
 * @author negen
 */

class LocationHandler
{

    static $cacheTime = Config::CACHE_TIME;
    static $cacheDir  = Config::CACHE_DIR;
    static $cacheKeyFias   = 'fias';
    static $cacheKeyCityList = 'citylist';

    /** Getting available deliveries by location
     * @param string $fias
     * @return array
     */
    public function getAvailableDeliveriesByFias($fias)
    {
        $cacheKey = self::$cacheKeyFias.'-'.$fias;
        $cache = Cache::createInstance();

        if ($cache->initCache(self::$cacheTime, $cacheKey, self::$cacheDir)) {
            $vars = $cache->getVars();
            return ($vars['fias']);
        } elseif ($cache->startDataCache()) {
            $city = Search::getCity($fias);
            $cityDeliveries = array_shift($city['data']);

            if ($city['success'] == true) {
                $cache->endDataCache(array("fias" => $cityDeliveries));
                return $cityDeliveries;
            }
        }

    }

    /** Getting available deliveries by location
     * @param string $locationCode
     * @return array
     */
    public function getAvailableDeliveriesByLocation($locationCode)
    {
        $cityDeliveries = array();
        if(Loader::includeModule('sale')) {


            $lang = strtoupper(LANGUAGE_ID);
            $region = '';
            $subregion = '';
            $name = '';

            $res = LocationTable::getList(array(
                'filter' => array(
                    '=CODE' => $locationCode,
                    '=PARENTS.NAME.LANGUAGE_ID' => $lang,
                    '=PARENTS.TYPE.NAME.LANGUAGE_ID' => $lang,
                    '!PARENTS.TYPE.CODE' => 'COUNTRY'
                ),
                'select' => array(
                    'I_ID' => 'PARENTS.ID',
                    'I_NAME_RU' => 'PARENTS.NAME.NAME',
                    'I_TYPE_CODE' => 'PARENTS.TYPE.CODE',
                    'I_TYPE_NAME_RU' => 'PARENTS.TYPE.NAME.NAME'
                ),
                'order' => array(
                    'PARENTS.DEPTH_LEVEL' => 'asc'
                )
            ));
            while($itemLocation = $res->fetch())
            {

                if($itemLocation['I_TYPE_CODE'] == 'REGION') {
                    $region = $itemLocation['I_NAME_'.$lang];
                } elseif ($itemLocation['I_TYPE_CODE'] == 'SUBREGION') {
                    $subregion = $itemLocation['I_NAME_'.$lang];
                } else {
                    $name = $itemLocation['I_NAME_'.$lang];
                }

                $arName = explode(' ', $name);

            }
            if($arName) {

                $hash = hash('md5', $arName[0]);
                $cacheKey = self::$cacheKeyCityList.'-'.$hash;
                $cache = Cache::createInstance();

                if ($cache->initCache(self::$cacheTime, $cacheKey, self::$cacheDir)) {
                    $vars = $cache->getVars();
                    $cityDeliveries = $vars['citylist'];

                } elseif ($cache->startDataCache()) {
                    $cityList = Search::getCity($arName[0]);
                    $cityDeliveries = self::parseSelectedCity($cityList, $arName[0], $subregion, $region);

                    if ($cityList['success'] == true) {
                        $cache->endDataCache(array("citylist" => $cityDeliveries));

                    }
                }

            }

        }
        return $cityDeliveries;

    }

    /** Selecting the city from list
     * @param array $deliveryRequest
     * @param string $name
     * @param string $subregion
     * @param string $region
     * @return array
     */
    public function parseSelectedCity($deliveryRequest, $name, $subregion, $region)
    {

        $cities = $deliveryRequest['data'];

        if(count($deliveryRequest['data']) == 1) {
            $cities = $deliveryRequest['data'];
        }
        foreach ($name as $part) {

            $cities = self::checkName($part, $cities);
        }

        if(count($cities) > 1) {
            $searchBySubregion = array();
            foreach ($cities as $city) {
                if($city['sub_region'] == $subregion) {
                    $searchBySubregion[] = $city;
                }
            }
            $cities = $searchBySubregion;

        }

        if(count($cities) > 1) {
            $searchByRegion = array();
            foreach ($cities as $city) {
                if($city['region'] == $region) {
                    $searchByRegion[] = $city;
                }
            }
            $cities = $searchByRegion;

        }

        if(count($cities) > 1) {
            $searchByName = array();
            foreach ($cities as $city) {
                if($city['name'] == $name) {
                    $searchByName[] = $city;
                }
            }
            $cities = $searchByName;

        }


        if(count($cities) == 1) {
            $cities = $cities[0];
        } else {
            $cities = array();
        }


        return $cities;
    }

    /** Compare city name
     * @param string $part
     * @param array $cities
     * @return array
     */
    private function checkName($part, $cities)
    {
        $result = array();
        foreach ($cities as $city) {
            if(mb_strpos($city['name'], $part) !== false) {

                $result[] = $city;
            };
        }
        if($result) {
            return $result;
        } else {
            return $cities;
        }
    }
}