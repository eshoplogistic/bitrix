<?php
namespace Eshoplogistic\Delivery\Controller;

use Bitrix\Main\Config\Option;
use \Bitrix\Main\Engine\Controller,
    \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Application,
    \Bitrix\Main\Data\Cache,
    \Bitrix\Sale\Delivery\Services\Table,
    \Eshoplogistic\Delivery\Config;
use Bitrix\Main\Request;
use Bitrix\Main\Web\Json;
use Eshoplogistic\Delivery\Event\Unloading;
use Eshoplogistic\Delivery\Helpers\OrderHandler;

Loader::includeModule('sale');

Loc::loadMessages(__FILE__);

/** Class for getting PVZ by ajax request
 * Class AjaxHandler
 * @package Eshoplogistic\Delivery\Controller
 * @author negen
 */

class AjaxHandler extends Controller
{
    static $cacheTime = Config::CACHE_TIME;
    static $cacheDir  = Config::CACHE_DIR;
    static $cacheKey   = 'pvzlist';


    /**
     * @return array
     */
    public function configureActions()
    {
        return [
            'getPvzList' => [
                'prefilters' => []
            ],
            'getDefaultCity' => [
                'prefilters' => []
            ],
            'widgetData' => [
                'prefilters' => []
            ],
            'unloadingForm' => [
                'prefilters' => []
            ]
        ];
    }

    /**
     * Clearing cache and managed cache directories
     * return string
     */
    public function clearCacheAction()
    {
        $cache = Cache::createInstance();
        $cache->CleanDir(Config::CACHE_DIR);

        $managedCahe = Application::getInstance()->getManagedCache();
        $managedCahe->cleanDir( Config::CACHE_DIR);

        return Loc::getMessage('ESHOP_LOGISTIC_OPTIONS_CLEAR_CACHE_RESULT');
    }

    /** Getting PVZ list for sale.order.ajax component (popup)
     * @param string $profileId
     * @param string $locationCode
     * @param integer $paymentId
     * @return array
     */
    public static function getPvzListAction($profileId= '', $locationCode = '', $paymentId = 0)
    {
        $pvz = array();
        if(!$profileId || !$locationCode) return $pvz;

        $rsDelivery = Table::getList(array(
            'filter' => array('ACTIVE'=>'Y', 'ID' => $profileId),
            'select' => array('CODE')
        ));

        if($profile = $rsDelivery->fetch()) {
            $profileClass = self::getProfileClassByCode($profile['CODE']);
        }

        $cacheKey = self::$cacheKey.'-'.$profileClass.'-'.$locationCode;
        $cache = Cache::createInstance();

        if ($cache->initCache(self::$cacheTime, $cacheKey, self::$cacheDir)) {
            $vars = $cache->getVars();
            return ($vars['pvz']);
        } elseif ($cache->startDataCache()) {
            $pvz = $profileClass::getPvzData($locationCode, $paymentId);

            if ($pvz['success'] == true) {
                $cache->endDataCache(array("pvz" => $pvz));
            }
        }

        return [
            $pvz,
        ];
    }

    /** Getting deliveri profile class by code
     * @param string $profileCode
     * @return mixed
     */
    private static function getProfileClassByCode($profileCode) {
        $profileCode = array_pop(explode(':', $profileCode));
        $config = new Config();
        $classList = $config->profileClasses;
        return $classList[$profileCode];
    }

    public function getDefaultCityAction(){
        $locationCode = OrderHandler::getCodeCityByApi();
        return [
            $locationCode,
        ];
    }

    public static function widgetDataAction()
    {
        $out    = [];
        $request = Application::getInstance()->getContext()->getRequest();

        $method = $request->getPost("method");

        if ( ! empty( $method ) ) {
            $query_data = @$_POST;
            unset( $query_data['method'] );
            $cache_key  = md5( $method . json_encode( $query_data ) );
            $cache = Cache::createInstance();
            $cache_data = $cache->initCache(Config::CACHE_TIME, $cache_key, Config::CACHE_DIR);

            if ( ! empty( $cache_data ) ) {
                $out = $cache->getVars();
            } elseif($cache->startDataCache()) {
                $raw = ( $method == 'widget/send' ) ? $request->getPost( 'raw' ) : '';

                if ( $requestOut = self::ApiQuery( trim( $method ), $query_data, $raw ) ) {
                    if ( ! empty( $requestOut ) && $requestOut['http_status'] == 200 ) {
                        $cache->endDataCache($requestOut);
                    }
                    $out = $requestOut;
                }
            }
        }

        $json = Json::encode( $out );
        echo $json;
        exit();

    }



    public static function ApiQuery( string $method, array $data = [], string $raw = '' ) {

        $calculation = false;


        $apiKey = Option::get(Config::MODULE_ID, 'api_key');
        if ( empty( $apiKey ) ) {
            return [];
        }

        $apiUrl = Config::API_UNLOADIG;
        if ( empty( $apiUrl ) ) {
            return [];
        }


        $lc = substr( $apiUrl, - 1 );
        if ( $lc != '/' ) {
            $apiUrl .= '/';
        }

        $curl = curl_init();
        curl_setopt( $curl, CURLOPT_URL, $apiUrl . $method );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
        curl_setopt( $curl, CURLOPT_POST, 1 );
        if ( preg_match( '/widget/', $method ) ) {
            # заказ из виджета отправляется в raw
            if ( $method == 'widget/send' ) {
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $raw );
                curl_setopt( $curl, CURLOPT_HTTPHEADER, [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                    'Content-Type: application/json'
                ] );
            } elseif ( $method == 'widget/calculation' ) {
                $encoded        = json_decode( stripslashes( $data['offers'] ) );
                $data['offers'] = json_encode( $encoded );
                $data['debug']  = 1;
                $calculation    = true;
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
                curl_setopt( $curl, CURLOPT_HTTPHEADER, [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                ] );
            } else {
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
                curl_setopt( $curl, CURLOPT_HTTPHEADER, [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                ] );
            }
        } else {
            # выгрузка заказа в raw
            if ( $method == 'delivery/order' ) {
                $raw = json_encode( array_merge( $data, [ 'key' => $apiKey ] ) );
                curl_setopt( $curl, CURLOPT_POSTFIELDS, $raw );
                curl_setopt( $curl, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json'
                ] );
            } else {
                curl_setopt( $curl, CURLOPT_POSTFIELDS, array_merge( $data, [ 'key' => $apiKey ] ) );
            }
        }

        $result = curl_exec( $curl );
        curl_close( $curl );


        if ( $result = \Bitrix\Main\Web\Json::decode($result) ) {
            if ( is_array( $result ) ) {

                if ( $calculation && isset( $result['debug'] ) ) {
                    if(isset($result['data']['terminal']['price']['value']) && !is_int($result['data']['terminal']['price']['value'])){
                        $result['data']['terminal']['price']['value'] = (int)$result['data']['terminal']['price']['value'];
                    }
                    if(isset($result['data']['door']['price']['value']) && !is_int($result['data']['door']['price']['value'])){
                        $result['data']['door']['price']['value'] = (int)$result['data']['door']['price']['value'];
                    }
                    $keyWidget = explode( ':', $data['key'] );
                    $cacheJson = array(
                        'city' => $data['to'],
                        'key'  => $keyWidget[0],
                        'service' => $data['service']
                    );
                    $cache_key = md5( $method . json_encode( $cacheJson ) );
                    $cache = Cache::createInstance();
                    $cache->initCache(Config::CACHE_TIME, $cache_key, Config::CACHE_DIR);

                    if($cache->startDataCache()){
                        $cache->endDataCache($result);
                    }
                }

                return $result;
            }
        }

        return false;
    }

    public static function unloadingFormAction()
    {
        if(!$_POST)
            return false;

        $request = $_POST;
        $unloading = new Unloading();

        $result = $unloading->params_delivery_init($request);
        if(isset($result['errors'])){
            echo json_encode(['success' => false, 'errors' => $result['errors']]);
            exit();
        }else{
           return $result['http_status_message'];
        }
    }

}