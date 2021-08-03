<?php
namespace Webnauts\EshopLogistic\Controller;

use \Bitrix\Main\Engine\Controller,
    \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Application,
    \Bitrix\Main\Data\Cache,
    \Bitrix\Sale\Delivery\Services\Table,
    \Webnauts\EshopLogistic\Config;

Loader::includeModule('sale');

Loc::loadMessages(__FILE__);

/** Class for getting PVZ by ajax request
 * Class AjaxHandler
 * @package Webnauts\EshopLogistic\Controller
 * @copyright webnauts.pro
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
    private function getProfileClassByCode($profileCode) {
        $profileCode = array_pop(explode(':', $profileCode));
        $config = new Config();
        $classList = $config->profileClasses;
        return $classList[$profileCode];
    }

}