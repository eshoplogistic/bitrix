<?php
namespace Eshoplogistic\Delivery\Agent;

use \Bitrix\Main\Application,
    \Bitrix\Main\Data\Cache,
    \Eshoplogistic\Delivery\Config;;

/** Agents for cache managing
 * Class CacheHandler
 * @package Eshoplogistic\Delivery\Agent
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

        return "Eshoplogistic\Delivery\Agent\CacheHandler::clean();";
    }
}