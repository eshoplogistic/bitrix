<?php
namespace Eshoplogistic\Delivery;

use \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option;
use Logger;

Loc::loadMessages(__FILE__);

/** Class for setup config options
 * Class Config
 * @package Eshoplogistic\Delivery
 * @author negen
 */
class Config
{
	const MODULE_ID = 'eshoplogistic.delivery';
	const PATH_MODULE = '//bitrix/modules/eshoplogistic.delivery';
	const DELIVERY_CODE = 'eslogistic';
	const CACHE_TIME = 3600;
	const CACHE_DIR = 'eshoplogistic';
	const API_UNLOADIG = 'https://api.esplc.ru/';
	public $pvzBalloonLang;
	public $priceError;
	public $locationError;
	public $apiV = 0;


	public function __construct()
	{
        $apiV = Option::get(Config::MODULE_ID, 'api_v2');
        $this->apiV = isset($apiV) && $apiV == 'Y';

        $this->profileClasses = array(
			'baikal_door'   =>  'Eshoplogistic\Delivery\Profile\BaikalDoor',
			'baikal_term'   =>  'Eshoplogistic\Delivery\Profile\BaikalTerminal',
			'boxberry_door' =>  'Eshoplogistic\Delivery\Profile\BoxberryDoor',
			'boxberry_term' =>  'Eshoplogistic\Delivery\Profile\BoxberryTerminal',
			'custom_door'   =>  'Eshoplogistic\Delivery\Profile\CustomDoor',
			'custom_term'   =>  'Eshoplogistic\Delivery\Profile\CustomTerminal',
			'delline_door'  =>  'Eshoplogistic\Delivery\Profile\DellineDoor',
			'delline_term'  =>  'Eshoplogistic\Delivery\Profile\DellineTerminal',
			'dpd_door'      =>  'Eshoplogistic\Delivery\Profile\DpdDoor',
			'dpd_term'      =>  'Eshoplogistic\Delivery\Profile\DpdTerminal',
			'gtd_door'      =>  'Eshoplogistic\Delivery\Profile\GtdDoor',
			'gtd_term'      =>  'Eshoplogistic\Delivery\Profile\GtdTerminal',
			'iml_door'      =>  'Eshoplogistic\Delivery\Profile\ImlDoor',
			'iml_term'      =>  'Eshoplogistic\Delivery\Profile\ImlTerminal',
			'pecom_door'    =>  'Eshoplogistic\Delivery\Profile\PecomDoor',
			'pecom_term'    =>  'Eshoplogistic\Delivery\Profile\PecomTerminal',
			'postrf_term'   =>  'Eshoplogistic\Delivery\Profile\PostrfTerminal',
			'postrf_door'   =>  'Eshoplogistic\Delivery\Profile\PostrfDoor',
			'sdek_door'     =>  'Eshoplogistic\Delivery\Profile\SdekDoor',
			'sdek_term'     =>  'Eshoplogistic\Delivery\Profile\SdekTerminal',
			'ozon_door'     =>  'Eshoplogistic\Delivery\Profile\OzonDoor',
			'ozon_term'     =>  'Eshoplogistic\Delivery\Profile\OzonTerminal',
			'zde_door'      =>  'Eshoplogistic\Delivery\Profile\ZdeDoor',
			'zde_term'      =>  'Eshoplogistic\Delivery\Profile\ZdeTerminal',
			'picpoint_term' =>  'Eshoplogistic\Delivery\Profile\PicpointTerminal',
			'energija_door' =>  'Eshoplogistic\Delivery\Profile\EnergijaDoor',
			'energija_term' =>  'Eshoplogistic\Delivery\Profile\EnergijaTerminal',
			'vozovoz_door'  =>  'Eshoplogistic\Delivery\Profile\VozovozDoor',
			'vozovoz_term'  =>  'Eshoplogistic\Delivery\Profile\VozovozTerminal',
			'grastin_door'  =>  'Eshoplogistic\Delivery\Profile\GrastinDoor',
			'grastin_term'  =>  'Eshoplogistic\Delivery\Profile\GrastinTerminal',
			'fivepost_term' =>  'Eshoplogistic\Delivery\Profile\FivepostTerminal',
			'sberlogistics_door'  =>  'Eshoplogistic\Delivery\Profile\SberlogisticsDoor',
			'sberlogistics_term'  =>  'Eshoplogistic\Delivery\Profile\SberlogisticsTerminal',
			'yandex_door'  =>  'Eshoplogistic\Delivery\Profile\YandexDoor',
			'yandex_term'  =>  'Eshoplogistic\Delivery\Profile\YandexTerminal',
            'dostavista_door'  =>  'Eshoplogistic\Delivery\Profile\DostavistaDoor',
            'kit_door'  =>  'Eshoplogistic\Delivery\Profile\KitDoor',
            'kit_term'  =>  'Eshoplogistic\Delivery\Profile\KitTerminal',
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
			'postrf_door'   => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_POSTRF_DOOR"),
			'sdek_door'     => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_SDEK_DOOR"),
			'sdek_term'     => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_SDEK_TERMINAL"),
			'ozon_door'     => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_OZON_DOOR"),
			'ozon_term'     => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_OZON_TERMINAL"),
			'zde_door'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_ZDE_DOOR"),
			'zde_term'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_ZDE_TERMINAL"),
			'picpoint_term' => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_PICPOINT_TERMINAL"),
			'energija_door' => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_ENERGIJA_DOOR"),
			'energija_term' => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_ENERGIJA_TERMINAL"),
			'vozovoz_door'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_VOZOVOZ_DOOR"),
			'vozovoz_term'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_VOZOVOZ_TERMINAL"),
			'grastin_door'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_GRASTIN_DOOR"),
			'grastin_term'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_GRASTIN_TERMINAL"),
			'fivepost_term' => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_FIVEPOST_TERMINAL"),
			'sberlogistics_door'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_SBERLOGISTICS_DOOR"),
			'sberlogistics_term'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_SBERLOGISTICS_TERMINAL"),
			'yandex_door'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_YANDEX_DOOR"),
			'yandex_term'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_YANDEX_TERMINAL"),
            'dostavista_door'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_DOSTAVISTA_DOOR"),
            'kit_door'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_KIT_DOOR"),
            'kit_term'  => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_KIT_TERMINAL"),
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
		//$path = '/'.dirname(substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])));
		$path = self::PATH_MODULE;

		$eventDeliveryList = array(

			'\Eshoplogistic\Delivery\Engine\InitDeliveryService' => $path.'/lib/engine/initdeliveryservice.php',
			'\Eshoplogistic\Delivery\Profile\BaikalDoor' => $path.'/lib/profile/baikaldoor.php',
			'\Eshoplogistic\Delivery\Profile\BaikalTerminal' => $path.'/lib/profile/baikalterminal.php',
			'\Eshoplogistic\Delivery\Profile\BoxberryDoor' => $path.'/lib/profile/boxberrydoor.php',
			'\Eshoplogistic\Delivery\Profile\BoxberryTerminal' => $path.'/lib/profile/boxberryterminal.php',
			'\Eshoplogistic\Delivery\Profile\CustomDoor' => $path.'/lib/profile/customdoor.php',
			'\Eshoplogistic\Delivery\Profile\CustomTerminal' => $path.'/lib/profile/customterminal.php',
			'\Eshoplogistic\Delivery\Profile\DellineDoor' => $path.'/lib/profile/dellinedoor.php',
			'\Eshoplogistic\Delivery\Profile\DellineTerminal' => $path.'/lib/profile/dellineterminal.php',
			'\Eshoplogistic\Delivery\Profile\DpdDoor' => $path.'/lib/profile/dpddoor.php',
			'\Eshoplogistic\Delivery\Profile\DpdTerminal' => $path.'/lib/profile/dpdterminal.php',
			'\Eshoplogistic\Delivery\Profile\GtdDoor' => $path.'/lib/profile/gtddoor.php',
			'\Eshoplogistic\Delivery\Profile\GtdTerminal' => $path.'/lib/profile/gtdterminal.php',
			'\Eshoplogistic\Delivery\Profile\ImlDoor' => $path.'/lib/profile/imldoor.php',
			'\Eshoplogistic\Delivery\Profile\ImlTerminal' => $path.'/lib/profile/imlterminal.php',
			'\Eshoplogistic\Delivery\Profile\PecomDoor' => $path.'/lib/profile/pecomdoor.php',
			'\Eshoplogistic\Delivery\Profile\PecomTerminal' => $path.'/lib/profile/pecomterminal.php',
			'\Eshoplogistic\Delivery\Profile\PostrfTerminal' => $path.'/lib/profile/postrfterminal.php',
			'\Eshoplogistic\Delivery\Profile\PostrfDoor' => $path.'/lib/profile/postrfdoor.php',
			'\Eshoplogistic\Delivery\Profile\SdekDoor' => $path.'/lib/profile/sdekdoor.php',
			'\Eshoplogistic\Delivery\Profile\SdekTerminal' => $path.'/lib/profile/sdekterminal.php',
			'\Eshoplogistic\Delivery\Profile\OzonDoor' => $path.'/lib/profile/ozondoor.php',
			'\Eshoplogistic\Delivery\Profile\OzonTerminal' => $path.'/lib/profile/ozonterminal.php',
			'\Eshoplogistic\Delivery\Profile\ZdeDoor' => $path.'/lib/profile/zdedoor.php',
			'\Eshoplogistic\Delivery\Profile\ZdeTerminal' => $path.'/lib/profile/zdeterminal.php',
			'\Eshoplogistic\Delivery\Profile\PicpointTerminal' => $path.'/lib/profile/picpointterminal.php',
			'\Eshoplogistic\Delivery\Profile\EnergijaDoor' => $path.'/lib/profile/energijadoor.php',
			'\Eshoplogistic\Delivery\Profile\EnergijaTerminal' => $path.'/lib/profile/energijaterminal.php',
			'\Eshoplogistic\Delivery\Profile\VozovozDoor' => $path.'/lib/profile/vozovozdoor.php',
			'\Eshoplogistic\Delivery\Profile\VozovozTerminal' => $path.'/lib/profile/vozovozterminal.php',
			'\Eshoplogistic\Delivery\Profile\GrastinDoor' => $path.'/lib/profile/grastindoor.php',
			'\Eshoplogistic\Delivery\Profile\GrastinTerminal' => $path.'/lib/profile/grastinterminal.php',
			'\Eshoplogistic\Delivery\Profile\FivepostTerminal' => $path.'/lib/profile/fivepostterminal.php',
			'\Eshoplogistic\Delivery\Profile\SberlogisticsDoor' => $path.'/lib/profile/sberlogisticsdoor.php',
			'\Eshoplogistic\Delivery\Profile\SberlogisticsTerminal' => $path.'/lib/profile/sberlogisticsterminal.php',
			'\Eshoplogistic\Delivery\Profile\YandexDoor' => $path.'/lib/profile/yandexdoor.php',
			'\Eshoplogistic\Delivery\Profile\YandexTerminal' => $path.'/lib/profile/yandexterminal.php',
            '\Eshoplogistic\Delivery\Profile\DostavistaDoor' => $path.'/lib/profile/dostavistadoor.php',
            '\Eshoplogistic\Delivery\Profile\KitDoor' => $path.'/lib/profile/kitdoor.php',
            '\Eshoplogistic\Delivery\Profile\KitTerminal' => $path.'/lib/profile/kitterminal.php',
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
		$receipt  = Option::get(self::MODULE_ID, 'api_payment_upon_receipt');

		$paymentTypes['card']     = explode(',', $card);
		$paymentTypes['cache']    = explode(',', $cache);
		$paymentTypes['cashless'] = explode(',', $cashless);
		$paymentTypes['prepay']   = explode(',', $prepay);
		$paymentTypes['payment_upon_receipt'] = explode(',', $receipt);

		return $paymentTypes;
	}

}