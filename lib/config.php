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
	public $pvzBalloonLang;
	public $priceError;
	public $locationError;


	public function __construct()
	{

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
			'sdek_door'     =>  'Eshoplogistic\Delivery\Profile\SdekDoor',
			'sdek_term'     =>  'Eshoplogistic\Delivery\Profile\SdekTerminal',
            'ozon_door'     =>  'Eshoplogistic\Delivery\Profile\OzonDoor',
            'ozon_term'     =>  'Eshoplogistic\Delivery\Profile\OzonTerminal',
            'zde_door'      =>  'Eshoplogistic\Delivery\Profile\ZdeDoor',
            'zde_term'      =>  'Eshoplogistic\Delivery\Profile\ZdeTerminal',
            'picpoint_term' =>  'Eshoplogistic\Delivery\Profile\PicpointTerminal',
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
            'ozon_door'     => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_OZON_DOOR"),
            'ozon_term'     => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_OZON_TERMINAL"),
            'zde_door'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_ZDE_DOOR"),
            'zde_term'      => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_ZDE_TERMINAL"),
            'picpoint_term' => Loc::GetMessage("ESHOP_LOGISTIC_PROFILELIST_PICPOINT_TERMINAL"),
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
			'\Eshoplogistic\Delivery\Profile\SdekDoor' => $path.'/lib/profile/sdekdoor.php',
			'\Eshoplogistic\Delivery\Profile\SdekTerminal' => $path.'/lib/profile/sdekterminal.php',
            '\Eshoplogistic\Delivery\Profile\OzonDoor' => $path.'/lib/profile/ozondoor.php',
            '\Eshoplogistic\Delivery\Profile\OzonTerminal' => $path.'/lib/profile/ozonterminal.php',
            '\Eshoplogistic\Delivery\Profile\ZdeDoor' => $path.'/lib/profile/zdedoor.php',
            '\Eshoplogistic\Delivery\Profile\ZdeTerminal' => $path.'/lib/profile/zdeterminal.php',
            '\Eshoplogistic\Delivery\Profile\PicpointTerminal' => $path.'/lib/profile/picpointterminal.php',
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