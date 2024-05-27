<?
namespace Eshoplogistic\Delivery\Engine;

use \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Application,
    \Bitrix\Main\Page\Asset,
    \Bitrix\Main\Config\Option,
    \Eshoplogistic\Delivery\Config;

Loc::loadMessages(__FILE__);

/** Class for initialization of delivery service
 * Class InitDeliveryService
 * @package Eshoplogistic\Delivery\Engine
 * @author negen
 */

class InitDeliveryService extends \Bitrix\Sale\Delivery\Services\Base
{
    protected static $isCalculatePriceImmediately = true;
    protected static $whetherAdminExtraServicesShow = false;
    protected static $canHasProfiles = true;
    protected static $deliveryCode = Config::DELIVERY_CODE;
    protected static $moduleId = Config::MODULE_ID;

    public function __construct(array $initParams)
    {
        parent::__construct($initParams);
    }

    public function prepareFieldsForSaving(array $fields)
    {
        $fields["CODE"] = self::$deliveryCode;

        return parent::prepareFieldsForSaving($fields);
    }

    public static function getClassTitle()
    {
        return Loc::getMessage("ESHOP_LOGISTIC_MENU_ITEM_DELIVERY_NAME");
    }

    public static function getClassDescription()
    {
        return Loc::getMessage("ESHOP_LOGISTIC_MENU_ITEM_DELIVERY_DESC");
    }

    public function isCalculatePriceImmediately()
    {
        return self::$isCalculatePriceImmediately;
    }

    public static function whetherAdminExtraServicesShow()
    {
        return self::$whetherAdminExtraServicesShow;
    }

    public static function canHasProfiles()
    {
        return self::$canHasProfiles;
    }

    public static function getChildrenClassNames()
    {
        $config = new Config();
        if($_REQUEST['PROFILE_ID']) {
            $profile = $_REQUEST['PROFILE_ID'];
            if(isset($config->profileClasses[$profile])){
                $childrenClass = array($config->profileClasses[$profile]);
            }else{
                $childrenClass = array();
            }
        } else {
            $childrenClass = $config->profileClasses;
        }
        return $childrenClass;
    }

    public function getProfilesList()
    {
        $config = new Config();
        $profileList = $config->profileList;
        return $profileList;
    }
}