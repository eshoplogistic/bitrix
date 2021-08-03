<?php
namespace Webnauts\EshopLogistic;

use \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

/** Class for setup config options
 * Class Config
 * @package Webnauts\EshopLogistic
 * @copyright webnauts.pro
 * @author negen
 */
class Config
{
    const MODULE_ID = 'webnauts.eshoplogistic';
    const DELIVERY_CODE = 'eslogistic';
    const CACHE_TIME = 3600;
    const CACHE_DIR = 'eshoplogistic';
    public $pvzBalloonLang;
    public $priceError;
    public $locationError;


    public function __construct()
    {

        $this->profileClasses = array(
            'baikal_door'   => 'Webnauts\EshopLogistic\Profile\BaikalDoor',
            'baikal_term'   => 'Webnauts\EshopLogistic\Profile\BaikalTerminal',
            'boxberry_door' => 'Webnauts\EshopLogistic\Profile\BoxberryDoor',
            'boxberry_term' => 'Webnauts\EshopLogistic\Profile\BoxberryTerminal',
            'custom_door'   =>  'Webnauts\EshopLogistic\Profile\CustomDoor',
            'custom_term'   =>  'Webnauts\EshopLogistic\Profile\CustomTerminal',
            'delline_door'  =>  'Webnauts\EshopLogistic\Profile\DellineDoor',
            'delline_term'  =>  'Webnauts\EshopLogistic\Profile\DellineTerminal',
            'dpd_door'      =>  'Webnauts\EshopLogistic\Profile\DpdDoor',
            'dpd_term'      =>  'Webnauts\EshopLogistic\Profile\DpdTerminal',
            'gtd_door'      =>  'Webnauts\EshopLogistic\Profile\GtdDoor',
            'gtd_term'      =>  'Webnauts\EshopLogistic\Profile\GtdTerminal',
            'iml_door'      =>  'Webnauts\EshopLogistic\Profile\ImlDoor',
            'iml_term'      =>  'Webnauts\EshopLogistic\Profile\ImlTerminal',
            'pecom_door'    =>  'Webnauts\EshopLogistic\Profile\PecomDoor',
            'pecom_term'    =>  'Webnauts\EshopLogistic\Profile\PecomTerminal',
            'postrf_term'   =>  'Webnauts\EshopLogistic\Profile\PostrfDoor',
            'sdek_door'     =>  'Webnauts\EshopLogistic\Profile\SdekDoor',
            'sdek_term'     =>  'Webnauts\EshopLogistic\Profile\SdekTerminal',
        );

        $this->profileList = array(
            'baikal_door'   => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_BAIKAL_DOOR"),
            'baikal_term'   => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_BAIKAL_TERMINAL"),
            'boxberry_door' => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_BOXBERRY_DOOR"),
            'boxberry_term' => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_BOXBERRY_TERMINAL"),
            'custom_door'   => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_CUSTOM_DOOR"),
            'custom_term'   => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_CUSTOM_TERMINAL"),
            'delline_door'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_DELLINE_DOOR"),
            'delline_term'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_DELLINE_TERMINAL"),
            'dpd_door'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_DPD_DOOR"),
            'dpd_term'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_DPD_TERMINAL"),
            'gtd_door'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_GTD_DOOR"),
            'gtd_term'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_GTD_TERMINAL"),
            'iml_door'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_IML_DOOR"),
            'iml_term'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_IML_TERMINAL"),
            'pecom_door'    => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_PECOM_DOOR"),
            'pecom_term'    => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_PECOM_TERMINAL"),
            'postrf_term'   => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_POSTRF_TERMINAL"),
            'sdek_door'     => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_SDEK_DOOR"),
            'sdek_term'     => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_SDEK_TERMINAL"),
        );

        $this->priceError = Loc::getMessage("ESHOP_LOGISTIC_DELIVERY_PRICE_ERROR");
        $this->locationError = Loc::getMessage("ESHOP_LOGISTIC_DELIVERY_LOCATION_ERROR");
        $this->dataErrorError = Loc::getMessage("ESHOP_LOGISTIC_DELIVERY_DATA_ERROR");
    }

    /** Get delivery list for module event
     * @return array
     */
    public function getEventDeliveryList()
    {
        $path = '/'.dirname(substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])));
        $eventDeliveryList = array(

            '\Webnauts\EshopLogistic\Engine\InitDeliveryService' => $path.'/lib/engine/initdeliveryservice.php',
            '\Webnauts\EshopLogistic\Profile\BaikalDoor' => $path.'/lib/profile/baikaldoor.php',
            '\Webnauts\EshopLogistic\Profile\BaikalTerminal' => $path.'/lib/profile/baikalterminal.php',
            '\Webnauts\EshopLogistic\Profile\BoxberryDoor' => $path.'/lib/profile/boxberrydoor.php',
            '\Webnauts\EshopLogistic\Profile\BoxberryTerminal' => $path.'/lib/profile/boxberryterminal.php',
            '\Webnauts\EshopLogistic\Profile\CustomDoor' => $path.'/lib/profile/customdoor.php',
            '\Webnauts\EshopLogistic\Profile\CustomTerminal' => $path.'/lib/profile/customterminal.php',
            '\Webnauts\EshopLogistic\Profile\DellineDoor' => $path.'/lib/profile/dellinedoor.php',
            '\Webnauts\EshopLogistic\Profile\DellineTerminal' => $path.'/lib/profile/dellineterminal.php',
            '\Webnauts\EshopLogistic\Profile\DpdDoor' => $path.'/lib/profile/dpddoor.php',
            '\Webnauts\EshopLogistic\Profile\DpdTerminal' => $path.'/lib/profile/dpdterminal.php',
            '\Webnauts\EshopLogistic\Profile\GtdDoor' => $path.'/lib/profile/gtddoor.php',
            '\Webnauts\EshopLogistic\Profile\GtdTerminal' => $path.'/lib/profile/gtdterminal.php',
            '\Webnauts\EshopLogistic\Profile\ImlDoor' => $path.'/lib/profile/imldoor.php',
            '\Webnauts\EshopLogistic\Profile\ImlTerminal' => $path.'/lib/profile/imlterminal.php',
            '\Webnauts\EshopLogistic\Profile\PecomDoor' => $path.'/lib/profile/pecomdoor.php',
            '\Webnauts\EshopLogistic\Profile\PecomTerminal' => $path.'/lib/profile/pecomterminal.php',
            '\Webnauts\EshopLogistic\Profile\PostrfDoor' => $path.'/lib/profile/postrfdoor.php',
            '\Webnauts\EshopLogistic\Profile\SdekDoor' => $path.'/lib/profile/sdekdoor.php',
            '\Webnauts\EshopLogistic\Profile\SdekTerminal' => $path.'/lib/profile/sdekterminal.php',
        );

        return $eventDeliveryList;
    }

    /** Get option types of payments
     * @return mixed
     */
    public function getPaymentTypes()
    {
        $card     = Option::get(self::MODULE_ID, 'api_payment_card');
        $cache    = Option::get(self::MODULE_ID, 'api_payment_cache');
        $cashless = Option::get(self::MODULE_ID, 'api_payment_cashless');
        $prepay   = Option::get(self::MODULE_ID, 'api_payment_prepay');

        $paymentTypes['card']     = explode(',', $card);
        $paymentTypes['cache']    = explode(',', $cache);
        $paymentTypes['cashless'] = explode(',', $cashless);
        $paymentTypes['prepay']   = explode(',', $prepay);

        return $paymentTypes;
    }

}