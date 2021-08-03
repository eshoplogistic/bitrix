<?php
namespace Webnauts\EshopLogistic\Agent;

use \Bitrix\Main\Application,
    \Bitrix\Main\Data\Cache,
    \Webnauts\EshopLogistic\Config;;

/** Agents for cache managing
 * Class CacheHandler
 * @package Webnauts\EshopLogistic\Agent
 * @copyright webnauts.pro
 * @author negen
 */

class CacheHandler
{
    static $cacheDir  = Config::CACHE_DIR;

    /** Agent for clearing cache and managed cache directories
     * @return string
     */
    public function clean()
    {

        $cache = Cache::createInstance();
        $cache->CleanDir(Config::CACHE_DIR);

        $managedCahe = Application::getInstance()->getManagedCache();
        $managedCahe->cleanDir( Config::CACHE_DIR);

        return "Webnauts\EshopLogistic\Agent\CacheHandler::clean();";
    }
}