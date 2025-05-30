<?

namespace Eshoplogistic\Delivery\Event;

use \Bitrix\Main,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Sale,
	\Bitrix\Sale\Delivery,
	\Eshoplogistic\Delivery\Config;

use Bitrix\Main\Config\Option;
use CFile;
use Eshoplogistic\Delivery\Api\Search;
use Eshoplogistic\Delivery\Helpers\LocationHandler;
use Bitrix\Sale\Delivery\Services\Manager;


Main\Loader::includeModule('sale');

Loc::loadMessages(__FILE__);

/** Class for handing sale.order.ajax component events
 * Class ComponentOrder
 * @package Eshoplogistic\Delivery\Event
 * @author negen
 */
class ComponentOrder
{
	/** Adding button and delivery terms information for output
	 * @param array $arResult
	 * @param array $arUserResult
	 * @param array $arParams
	 */
	public static function orderDeliveryBuildList(&$arResult, &$arUserResult, $arParams)
	{
		if (Option::get(Config::MODULE_ID, 'frame_lib')) {
            $configClass = new Config();
            $apiV = $configClass->apiV;
            if($apiV){
                \CUtil::InitJSCore(array('framev2_lib'));
            }else{
                \CUtil::InitJSCore(array('frame_lib'));
            }
			$arResult['DELIVERY'] = self::orderDeliveryBuildListFrame($arResult, $arUserResult);
		} else {
			\CUtil::InitJSCore(array('main_lib'));
			\CUtil::InitJSCore(array('yamap_lib'));

			$request = Main\Application::getInstance()->getContext()->getRequest();
			$requestData = $request->getPost("order");

			$pvzTitle = Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_DESC_EMPTY");
			$pvzValue = '';
            $fullAdressValue = '';

			foreach ($arResult['DELIVERY'] as $delivery) {
				if ($delivery['CHECKED'] == 'Y') {
					$cityCheck = $requestData;
					unset($cityCheck['RECENT_DELIVERY_VALUE']);
					if ($delivery['ID'] == $requestData['current-profile-id'] && in_array($requestData['RECENT_DELIVERY_VALUE'], $cityCheck)) {
						$tmpTitle = explode(',', $requestData['ESHOPLOGISTIC_PVZ']);
						unset($tmpTitle[0]);
						$pvzTitle = trim(implode(', ', $tmpTitle));
						$pvzValue = trim($requestData['ESHOPLOGISTIC_PVZ']);
					}
                    if(isset($requestData['ESHOPLOGISTIC_FULL_ADDRESS']) && $requestData['ESHOPLOGISTIC_FULL_ADDRESS']){
                        $fullAdressValue = $requestData['ESHOPLOGISTIC_FULL_ADDRESS'];
                    }
					break;
				}
			}

			$rsDelivery = Delivery\Services\Table::getList(array(
				'filter' => array('ACTIVE' => 'Y', '=CODE' => Config::DELIVERY_CODE),
				'select' => array('ID')
			));

            if(!isset($arResult['DELIVERY']) && !is_array($arResult['DELIVERY']))
                return [];

			$profileIds = array_keys($arResult['DELIVERY']);

			if ($delivery = $rsDelivery->fetch()) {

				$rsProfile = Delivery\Services\Table::getList(array(
					'filter' => array('ACTIVE' => 'Y', 'PARENT_ID' => $delivery['ID'], 'ID' => $profileIds),
					'select' => array('ID', 'CODE', 'DESCRIPTION')
				));
				while ($profile = $rsProfile->fetch()) {

					if ($arResult['DELIVERY'][$profile['ID']]['OWN_NAME'])
						$arResult['DELIVERY'][$profile['ID']]['NAME'] = $arResult['DELIVERY'][$profile['ID']]['OWN_NAME'];

					if (
						isset($arResult['DELIVERY'][$profile['ID']]) &&
						$arResult['DELIVERY'][$profile['ID']]['CHECKED'] == 'Y') {


						$isDeliveryHasPvz = self::isDeliveryHasPvz($profile['CODE']);

						if ($isDeliveryHasPvz && $profile['CODE']) {
                            $addressRequar = Option::get(Config::MODULE_ID, 'api_address_requar');

                            $arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] =
								'<div class="eslog-deliverey-desc">'.$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'].'</div>' .
								'<div class="eslog-deliverey-desc-lk">' . $arResult['DELIVERY'][$profile['ID']]['CALCULATE_DESCRIPTION'] . '</div>' .
								'<a 
                            onclick="BX.EShopLogistic.Delivery.sale_order_ajax.getPvzList(' . $profile['ID'] . ')"
                            href="javascript:void(0)"
                            id ="eslogistic-btn-choose-pvz"
                            class="eslog-btn-default"
                        >' .
								Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_BTN") .
								'</a>' .
								'<span>
                            <div class="eslogistic-termin">' . Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_TERMIN") . '</div>
                            <div id="eslogistic-description" class="eslogistic-description">' . $pvzTitle . '</div>
                        </span>' .
								'<input 
                            id="eslogic-pvz-value" 
                            name="ESHOPLOGISTIC_PVZ"
                            type="hidden" value="' . $pvzValue . '"
                        >' .
								'<input 
                            name="current-profile-id"
                            type="hidden" value="' . $profile['ID'] . '"
                         >'.
                                '<input 
                            id="eslogic-address-requar" 
                            name="ESHOPLOGISTIC_ADDRESS_REQUAR"
                            type="hidden" value="' . $addressRequar . '"
                        >';
						} else {
							$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] =
								'<div class="eslog-deliverey-desc">'.$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'].'</div>' .
								'<div class="eslog-deliverey-desc-lk">' . $arResult['DELIVERY'][$profile['ID']]['CALCULATE_DESCRIPTION'] . '</div>';

                            if($profile['CODE'] === 'eslogistic:dostavista_door'){
                                $arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] .=
                                    '<input id="eslogic-address-full" name="ESHOPLOGISTIC_FULL_ADDRESS" type="text" value="'.$fullAdressValue.'" placeholder="'.Loc::getMessage("ESHOP_LOGISTIC_ADDRESS_FULL").'"/>' .
                                    '<input  type="button" value="ОК" onclick="BX.EShopLogistic.Delivery.sale_order_ajax.calcFullAddress()" class="eslogic-address-full_but"/>';
                            }
						}

                        $priceEmpty = Option::get(Config::MODULE_ID, 'price_empty');
                        if($priceEmpty && $arResult['DELIVERY'][$profile['ID']]['PRICE'] == 0.0){
                            $arResult['DELIVERY'][$profile['ID']]['PRICE_FORMATED'] = $priceEmpty;
                        }
                        $priceHide = Option::get(Config::MODULE_ID, 'price_hide');
                        if($priceHide == 'Y'){
                            $arResult['DELIVERY'][$profile['ID']]['PRICE_FORMATED'] = '';
                        }

                        $arResult['DELIVERY'][$profile['ID']]['CALCULATE_DESCRIPTION'] = '';
					}
				}
			}
		}
	}

	/** Saving chosen PVZ to order property
	 * @param object $arUserResult
	 * @param object $request
	 */
    public static function saleOrderPropertyPvzFill(&$arUserResult, $request)
	{

		if ($arUserResult['DELIVERY_ID'] > 0) {
			$rsDelivery = Delivery\Services\Table::getList(array(
				'filter' => array('ACTIVE' => 'Y', 'ID' => $arUserResult['DELIVERY_ID']),
				'select' => array('CODE')
			));

			if ($delivery = $rsDelivery->fetch()) {
				$isDeliveryHasPvz = self::isDeliveryHasPvz($delivery['CODE']);
				if ($isDeliveryHasPvz) {

					$db_props = \CSaleOrderProps::GetList(
						array(),
						array(
							"PERSON_TYPE_ID" => $arUserResult['PERSON_TYPE_ID'],
							"CODE" => "ESHOPLOGISTIC_PVZ",
						),
						false,
						false,
						array('ID')
					);

					if ($props = $db_props->Fetch()) {
						$pvz = $request->getPost('ESHOPLOGISTIC_PVZ');
						if ($pvz)
							$arUserResult['ORDER_PROP'][$props['ID']] = $pvz;
					}

				}

                if($request->getPost('ESHOPLOGISTIC_CHOSE_FRAME')){
                    $choseFrame = $request->getPost('ESHOPLOGISTIC_CHOSE_FRAME');
                    $db_props = \CSaleOrderProps::GetList(
                        array(),
                        array(
                            "PERSON_TYPE_ID" => $arUserResult['PERSON_TYPE_ID'],
                            "CODE" => "ESHOPLOGISTIC_CHOSE_FRAME",
                        ),
                        false,
                        false,
                        array('ID')
                    );

                    if ($props = $db_props->Fetch()) {
                        if ($choseFrame)
                            $arUserResult['ORDER_PROP'][$props['ID']] = $choseFrame;
                    }
                }

                if($request->getPost('ESHOPLOGISTIC_SHIPPING_METHODS')){
                    $shipMethod = $request->getPost('ESHOPLOGISTIC_SHIPPING_METHODS');
                    $db_props = \CSaleOrderProps::GetList(
                        array(),
                        array(
                            "PERSON_TYPE_ID" => $arUserResult['PERSON_TYPE_ID'],
                            "CODE" => "ESHOPLOGISTIC_SHIPPING_METHODS",
                        ),
                        false,
                        false,
                        array('ID')
                    );

                    if ($props = $db_props->Fetch()) {
                        if ($shipMethod)
                            $arUserResult['ORDER_PROP'][$props['ID']] = $shipMethod;
                    }
                }

			}
		}

	}


	/** Mail chosen PVZ to order property
	 */
	public static function saleOrderPropertyMail($orderID, &$eventName, &$arFields)
	{
		$order_props = \CSaleOrderPropsValue::GetOrderProps($orderID);
		$propertyPvz = "";

		while ($arProps = $order_props->Fetch()) {
			if ($arProps["CODE"] == "ESHOPLOGISTIC_PVZ") {
				$propertyPvz = $arProps["VALUE"];
			}
		}

		if ($propertyPvz)
			$arFields["ESHOPLOGISTIC_PVZ"] = 'EShopLogistic : ' . $propertyPvz;

	}


	/** Check filling of PVZ field
	 * @param Sale\Order $order
	 * @return Main\EventResult
	 */
	public static function saleOrderBeforeSaved(Sale\Order $order)
	{
		$deliveryIds = $order->getDeliverySystemId();
		foreach ($deliveryIds as $deliveryId) {
			$rsDelivery = Delivery\Services\Table::getList(array(
				'filter' => array('ACTIVE' => 'Y', 'ID' => $deliveryId),
				'select' => array('PARENT_ID', 'CODE')
			));

			if ($delivery = $rsDelivery->fetch()) {
				if ($delivery['PARENT_ID'] > 0) {
					$rsParentDelivery = Delivery\Services\Table::getList(array(
						'filter' => array('ACTIVE' => 'Y', 'ID' => $delivery['PARENT_ID']),
						'select' => array('CODE')
					));
					if ($parentDelivery = $rsParentDelivery->fetch()) {
						$isDeliveryHasPvz = self::isDeliveryHasPvz($delivery['CODE']);

						if ($parentDelivery['CODE'] == 'eslogistic' && $isDeliveryHasPvz) {

							$propertyCollection = $order->getPropertyCollection();
                            $propertyPvz = '';
                            $propertyAddress = '';
                            $typeError = array();

							foreach ($propertyCollection as $propertyItem) {
                                $requaryPvz = Option::get(Config::MODULE_ID, 'requary_pvz');
								$propertyCode = $propertyItem->getField("CODE");

                                if ($propertyCode == 'ESHOPLOGISTIC_CHOSE_FRAME') {
                                    if($propertyItem->getValue()){
                                        $typeError['CHOSE_FRAME'] = 1;
                                    }
                                }
								if ($propertyCode == 'ESHOPLOGISTIC_PVZ') {
                                    $propertyPvz = $propertyItem;
									if (!$propertyItem->getValue() && $delivery['CODE'] !== 'eslogistic:postrf_term' && !$requaryPvz) {
                                        $typeError['ESHOPLOGISTIC_PVZ'] = 1;
									}
								}
                                if ($propertyCode == 'ADDRESS'){
                                    $propertyAddress = $propertyItem;
                                }
							}
                            $requaryPvzAddress = Option::get(Config::MODULE_ID, 'requary_pvz_address');
                            if($propertyPvz && $propertyAddress && $requaryPvzAddress){
                                $requaryPvzAddressValue = $propertyPvz->getValue();
                                if($requaryPvzAddressValue){
                                    $pos = strpos($requaryPvzAddressValue, ',');
                                    $noFirstTag = trim(substr($requaryPvzAddressValue, $pos+1));
                                    if($noFirstTag)
                                        $requaryPvzAddressValue = $noFirstTag;
                                    $propertyAddress->setField("VALUE", $requaryPvzAddressValue);
                                }
                            }

						}
                        if(isset($typeError['CHOSE_FRAME'])){
                            $message = (!empty(Option::get(Config::MODULE_ID, 'chose_frame'))) ? (Option::get(Config::MODULE_ID, 'chose_frame')) : Loc::getMessage("ESHOP_LOGISTIC_CHOSE_FRAME_EMPTY");
                            return new Main\EventResult(
                                Main\EventResult::ERROR,
                                new Sale\ResultError(
                                    $message,
                                    'ESHOP_LOGISTIC_CHOSE_FRAME_EMPTY'
                                ),
                                'sale'
                            );
                        }
                        if(isset($typeError['ESHOPLOGISTIC_PVZ'])){
                            $message = (!empty(Option::get(Config::MODULE_ID, 'terminal_pvz'))) ? Option::get(Config::MODULE_ID, 'terminal_pvz') : Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_FIELD_EMPTY");
                            return new Main\EventResult(
                                Main\EventResult::ERROR,
                                new Sale\ResultError(
                                    $message,
                                    'ESHOP_LOGISTIC_TERMINAL_PVZ_FIELD_EMPTY'
                                ),
                                'sale'
                            );
                        }


					}
				}
			}
		}
	}


	/** Check delivery type
	 * @param $deliveryCode
	 * @return bool
	 */
	private static function isDeliveryHasPvz($deliveryCode)
	{
        $array = explode('_', $deliveryCode);
        if (array_pop($array) == 'term') {
			return true;
		} else {
			return false;
		}
	}


	private static function orderDeliveryBuildListFrame($arResult, $arUserResult)
	{

		$selectedElement = '';
		$clearField = false;
		$widgetKey = Option::get(Config::MODULE_ID, 'widget_key');
		if (!$widgetKey)
			return '';

		$request = Main\Application::getInstance()->getContext()->getRequest();
		$requestDataEsl = $request->getPost("eslData");

        $configClass = new Config();
        $apiV = $configClass->apiV;
        $registry = \Bitrix\Sale\Registry::getInstance(\Bitrix\Sale\Registry::REGISTRY_TYPE_ORDER);
        $orderClassName = $registry->getOrderClassName();
        $order = $orderClassName::create(\Bitrix\Main\Application::getInstance()->getContext()->getSite());
        $propertyCollection = $order->getPropertyCollection();
        $requestDataLocation = '';
        foreach ($propertyCollection as $property){
            if ($property->isUtil())
                continue;

            $arProperty = $property->getProperty();
            if($arProperty['TYPE'] === 'LOCATION' && isset($arUserResult['ORDER_PROP'][$arProperty['ID']])){
                $requestDataLocation = $arProperty['ID'];
            }
        }
        if(!$requestDataLocation)
            $requestDataLocation = ($request->getPost("location")) ? $request->getPost("location") : 16;

        $rsDelivery = Delivery\Services\Table::getList(array(
			'filter' => array('ACTIVE' => 'Y', '=CODE' => Config::DELIVERY_CODE),
			'select' => array('ID', 'NAME', 'DESCRIPTION', 'CURRENCY', 'SORT', 'LOGOTIP')
		));

        if(!isset($arResult['DELIVERY']) && !is_array($arResult['DELIVERY']))
            return [];

		$profileIds = array_keys($arResult['DELIVERY']);
		$eslDelivery = array();
		if ($delivery = $rsDelivery->fetch()) {

			$rsProfile = Delivery\Services\Table::getList(array(
				'filter' => array('ACTIVE' => 'Y', 'PARENT_ID' => $delivery['ID'], 'ID' => $profileIds),
				'select' => array('ID', 'CODE', 'DESCRIPTION')
			));
			while ($profile = $rsProfile->fetch()) {
				$eslDelivery[$profile['ID']] = $profile;
			}

			$session = \Bitrix\Main\Application::getInstance()->getSession();
            //$session->remove('dataEsl');
			if ($session->has('dataEsl') && !$requestDataEsl && !$apiV){
				$requestDataEsl = $session->get('dataEsl');
				$clearField = true;
			}

            $apiPaymentCheck = Option::get(Config::MODULE_ID, 'api_payment_check');
            if($session->has('dataEsl') && !$requestDataEsl && $apiV && $apiPaymentCheck){
                $requestDataEslTmp = $session->get('dataEsl');
                $requestDataEslTmp = \Bitrix\Main\Web\Json::decode($requestDataEslTmp);
                if($requestDataEslTmp['paymentId'] != $arUserResult['PAY_SYSTEM_ID'] ){
                    $requestDataEsl = \Bitrix\Main\Web\Json::encode($requestDataEslTmp);
                }
                $requestDataEslTmp = '';
            }

            if ($session->has('dataEsl') && !$requestDataEsl && !$apiV){
                $requestDataEsl = $session->get('dataEsl');
                $clearField = true;
            }

            if ($requestDataEsl) {
				$requestDataEsl = \Bitrix\Main\Web\Json::decode($requestDataEsl);

                $requestDataEslTmp = $requestDataEsl;
                $requestDataEslTmp['paymentId'] = $arUserResult['PAY_SYSTEM_ID'];
                $requestDataEslTmp = \Bitrix\Main\Web\Json::encode($requestDataEslTmp);

                $session->set('dataEsl', $requestDataEslTmp);

                if($clearField){
					$requestDataEsl['terminals'] = '';
					$requestDataEsl['selectPvz'] = '';
				}
				$selectedElement = self::findDeliveryByName($eslDelivery, $requestDataEsl['key'], $requestDataEsl['mode']);
			}

			if (!$selectedElement) {
                $firstElem = current($eslDelivery);
               // $delivery['ID'] = $firstElem['ID'];
				$selectedElement = $firstElem;
                $selectedElement['DESCRIPTION'] = $delivery['DESCRIPTION'];
                $selectedElement['OWN_NAME'] = $delivery['NAME'];
				//$arResult['DELIVERY'][$delivery['ID']] = $delivery;
			}

		}

		$check = false;
		$deliveryResult = array();
		foreach ($arResult['DELIVERY'] as $key => $item) {
			if (array_key_exists($item['ID'], $eslDelivery)) {
				if ($item['CHECKED'] == 'Y')
					$check = true;

				unset($arResult['DELIVERY'][$key]);
			}

			if ($item['ID'] == $selectedElement['ID']) {
				$deliveryResult = $item;
                if(isset($selectedElement['OWN_NAME']))
                    $deliveryResult['OWN_NAME'] = $selectedElement['OWN_NAME'];
			}
		}
        if(!$deliveryResult)
            return $arResult['DELIVERY'];

		$deliveriesListTo = LocationHandler::getAvailableDeliveriesByLocation($arUserResult['ORDER_PROP'][$requestDataLocation]);
        $city = Search::getCity($deliveriesListTo['name']);
        $cityFirst = LocationHandler::parseSelectedCity($city, $deliveriesListTo['name'], $deliveriesListTo['sub_region'], $deliveriesListTo['region']);
		$jsonValueCity = \Bitrix\Main\Web\Json::encode($cityFirst);
		$calcDesc = (isset($deliveryResult['CALCULATE_DESCRIPTION'])) ? $deliveryResult['CALCULATE_DESCRIPTION'] : '';
		$deliveryResult['OWN_NAME'] = (isset($deliveryResult['OWN_NAME'])) ? $deliveryResult['OWN_NAME'] : $deliveryResult['NAME'];
		$deliveryResult['NAME'] = $deliveryResult['OWN_NAME'];
        if(!is_array($deliveryResult['LOGOTIP']))
		    $deliveryResult['LOGOTIP'] = '';

		$descriptionTerminal = '';
		if ($requestDataEsl['deliveryMethods']) {
            $countTerminal = 0;
            foreach ($requestDataEsl['deliveryMethods'] as $value){
                if($value['keyShipper'] == $requestDataEsl['mode']){
                    $countTerminal = count($value['services']);
                }
            }
			$descriptionTerminal = Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_DESC_1");
			$descriptionTerminal .= ' ' . $countTerminal;
			if ($countTerminal == 1) {
				$descriptionTerminal .= ' ' . Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_DESC_2");
			}elseif($countTerminal > 1 && $countTerminal < 5){
                $descriptionTerminal .= ' ' . Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_DESC_5");
            }else {
				$descriptionTerminal .= ' ' . Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_DESC_3");
			}
			$descriptionTerminal .= '<br>' . Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_DESC_4");

            if($countTerminal === 0)
                $descriptionTerminal = '';
		}

		$descUser = ($requestDataEsl['selectPvz'])?Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_TERMIN").' '.$requestDataEsl['selectPvz']:'';
		$deliveryResult['DESCRIPTION'] =
			'<div class="eslog-deliverey-desc">' . $descriptionTerminal . '</div>' .
			'<div class="eslog-deliverey-desc-lk">' . $calcDesc . '</div>' .
			'<a id="container_widget_esl_button" class="container_widget_esl_button eslog-btn-default loading-esl"><span class="button__text">' . Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_FRAME_BUT") . '</span>
                <div class="center">
                  <div class="wave"></div>
                  <div class="wave"></div>
                  <div class="wave"></div>
                  <div class="wave"></div>
                  <div class="wave"></div>
                  <div class="wave"></div>
                  <div class="wave"></div>
                  <div class="wave"></div>
                  <div class="wave"></div>
                  <div class="wave"></div>
                </div>
             </a>' .
			'<span>
                 <div id="eslogisticDescription" class="eslogistic-description">'.$descUser.'</div>
             </span>' .
			'<input 
                            name="current-profile-id"
                            type="hidden" value="' . $delivery['ID'] . '"
                         >';
		if ($requestDataEsl['mode'] === 'terminal') {
			if ($requestDataEsl['selectPvz']) {
				$deliveryResult['DESCRIPTION'] .= '<input 
                            id="terminalEsl" 
                            name="ESHOPLOGISTIC_PVZ" 
                            type="hidden"                         
                            value="' . $requestDataEsl['selectPvz'] . '"
                        >';
            } else {
				$deliveryResult['DESCRIPTION'] .= '<input 
                            id="terminalEsl" 
                            type="hidden"
                            name="ESHOPLOGISTIC_PVZ"
                        >';
			}
		}

        if(!isset($requestDataEsl['mode'])){
            $deliveryResult['DESCRIPTION'] .= '<input 
                            id="eslChoseFrame" 
                            type="hidden"
                            name="ESHOPLOGISTIC_CHOSE_FRAME"
                            value="1"
                        >';
        }

		$deliveryResult['DESCRIPTION'] .= "<input id='widgetCityEsl' value='$jsonValueCity' type='hidden'>";

		if ($check)
			$deliveryResult['CHECKED'] = 'Y';
		if (!$requestDataEsl) {
            $price = (isset($requestDataEsl['price']))?$requestDataEsl['price']:null;
			$deliveryResult['PRICE'] = $price;
			$deliveryResult['PRICE_FORMATED'] = CurrencyFormat($price, $deliveryResult['CURRENCY']);
            $deliveryLogoPath = CFile::GetFileArray($delivery['LOGOTIP']);
            $deliveryResult['LOGOTIP'] = $deliveryLogoPath;
            $deliveryResult['DESCRIPTION'] .= "<input id='widgetEslNotCalc' value='1' type='hidden'>";
		}

		$deliveryResult['CALCULATE_DESCRIPTION'] = '';
		unset($deliveryResult['CALCULATE_ERRORS']);

        if(isset($requestDataEsl['time'])){
            $unitTime = GetMessage("ESHOP_LOGISTIC_PER_DAY");
            if(isset($requestDataEsl['unit']))
                $unitTime = $requestDataEsl['unit'];

            $deliveryResult['PERIOD_TEXT'] = $requestDataEsl['time'].' '.$unitTime;
        }else{
            $deliveryResult['PERIOD_TEXT'] = '';
        }
        $addressRequar = Option::get(Config::MODULE_ID, 'api_address_requar');
        $deliveryResult['DESCRIPTION'] .= "<input id='eslogic-address-requar' value='$addressRequar' type='hidden'>";

        $priceEmpty = Option::get(Config::MODULE_ID, 'price_empty');
        if($priceEmpty && $deliveryResult['PRICE'] == 0.0){
            $deliveryResult['PRICE_FORMATED'] = $priceEmpty;
        }
        $priceHide = Option::get(Config::MODULE_ID, 'price_hide');
        if($priceHide == 'Y'){
            $deliveryResult['PRICE_FORMATED'] = '';
        }


        $arResult['DELIVERY'][$deliveryResult['ID']] = $deliveryResult;


		echo self::frameHtmlField($widgetKey, $arUserResult, $arResult);
		return $arResult['DELIVERY'];
	}

	private static function frameHtmlField($widgetKey, $arUserResult, $arResult)
	{
		$offers = [];
        $width = (int)Option::get(Config::MODULE_ID, 'width_default', 0);
        $height = (int)Option::get(Config::MODULE_ID, 'height_default', 0);
        $length = (int)Option::get(Config::MODULE_ID, 'length_default', 0);
        $weightDefault = (int)Option::get(Config::MODULE_ID, 'weight_default', 1);

        $configClass = new Config();
        $apiV = $configClass->apiV;

        if($apiV){
            $html = "<div id='invisibleBlockEsl'><div id='eShopLogisticWidgetCart' data-key='" . $widgetKey . "' style='display: block;' data-lazy-load='false' data-controller='/bitrix/services/main/ajax.php?action=eshoplogistic:delivery.api.ajaxhandler.widgetData' data-v-app></div></div>";
            $html .= "<script src='https://api.esplc.ru/widgets/cart/app.js'></script>";
        }else{
            $html = "<div id='eShopLogisticStatic' data-key='" . $widgetKey . "' style='display: block;'></div>";
            $html .= "<script src='https://api.eshoplogistic.ru/widget/cart/v1/app.js'></script>";
        }

		foreach ($arResult['BASKET_ITEMS'] as $item) {
            if($item['DIMENSIONS']){
                $dimensions = unserialize($item['DIMENSIONS']);
                if($dimensions['WIDTH']) $width = $dimensions['WIDTH'] / 10;
                if($dimensions['LENGTH']) $height = $dimensions['LENGTH'] / 10;
                if($dimensions['HEIGHT']) $length = $dimensions['HEIGHT'] / 10;

            }
			$offers[] = array(
				'article' => $item['ID'],
				'name' => $item['NAME'],
				'count' => $item['QUANTITY'],
				'price' => $item['PRICE'],
				'weight' => isset($item['WEIGHT']) && $item['WEIGHT'] != '0.00' ? $item['WEIGHT'] / 1000 : $weightDefault,
                "dimensions" => $width."*".$height."*".$length
			);
		}
		$jsonValueOffers = \Bitrix\Main\Web\Json::encode($offers);
		$html .= "<input id='widgetOffersEsl' value='$jsonValueOffers' type='hidden'>";

		$configClass = new Config();
		$paymentTypesList = $configClass->getPaymentTypes();
        $paymentResult = array();

        foreach ($paymentTypesList as $key=>$payment) {
            $paymentType = self::getCurrentPaymentTypes($key);
            if ($paymentType === '') continue;

            $paymentResult[$paymentType] = $payment;
        }

		$jsonValuePayment = \Bitrix\Main\Web\Json::encode($paymentResult);
            $html .= "<input id='widgetPaymentEsl' value='$jsonValuePayment' type='hidden'>";

		return $html;
	}

	private static function findDeliveryByName($deliveryBX, $code, $type)
	{
		$result = false;

		if ($type === 'terminal')
			$nameTypeBx = 'term';

		if ($type === 'door')
			$nameTypeBx = 'door';

        if ($type === 'postrf')
            $nameTypeBx = 'term';


        if (strpos($code, 'custom') !== false) {
            $codeTmp = explode('-', $code);
            if(count($codeTmp) > 1){
                $code = 'custom';
            }
        }

		$nameDeliveryBx = 'eslogistic:' . $code . '_' . $nameTypeBx;

		foreach ($deliveryBX as $key => $value) {
			if ($nameDeliveryBx === $value['CODE'])
				$result = $deliveryBX[$key];
		}

		return $result;
	}

    private static function getCurrentPaymentTypes($paymentTypesList)
    {
        $paymentType = '';

        switch ($paymentTypesList) {
            case 'card':
                $paymentType = 'card';
                break;
            case 'cache':
                $paymentType = 'cash';
                break;
            case 'cashless':
                $paymentType = 'cashless';
                break;
            case 'prepay':
                $paymentType = 'prepay';
                break;
            case 'payment_upon_receipt':
                $paymentType = 'payment_upon_receipt';
                break;
        }

        return $paymentType;
    }

}