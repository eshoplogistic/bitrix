<?
use \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\ModuleManager,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\EventManager,
	\Bitrix\Main\Application,
	\Bitrix\Main\Type\DateTime;


Loc::loadMessages(__FILE__);


Class eshoplogistic_delivery extends CModule
{
	var $MODULE_ID  = 'eshoplogistic.delivery';
	var $MODULE_SHORT_ID  = 'eshoplogistic';
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;
	var $MODULE_GROUP_RIGHTS = "N";


	function __construct()
	{

		$arModuleVersion = array();

		$path = str_replace("\\", "/", __FILE__);
		$path = substr($path, 0, strlen($path) - strlen("/index.php"));
		include($path."/version.php");

		$this->MODULE_NAME = Loc::GetMessage("ESHOP_LOGISTIC_MODULE_NAME");
		$this->MODULE_DESCRIPTION = Loc::GetMessage("ESHOP_LOGISTIC_MODULE_DESC");
		$this->PARTNER_NAME = Loc::GetMessage("ESHOP_LOGISTIC_PARTNER_NAME");
		$this->PARTNER_URI = Loc::GetMessage("ESHOP_LOGISTIC_PARTNER_URI");
	}

	function InstallDB()
	{
		self::createProptyes();
		return true;
	}

	function UnInstallDB()
	{
		Option::delete($this->MODULE_ID);
		return true;
	}

	static function createProptyes(){
		if(!CModule::IncludeModule("sale"))
			return false;
		$arProps = array(
			array(
				'CODE'  => "ESHOPLOGISTIC_PVZ",
				'NAME'  => GetMessage('ESHOP_LOGISTIC_ORDER_PROPERTY_NAME'),
				'DESCR' => GetMessage('ESHOP_LOGISTIC_ORDER_PROPERTY_DESC')
			),
			array(
				'CODE'  => "ESHOPLOGISTIC_FULL_ADDRESS",
				'NAME'  => GetMessage('ESHOP_LOGISTIC_ADDRESS_ORDER_PROPERTY_NAME'),
				'DESCR' => GetMessage('ESHOP_LOGISTIC_ADDRESS_ORDER_PROPERTY_DESC')
			),
			array(
				'CODE'  => "ESHOPLOGISTIC_SHIPPING_METHODS",
				'NAME'  => GetMessage('ESHOP_LOGISTIC_METHODS_ORDER_PROPERTY_NAME'),
				'DESCR' => GetMessage('ESHOP_LOGISTIC_METHODS_ORDER_PROPERTY_DESC')
			)
		);
		foreach($arProps as $prop) {
			self::handleProperty($prop);
		}

	}

	protected static function handleProperty($arProp){
		$proprtyGet=CSaleOrderProps::GetList(array("SORT" => "ASC"),array("CODE" => $arProp['CODE']));
		$existedProps=array();
		while($propElement=$proprtyGet->Fetch())
			$existedProps[$propElement['PERSON_TYPE_ID']]=$propElement['ID'];

		$return = true;

		$personTypeGet = CSalePersonType::GetList(Array("SORT" => "ASC"), Array());
		$allPersons = array();
		while($personType=$personTypeGet->Fetch())
			$allPersons[]=$personType['ID'];

		foreach($allPersons as $person){
			$propsGroupGet = CSaleOrderPropsGroup::GetList(
				array("SORT" => "ASC"),
				array("PERSON_TYPE_ID" => $person),
				false,
				array('nTopCount' => '1')
			);
			$propsGroup=$propsGroupGet->Fetch();
			$arFields = array(
				"PERSON_TYPE_ID" => $person,
				"NAME" => $arProp['NAME'],
				"TYPE" => "TEXT",
				"REQUIED" => "N",
				"DEFAULT_VALUE" => "",
				"SORT" => 100,
				"CODE" => $arProp['CODE'],
				"USER_PROPS" => "N",
				"IS_LOCATION" => "N",
				"IS_LOCATION4TAX" => "N",
				"PROPS_GROUP_ID" => $propsGroup['ID'],
				"SIZE1" => 10,
				"SIZE2" => 1,
				"DESCRIPTION" => $arProp['DESCR'],
				"IS_EMAIL" => "N",
				"IS_PROFILE_NAME" => "N",
				"IS_PAYER" => "N",
				"IS_FILTERED" => "Y",
				"IS_ZIP" => "N",
				"UTIL" => "Y"
			);
			if(!array_key_exists($person,$existedProps)) {
				if(!CSaleOrderProps::Add($arFields)) $return = false;
			}
		}
		return $return;
	}


	function InstallEvents()
	{
		$eventManager = EventManager::getInstance();
		$eventManager->registerEventHandlerCompatible(
			'sale',
			'onSaleDeliveryHandlersClassNamesBuildList',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\DeliveryBuildList',
			'deliveryBuildList'
		);

		$eventManager->registerEventHandlerCompatible(
			'sale',
			'OnSaleComponentOrderOneStepDelivery',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\ComponentOrder',
			'orderDeliveryBuildList'
		);

		$eventManager->registerEventHandlerCompatible(
			'sale',
			'OnSaleComponentOrderProperties',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\ComponentOrder',
			'saleOrderPropertyPvzFill'
		);

		$eventManager->registerEventHandlerCompatible(
			'sale',
			'OnSaleOrderBeforeSaved',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\ComponentOrder',
			'saleOrderBeforeSaved'
		);

		$eventManager->registerEventHandlerCompatible(
			'sale',
			'OnOrderNewSendEmail',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\ComponentOrder',
			'saleOrderPropertyMail'
		);

		$eventManager->registerEventHandlerCompatible(
			'main',
			'OnAdminContextMenuShow',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\Unloading',
			'OrderDetailAdminContextMenuShow'
		);

		return true;
	}

	function UnInstallEvents()
	{
		$eventManager = EventManager::getInstance();
		$eventManager->unRegisterEventHandler(
			'sale',
			'onSaleDeliveryHandlersClassNamesBuildList',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\DeliveryBuildList',
			'deliveryBuildList'
		);

		$eventManager->unRegisterEventHandler(
			'sale',
			'OnSaleComponentOrderOneStepDelivery',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\ComponentOrder',
			'orderDeliveryBuildList'
		);

		$eventManager->unRegisterEventHandler(
			'sale',
			'OnSaleComponentOrderProperties',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\ComponentOrder',
			'saleOrderPropertyPvzFill'
		);

		$eventManager->unRegisterEventHandler(
			'sale',
			'OnSaleOrderBeforeSaved',
			$this->MODULE_ID,
			'Eshoplogistic\Delivery\Event\ComponentOrder',
			'saleOrderBeforeSaved'
		);

		return true;
	}


	private function installAgents()
	{
		$dateTime =  DateTime::createFromPhp( new \DateTime('now'));

		\CAgent::AddAgent(
			"Eshoplogistic\Delivery\Agent\CacheHandler::clean();",
			$this->MODULE_ID,
			"N",
			86400,
			$dateTime,
			"Y",
			$dateTime
		);

		\CAgent::AddAgent(
			"Eshoplogistic\Delivery\Agent\UnloadingHandler::update();",
			$this->MODULE_ID,
			"N",
			86400,
			$dateTime,
			"N",
			$dateTime
		);
	}


	public function unInstallAgents()
	{
		\CAgent::RemoveModuleAgents($this->MODULE_ID);
	}

	function InstallFiles() {
		CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/js/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/js/".$this->MODULE_ID, true, true);
		CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/css/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/css/".$this->MODULE_ID, true, true);
		CopyDirFiles($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/".$this->MODULE_ID."/install/components/",$_SERVER['DOCUMENT_ROOT'].'/bitrix/components', true, true);
		CopyDirFiles($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/".$this->MODULE_ID."/install/view/",$_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.$this->MODULE_ID, true, true);

		return true;
	}

	function UnInstallFiles()	{
		DeleteDirFilesEx("/bitrix/js/".$this->MODULE_ID);
		DeleteDirFilesEx("/bitrix/css/".$this->MODULE_ID);
		DeleteDirFilesEx("/bitrix/components/".$this->MODULE_SHORT_ID."/button");
		DeleteDirFilesEx("/bitrix/admin/".$this->MODULE_ID);

		return true;
	}

	function DoInstall()
	{
		global $APPLICATION;

        if(CheckVersion(ModuleManager::getVersion("main"), "16.00.28")){

			$this->InstallDB();
			$this->InstallFiles();
			$this->createProptyes();
			$this->InstallEvents();
			$this->installAgents();
            ModuleManager::registerModule($this->MODULE_ID);

        }else{

            $APPLICATION->ThrowException(Loc::getMessage("LOG_ELEMUPD_INSTALL_ERROR_VERSION"));
        }
	}

	function DoUninstall()
	{
    	global $APPLICATION;
        $FORM_RIGHT = $APPLICATION->GetGroupRight($this->MODULE_ID);
        if ($FORM_RIGHT=="W") {
                $this->UnInstallFiles();
				$this->UnInstallDB();
                $this->UnInstallEvents();
                $this->unInstallAgents();
                ModuleManager::unRegisterModule($this->MODULE_ID);
        }
	}
}
?>
