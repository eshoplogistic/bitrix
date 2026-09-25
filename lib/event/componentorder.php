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
use Eshoplogistic\Delivery\Logger\Logger;


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
		if (!isset($arResult['DELIVERY']) || !is_array($arResult['DELIVERY'])) {
			return;
		}

		// Неотмеченные методы доставки Bitrix по умолчанию не пересчитывает (это происходит
		// только для CHECKED-элемента или по AJAX после выбора), поэтому проверки на
		// CALCULATE_ERRORS/cityNotFound ниже их не касаются — карточки конкретных ТК
		// (СДЕК, Байкал Сервис и т.п.) остаются в списке даже при полностью нерабочем ключе.
		// Поэтому дополнительно один раз проверяем статус авторизации (кэшируется на час,
		// см. Api\Site::getAuthStatus) и, если ключ не авторизован, убираем из чекаута все
		// профили eShopLogistic целиком, независимо от режима отображения и того, отмечен
		// ли метод.
		$authStatus = (new \Eshoplogistic\Delivery\Api\Site())->getAuthStatus();
		if (empty($authStatus['success'])) {
			$rsDelivery = Delivery\Services\Table::getList(array(
				'filter' => array('ACTIVE' => 'Y', '=CODE' => Config::DELIVERY_CODE),
				'select' => array('ID')
			));
			while ($delivery = $rsDelivery->fetch()) {
				$rsProfile = Delivery\Services\Table::getList(array(
					'filter' => array('PARENT_ID' => $delivery['ID']),
					'select' => array('ID')
				));
				while ($profile = $rsProfile->fetch()) {
					unset($arResult['DELIVERY'][$profile['ID']]);
				}
			}
			return;
		}

		if (Option::get(Config::MODULE_ID, 'frame_lib')) {
            \CUtil::InitJSCore(array('framev2_lib'));
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
						$pvzTitle = htmlspecialchars(trim(implode(', ', $tmpTitle)), ENT_QUOTES, 'UTF-8');
						$pvzValue = htmlspecialchars(trim($requestData['ESHOPLOGISTIC_PVZ']), ENT_QUOTES, 'UTF-8');
					}
                    if(isset($requestData['ESHOPLOGISTIC_FULL_ADDRESS']) && $requestData['ESHOPLOGISTIC_FULL_ADDRESS']){
                        $fullAdressValue = htmlspecialchars($requestData['ESHOPLOGISTIC_FULL_ADDRESS'], ENT_QUOTES, 'UTF-8');
                    }
					break;
				}
			}

			$rsDelivery = Delivery\Services\Table::getList(array(
				'filter' => array('ACTIVE' => 'Y', '=CODE' => Config::DELIVERY_CODE),
				'select' => array('ID')
			));

            if(!isset($arResult['DELIVERY']) || !is_array($arResult['DELIVERY']))
                return [];

			$profileIds = array_keys($arResult['DELIVERY']);

			if ($delivery = $rsDelivery->fetch()) {

				$rsProfile = Delivery\Services\Table::getList(array(
					'filter' => array('ACTIVE' => 'Y', 'PARENT_ID' => $delivery['ID'], 'ID' => $profileIds),
					'select' => array('ID', 'CODE', 'DESCRIPTION')
				));

                // Собираем IDs LOCATION-полей один раз через SQL, а не через Order::create()
                $addressRequar = Option::get(Config::MODULE_ID, 'api_address_requar');
                $priceEmpty = Option::get(Config::MODULE_ID, 'price_empty');
                $priceHide  = Option::get(Config::MODULE_ID, 'price_hide');
                $locationTypeIdsClassicStr = implode(',', self::getLocationPropertyIds());

				while ($profile = $rsProfile->fetch()) {

					if ($arResult['DELIVERY'][$profile['ID']]['OWN_NAME'])
						$arResult['DELIVERY'][$profile['ID']]['NAME'] = $arResult['DELIVERY'][$profile['ID']]['OWN_NAME'];

					if (
						isset($arResult['DELIVERY'][$profile['ID']]) &&
						$arResult['DELIVERY'][$profile['ID']]['CHECKED'] == 'Y') {

						if (isset($arResult['DELIVERY'][$profile['ID']]['CALCULATE_ERRORS'])) {
							// Расчёт стоимости не удался (в т.ч. из-за ошибки/невалидного API-ключа):
							// PRICE у Bitrix не задан, а null == 0.0 — из-за этого ниже включался
							// "price_empty" и покупатель видел фиктивное "бесплатно" вместо ошибки.
							// Вместо этого просто убираем метод доставки из списка на чекауте.
							unset($arResult['DELIVERY'][$profile['ID']]);
							continue;
						}

						$isDeliveryHasPvz = self::isDeliveryHasPvz($profile['CODE']);

						if ($isDeliveryHasPvz && $profile['CODE']) {

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
                            type="hidden" value="' . htmlspecialcharsbx((string)$addressRequar, ENT_QUOTES) . '"
                        >' .
                                '<input 
                            id="eslogic-location-fields"
                            type="hidden" value="' . htmlspecialcharsbx($locationTypeIdsClassicStr, ENT_QUOTES) . '"
                        >';
						} else {
							$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] =
								'<div class="eslog-deliverey-desc">'.$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'].'</div>' .
								'<div class="eslog-deliverey-desc-lk">' . $arResult['DELIVERY'][$profile['ID']]['CALCULATE_DESCRIPTION'] . '</div>';

                            if($profile['CODE'] === 'eslogistic:dostavista_door'){
                                $arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] .=
                                    '<input id="eslogic-address-full" name="ESHOPLOGISTIC_FULL_ADDRESS" type="text" value="'.$fullAdressValue.'" placeholder="'.Loc::getMessage("ESHOP_LOGISTIC_ADDRESS_FULL").'"/>' .
                                    '<input  type="button" value="'.Loc::getMessage("ESHOP_LOGISTIC_ADDRESS_FULL_BUTTON").'" onclick="BX.EShopLogistic.Delivery.sale_order_ajax.calcFullAddress()" class="eslogic-address-full_but"/>';
                            }
						}

                        if($priceEmpty && $arResult['DELIVERY'][$profile['ID']]['PRICE'] == 0.0){
                            $arResult['DELIVERY'][$profile['ID']]['PRICE_FORMATED'] = $priceEmpty;
                        }
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
				$choseFrame = $request->getPost('ESHOPLOGISTIC_CHOSE_FRAME');
				$shipMethod = $request->getPost('ESHOPLOGISTIC_SHIPPING_METHODS');

				// Режим виджета: скрытое поле с тарифом есть в форме только после обновления
				// чекаута с eslData — любое следующее обновление без eslData его теряет.
				// Поэтому тариф, выбранный в виджете, дополнительно берём из самих данных виджета.
				$widgetTariff = self::getWidgetTariff($request, $delivery['CODE']);
				if ($widgetTariff) {
					$decodedShipMethod = is_string($shipMethod) ? json_decode($shipMethod, true) : null;
					if (!is_array($decodedShipMethod)) {
						$decodedShipMethod = array();
					}
					if (empty($decodedShipMethod['terminal_tarrif']['code'])) {
						$decodedShipMethod['terminal_tarrif'] = $widgetTariff;
						$shipMethod = json_encode($decodedShipMethod, JSON_UNESCAPED_UNICODE);
					}
				}

				$neededCodes = array();
				if ($isDeliveryHasPvz) $neededCodes[] = "ESHOPLOGISTIC_PVZ";
				if ($choseFrame) $neededCodes[] = "ESHOPLOGISTIC_CHOSE_FRAME";
				if ($shipMethod) $neededCodes[] = "ESHOPLOGISTIC_SHIPPING_METHODS";

				if ($neededCodes) {
					// Один запрос вместо трёх последовательных CSaleOrderProps::GetList
					$db_props = \CSaleOrderProps::GetList(
						array(),
						array(
							"PERSON_TYPE_ID" => $arUserResult['PERSON_TYPE_ID'],
							"CODE" => $neededCodes,
						),
						false,
						false,
						array('ID', 'CODE')
					);

					$propIdByCode = array();
					while ($props = $db_props->Fetch()) {
						$propIdByCode[$props['CODE']] = $props['ID'];
					}

					if ($isDeliveryHasPvz && isset($propIdByCode['ESHOPLOGISTIC_PVZ'])) {
						$pvz = $request->getPost('ESHOPLOGISTIC_PVZ');
						if ($pvz)
							$arUserResult['ORDER_PROP'][$propIdByCode['ESHOPLOGISTIC_PVZ']] = $pvz;
					}

					if ($choseFrame && isset($propIdByCode['ESHOPLOGISTIC_CHOSE_FRAME'])) {
						$arUserResult['ORDER_PROP'][$propIdByCode['ESHOPLOGISTIC_CHOSE_FRAME']] = $choseFrame;
					}

					if ($shipMethod && isset($propIdByCode['ESHOPLOGISTIC_SHIPPING_METHODS'])) {
						// Значение приходит от анонимного покупателя, а свойство потом считается
						// серверным состоянием выгрузки (ключ answer/pending_confirmation) — оставляем
						// только данные расчёта, которые сам модуль кладёт в hidden input.
						$safeShipMethod = self::sanitizeShippingMethodsPost($shipMethod);
						if ($safeShipMethod !== null) {
							$arUserResult['ORDER_PROP'][$propIdByCode['ESHOPLOGISTIC_SHIPPING_METHODS']] = $safeShipMethod;
						}
					}
				}

			}
		}

	}


	/** Тариф ({code, name}), выбранный покупателем в виджете (framev2-script.js кладёт его в
	 * eslData.tariff; orderDeliveryBuildListFrame сохраняет eslData в сессию как dataEsl).
	 * Возвращается только если данные виджета относятся к той же службе и режиму, что и
	 * выбранный профиль доставки — иначе это остатки прежнего выбора.
	 * @param object $request
	 * @param string $deliveryCode CODE профиля доставки, например eslogistic:sdek_term
	 * @return array|null
	 */
	private static function getWidgetTariff($request, $deliveryCode)
	{
		if (!Option::get(Config::MODULE_ID, 'frame_lib')) {
			return null;
		}

		$raw = $request->getPost('eslData');
		if (!$raw) {
			$session = Main\Application::getInstance()->getSession();
			$raw = $session->has('dataEsl') ? $session->get('dataEsl') : null;
		}
		$data = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($data) || !isset($data['tariff']['code']) || !is_scalar($data['tariff']['code'])
			|| !isset($data['key'], $data['mode']) || !is_string($data['key']) || !is_string($data['mode'])) {
			return null;
		}

		if (!self::findDeliveryByName(array(array('CODE' => $deliveryCode)), $data['key'], $data['mode'])) {
			return null;
		}

		return array(
			'code' => (string)$data['tariff']['code'],
			'name' => isset($data['tariff']['name']) && is_scalar($data['tariff']['name']) ? (string)$data['tariff']['name'] : '',
		);
	}


	/** Оставляет в значении ESHOPLOGISTIC_SHIPPING_METHODS из POST только ключи, которые
	 * CalculateHandler кладёт в hidden input (см. $debugArr): settlement_to/region_to/
	 * settlement_from/region_from — строки, terminal_tarrif — пустая строка либо массив code/name.
	 * @param mixed $raw
	 * @return string|null JSON для сохранения в свойство заказа либо null, если значение непригодно
	 */
	private static function sanitizeShippingMethodsPost($raw)
	{
		$decoded = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($decoded)) {
			return null;
		}

		$safe = array();
		foreach (array('settlement_to', 'region_to', 'settlement_from', 'region_from') as $key) {
			if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
				$safe[$key] = mb_substr((string)$decoded[$key], 0, 255);
			}
		}

		if (isset($decoded['terminal_tarrif'])) {
			$tariff = $decoded['terminal_tarrif'];
			if (is_array($tariff)) {
				$safeTariff = array();
				foreach (array('code', 'name') as $key) {
					if (isset($tariff[$key]) && is_scalar($tariff[$key])) {
						$safeTariff[$key] = mb_substr((string)$tariff[$key], 0, 255);
					}
				}
				// Без code тариф бесполезен (exportfileds.php обращается к ['code'] напрямую)
				$safe['terminal_tarrif'] = isset($safeTariff['code']) ? $safeTariff : '';
			} else {
				$safe['terminal_tarrif'] = '';
			}
		}

		return json_encode($safe, JSON_UNESCAPED_UNICODE);
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
			$arFields["ESHOPLOGISTIC_PVZ"] = 'EShopLogistic : ' . htmlspecialcharsbx($propertyPvz);

	}


	/** Check filling of PVZ field
	 * @param Sale\Order $order
	 * @return Main\EventResult
	 */
	public static function saleOrderBeforeSaved(Sale\Order $order)
	{
		// В режиме виджета (frame_lib) реальная цена доставки считается только на реальном
		// оформлении заказа (см. CalculateHandler::skipRealCalculation, action=saveOrderAjax) —
		// на всех остальных AJAX-обновлениях чекаута она сознательно отбрасывается и
		// возвращается 0. Но Bitrix (sale.order.ajax::synchronizeOrder()) вызывает calculate()
		// заново только если DELIVERY_ID (или локационное свойство) реально ИЗМЕНИЛИСЬ в этом
		// же запросе. На самом запросе оформления способ доставки обычно уже выбран раньше и
		// не меняется — Bitrix calculate() вообще не перевызывает, и в заказ сохраняется та
		// самая нулевая цена с последнего обычного (не подтверждающего) обновления. Форсируем
		// пересчёт здесь: OnSaleOrderBeforeSaved срабатывает один раз, ровно перед реальным
		// сохранением заказа, и action=saveOrderAjax на этот момент гарантированно виден
		// CalculateHandler'у — реальный запрос к api.esplc.ru уйдёт ровно один раз.
		// Только для нового заказа (оформление): это же событие срабатывает и на каждом
		// последующем save() уже существующего заказа — сохранение ответа ТК после выгрузки
		// (AJAX-контроллер, ADMIN_SECTION не определён), обновление статуса агентом и т.д.
		// Там skipRealCalculation() возвращает true, и пересчёт обнулял бы цену доставки.
		// Пересчёт идёт внутри withRealCalculation(): заглушка с нулевой ценой отключена
		// независимо от параметров запроса — иначе путь сохранения без action=saveOrderAjax
		// ("заказ в один клик", не-AJAX submit, кастомная форма) сохранил бы доставку за 0.
		$calcResult = $order->isNew()
			? \Eshoplogistic\Delivery\Helpers\CalculateHandler::withRealCalculation(function () use ($order) {
				return $order->getShipmentCollection()->calculateDelivery();
			})
			: new Main\Result();
		if (!$calcResult->isSuccess()) {
			Logger::log(
				'DELIVERY_RECALC_FAILED',
				Logger::msg('ORDER', ['#ORDER_ID#' => $order->getId()]) . ': ' . implode('; ', array_map(
					function ($e) { return $e->getCode() . ':' . $e->getMessage(); },
					$calcResult->getErrors()
				)),
				\CEventLog::SEVERITY_ERROR,
				$order->getId() ?: false
			);
		}

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

						if ($parentDelivery['CODE'] == 'eslogistic') {

							$propertyCollection = $order->getPropertyCollection();
                            $propertyPvz = '';
                            $propertyAddress = '';
                            $typeError = array();
                            $requaryPvz = Option::get(Config::MODULE_ID, 'requary_pvz');

							foreach ($propertyCollection as $propertyItem) {
								$propertyCode = $propertyItem->getField("CODE");

                                if ($propertyCode == 'ESHOPLOGISTIC_CHOSE_FRAME') {
                                    if($propertyItem->getValue()){
                                        $typeError['CHOSE_FRAME'] = 1;
                                    }
                                }
								if ($propertyCode == 'ESHOPLOGISTIC_PVZ') {
                                    $propertyPvz = $propertyItem;
									if ($isDeliveryHasPvz && !$propertyItem->getValue() && $delivery['CODE'] !== 'eslogistic:postrf_term' && !$requaryPvz) {
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


	/** IDs свойств заказа с TYPE=LOCATION
	 * @return array
	 */
	private static function getLocationPropertyIds()
	{
		$ids = [];
		$dbLocationProps = \CSaleOrderProps::GetList(
			array('SORT' => 'ASC'),
			array('TYPE' => 'LOCATION', 'UTIL' => 'N'),
			false,
			false,
			array('ID')
		);
		while ($locProp = $dbLocationProps->Fetch()) {
			$ids[] = $locProp['ID'];
		}
		return $ids;
	}

	/** Значение свойства заказа, только что отправленное в этом же AJAX-запросе.
	 * @param int|string $propId
	 * @param array $requestOrderPost
	 * @param array $arUserResult
	 * @return string|null
	 */
	private static function getFreshOrderPropValue($propId, $requestOrderPost, $arUserResult)
	{
		if (!$propId) {
			return null;
		}
		if (isset($requestOrderPost['ORDER_PROP_' . $propId]) && $requestOrderPost['ORDER_PROP_' . $propId] !== '') {
			return $requestOrderPost['ORDER_PROP_' . $propId];
		}
		if (isset($arUserResult['ORDER_PROP'][$propId]) && $arUserResult['ORDER_PROP'][$propId] !== '') {
			return $arUserResult['ORDER_PROP'][$propId];
		}
		return null;
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
		$invalidEslService = false;
		$clearField = false;
		// SITE_ID — Option::get сам подставит общий ключ модуля, если для текущего
		// сайта не задан отдельный override (мультисайтовость, см. options.php).
		$widgetKey = Option::get(Config::MODULE_ID, 'widget_key', '', Main\Context::getCurrent()->getSite());
		if (!$widgetKey)
			return '';

		$request = Main\Application::getInstance()->getContext()->getRequest();
		$requestDataEsl = $request->getPost("eslData");
        // Сырые данные того же AJAX-запроса — единственный надёжный источник
        $requestOrderPost = $request->getPost("order");
        $locationTypeIds = self::getLocationPropertyIds();
        $requestDataLocation = '';
        foreach ($locationTypeIds as $locPropId) {
            if (isset($arUserResult['ORDER_PROP'][$locPropId])) {
                $requestDataLocation = $locPropId;
            }
        }
        if(!$requestDataLocation)
            $requestDataLocation = ($request->getPost("location")) ? $request->getPost("location") : 16;

        $rsDelivery = Delivery\Services\Table::getList(array(
			'filter' => array('ACTIVE' => 'Y', '=CODE' => Config::DELIVERY_CODE),
			'select' => array('ID', 'NAME', 'DESCRIPTION', 'CURRENCY', 'SORT', 'LOGOTIP')
		));

        if(!isset($arResult['DELIVERY']) || !is_array($arResult['DELIVERY']))
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

            $apiPaymentCheck = Option::get(Config::MODULE_ID, 'api_payment_check');
            if($session->has('dataEsl') && !$requestDataEsl && $apiPaymentCheck){
                $requestDataEslTmp = $session->get('dataEsl');
                $requestDataEslTmp = \Bitrix\Main\Web\Json::decode($requestDataEslTmp);
                if($requestDataEslTmp['paymentId'] != $arUserResult['PAY_SYSTEM_ID'] ){
                    $requestDataEsl = \Bitrix\Main\Web\Json::encode($requestDataEslTmp);
                }
                $requestDataEslTmp = '';
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

				if (!$selectedElement) {
					// Покупатель выбрал в виджете службу (например ПЭК), для которой в админке
					// не создан/не активен профиль в "Калькулятор доставки eShopLogistic".
					// Раньше в этом случае мы молча подставляли первый попавшийся сконфигурированный
					// профиль (например СДЭК) через current($eslDelivery), но цену, срок и ПВЗ ниже
					// брали из данных виджета для выбранной покупателем службы — на чекауте
					// показывались логотип/название одной ТК с ценой и пунктом выдачи другой.
					// Возврат здесь недопустим: $requestDataEsl хранится в сессии и подставляется
					// на КАЖДЫЙ рендер чекаута, поэтому return полностью ломал склейку профилей
					// в один пункт "Калькулятор доставки eShopLogistic" — вместо него на любой
					// стадии показывался сырой список отдельных профилей (СДЭК: курьер, СДЭК: ПВЗ,
					// Байкал Сервис по отдельности). Вместо прерывания просто забываем невалидный
					// выбор и идём дальше как при первой загрузке (без данных виджета) — ниже
					// сработает обычный плейсхолдер "ещё не рассчитано", плюс покажем покупателю
					// явное сообщение, что выбранная служба недоступна.
					$requestDataEsl = null;
					$invalidEslService = true;
				}
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

            if(!isset($selectedElement['ID']))
                continue;

			if ($item['ID'] == $selectedElement['ID']) {
				$deliveryResult = $item;
                if(isset($selectedElement['OWN_NAME']))
                    $deliveryResult['OWN_NAME'] = $selectedElement['OWN_NAME'];
			}
		}
        if(!$deliveryResult) {
            self::printFrameHtmlField($widgetKey, $arUserResult, $arResult);
            return $arResult['DELIVERY'];
        }

        $addressRequarOption = Option::get(Config::MODULE_ID, 'api_address_requar');
        $addressRequarIds = $addressRequarOption ? array_filter(array_map('trim', explode(',', $addressRequarOption))) : [];
        $addressCityName = null;
        foreach ($addressRequarIds as $reqId) {
            if ((string)$reqId === (string)$requestDataLocation) {
                continue;
            }
            $value = self::getFreshOrderPropValue($reqId, $requestOrderPost, $arUserResult);
            if ($value !== null) {
                $addressCityName = $value;
                break;
            }
        }
        if ($addressCityName !== null) {
            $resolved = LocationHandler::resolveCityFromText($addressCityName);
            $cityFirst = $resolved['parsedCity'] ?? [];
        } else {
            $locationValue = self::getFreshOrderPropValue($requestDataLocation, $requestOrderPost, $arUserResult);
            $deliveriesListTo = LocationHandler::getAvailableDeliveriesByLocation($locationValue);
            // getAvailableDeliveriesByLocation уже вернул распарсенный объект города —
            // повторный запрос Search::getCity не нужен
            $cityFirst = $deliveriesListTo;
        }
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

		$selectPvz = (string)($requestDataEsl['selectPvz'] ?? '');
		$selectPvzHtml = htmlspecialcharsbx($selectPvz, ENT_QUOTES);
		$descUser = $selectPvzHtml !== ''
			? Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_TERMIN") . ' ' . $selectPvzHtml
			: '';

        $cityNotFound = empty($cityFirst);

        if ($cityNotFound) {
            // Город не удалось определить — это происходит и когда сломан/неверен API-ключ
            // (LocationHandler ходит в eShopLogistic API). Раньше в этом случае метод доставки
            // всё равно оставался в списке выбранным, с виджетом-заглушкой и ценой по умолчанию
            // 0 -> "бесплатно", что вводит покупателя в заблуждение. Вместо этого просто не
            // показываем метод доставки в чекауте, пока город не определится.
            self::printFrameHtmlField($widgetKey, $arUserResult, $arResult);
            return $arResult['DELIVERY'];
        }

		$deliveryResult['DESCRIPTION'] =
			'<div class="eslog-deliverey-desc">' . $descriptionTerminal . '</div>' .
			'<div class="eslog-deliverey-desc-lk">' . $calcDesc . '</div>' .
			($invalidEslService
				? '<div class="eslog-service-not-configured" style="color:red;margin:8px 0;">' . Loc::getMessage("ESHOP_LOGISTIC_SERVICE_NOT_CONFIGURED") . '</div>'
				: '') .
			($cityNotFound
				? '<div class="eslog-city-not-found" style="color:red;margin:8px 0;">' . Loc::getMessage("ESHOP_LOGISTIC_CITY_NOT_FOUND") . '</div>'
				: '<a id="container_widget_esl_button" class="container_widget_esl_button eslog-btn-default loading-esl"><span class="button__text">' . Loc::getMessage("ESHOP_LOGISTIC_TERMINAL_PVZ_FRAME_BUT") . '</span>
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
			'<div id="eslCalcErrorMsg" style="display:none;color:#dc2626;padding:12px 16px;border:1px solid #fecaca;border-radius:6px;background:#fef2f2;margin:8px 0;font-size:14px;line-height:1.5;"></div>'
			) .
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
                            value="' . $selectPvzHtml . '"
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
                            id="terminalEsl"
                            name="ESHOPLOGISTIC_PVZ"
                            type="hidden"
                            value="' . $selectPvzHtml . '"
                        >';
            $deliveryResult['DESCRIPTION'] .= '<input 
                            id="eslChoseFrame" 
                            type="hidden"
                            name="ESHOPLOGISTIC_CHOSE_FRAME"
                            value="1"
                        >';
        }

		// В режиме виджета скрытое поле ESHOPLOGISTIC_SHIPPING_METHODS из CalculateHandler
		// не выводится (результат серверного расчёта отбрасывается), поэтому тариф, выбранный
		// покупателем в виджете, передаём сами — иначе свойство заказа остаётся пустым и в
		// форме выгрузки селект тарифа откатывается на первый пункт списка.
		if (isset($requestDataEsl['tariff']['code']) && is_scalar($requestDataEsl['tariff']['code'])) {
			$shippingMethodsValue = \Bitrix\Main\Web\Json::encode(array(
				'terminal_tarrif' => array(
					'code' => (string)$requestDataEsl['tariff']['code'],
					'name' => (string)($requestDataEsl['tariff']['name'] ?? ''),
				),
			));
			$deliveryResult['DESCRIPTION'] .= '<input name="ESHOPLOGISTIC_SHIPPING_METHODS" type="hidden" value="' . htmlspecialcharsbx($shippingMethodsValue, ENT_QUOTES) . '">';
		}

		$deliveryResult['DESCRIPTION'] .= '<input id="widgetCityEsl" value="' . htmlspecialcharsbx($jsonValueCity, ENT_QUOTES) . '" type="hidden">';

		if ($check)
			$deliveryResult['CHECKED'] = 'Y';
		if (!$requestDataEsl) {
			$deliveryResult['PRICE'] = null;
			$deliveryResult['PRICE_FORMATED'] = CurrencyFormat(null, $deliveryResult['CURRENCY']);
            $deliveryLogoPath = CFile::GetFileArray($delivery['LOGOTIP']);
            $deliveryResult['LOGOTIP'] = $deliveryLogoPath;
            $deliveryResult['DESCRIPTION'] .= "<input id='widgetEslNotCalc' value='1' type='hidden'>";
		} elseif (array_key_exists('price', $requestDataEsl)) {
            // Раньше цену виджета сюда не подставляли (мёртвая ветка: $requestDataEsl['price']
            // проверялся внутри "if (!$requestDataEsl)", где сам $requestDataEsl всегда пуст).
            // Из-за этого PRICE оставался равен $item['PRICE'] — тому, что вернул классический
            // calculate() для CHECKED-профиля, который тут не показывается и может относиться к
            // другой службе, чем выбрана в виджете. С оптимизацией skipRealCalculation() в
            // CalculateHandler (обычный рендер чекаута в режиме виджета не делает реальный расчёт,
            // раз он всё равно отбрасывается) это стало явной 0 руб. вместо настоящей цены виджета —
            // сама причина, по которой этот блок здесь появился. Данные виджета — единственный
            // источник, которому можно доверять для отображаемой цены в этом режиме.
            $deliveryResult['PRICE'] = $requestDataEsl['price'];
            $deliveryResult['PRICE_FORMATED'] = CurrencyFormat($requestDataEsl['price'], $deliveryResult['CURRENCY']);
		}

		$deliveryResult['CALCULATE_DESCRIPTION'] = '';
		unset($deliveryResult['CALCULATE_ERRORS']);

        if(isset($requestDataEsl['time'])){
            $unitTime = GetMessage("ESHOP_LOGISTIC_PER_DAY");
            if(isset($requestDataEsl['unit']))
                $unitTime = $requestDataEsl['unit'];

            $deliveryResult['PERIOD_TEXT'] = htmlspecialcharsbx($requestDataEsl['time']).' '.htmlspecialcharsbx($unitTime);
        }else{
            $deliveryResult['PERIOD_TEXT'] = '';
        }
        $addressRequar = Option::get(Config::MODULE_ID, 'api_address_requar');
        $deliveryResult['DESCRIPTION'] .= '<input id="eslogic-address-requar" value="' . htmlspecialcharsbx((string)$addressRequar, ENT_QUOTES) . '" type="hidden">';
        $locationTypeIdsStr = implode(',', $locationTypeIds);
        $deliveryResult['DESCRIPTION'] .= '<input id="eslogic-location-fields" value="' . htmlspecialcharsbx($locationTypeIdsStr, ENT_QUOTES) . '" type="hidden">';

        $priceEmpty = Option::get(Config::MODULE_ID, 'price_empty');
        if($priceEmpty && $deliveryResult['PRICE'] == 0.0){
            $deliveryResult['PRICE_FORMATED'] = $priceEmpty;
        }
        $priceHide = Option::get(Config::MODULE_ID, 'price_hide');
        if($priceHide == 'Y'){
            $deliveryResult['PRICE_FORMATED'] = '';
        }


        $arResult['DELIVERY'][$deliveryResult['ID']] = $deliveryResult;


		self::printFrameHtmlField($widgetKey, $arUserResult, $arResult);
		return $arResult['DELIVERY'];
	}

	private static $frameHtmlPrinted = false;

	// Контейнер виджета и скрытые #widgetOffersEsl/#widgetPaymentEsl выводятся через echo,
	// а echo доходит до страницы только при обычной загрузке: AJAX-ответ sale.order.ajax
	// (showAjaxAnswer) делает RestartBuffer и отдаёт JSON. Поэтому выводим их при первой
	// загрузке всегда, даже если метод ESL сейчас недоступен (например, скрыт ограничением
	// по выбранной оплате) — иначе после переключения оплаты метод появится по AJAX, а
	// framev2-script.js упадёт на отсутствующих полях и неинициализированном виджете.
	private static function printFrameHtmlField($widgetKey, $arUserResult, $arResult)
	{
		if (self::$frameHtmlPrinted)
			return;

		$request = Main\Application::getInstance()->getContext()->getRequest();
		if ($request->isPost() && $request->get('via_ajax') === 'Y')
			return;

		self::$frameHtmlPrinted = true;
		echo self::frameHtmlField($widgetKey, $arUserResult, $arResult);
	}

	private static function frameHtmlField($widgetKey, $arUserResult, $arResult)
	{
		$offers = [];
        $width = (int)Option::get(Config::MODULE_ID, 'width_default', 0);
        $height = (int)Option::get(Config::MODULE_ID, 'height_default', 0);
        $length = (int)Option::get(Config::MODULE_ID, 'length_default', 0);
        $weightDefault = (int)Option::get(Config::MODULE_ID, 'weight_default', 1);

        $widgetKeyAttr = htmlspecialcharsbx((string)$widgetKey, ENT_QUOTES);
        // sessid в самом URL, а не в теле запроса: виджет — сторонний скрипт api.esplc.ru,
        // его POST-тело мы не формируем и не контролируем, но URL data-controller задаём сами,
        // так что это единственный способ дать ActionFilter\Csrf на widgetData (см.
        // ajaxhandler.php::configureActions) валидный токен без Authentication (виджет вызывают
        // анонимные посетители витрины). check_bitrix_sessid() ищет sessid в GET+POST вместе
        // (Bitrix мержит их в Request), поэтому GET-параметр из этого URL достаточен независимо
        // от того, что именно виджет положит в тело POST.
        $sessidAttr = htmlspecialcharsbx(bitrix_sessid(), ENT_QUOTES);
        // widgetData проксирует запросы только с ключом, выданным этой сессии
        \Eshoplogistic\Delivery\Controller\AjaxHandler::rememberIssuedWidgetKey((string)$widgetKey);
        // Логика обнаружения зависшего/сломанного виджета и весь связанный с ней JS
        // живут в install/js/framev2-script.js (см. #eslCalcErrorMsg ниже) — здесь только
        // разметка. #eShopLogisticWidgetCart лежит в #invisibleBlockEsl (display:none, см.
        // framev2-style.css) до открытия попапа, поэтому сообщение об ошибке нельзя
        // вставлять внутрь него — оно будет не видно пользователю.
        $html = "<div id='invisibleBlockEsl'><div id='eShopLogisticWidgetCart' data-key='" . $widgetKeyAttr . "' style='display: block;' data-lazy-load='false' data-controller='/bitrix/services/main/ajax.php?action=eshoplogistic:delivery.api.ajaxhandler.widgetData&sessid=" . $sessidAttr . "' data-v-app></div></div>";
        $html .= "<script src='https://api.esplc.ru/widgets/cart/app.js'></script>";

        // Габариты берём тем же резолвером, что и обычный чекаут (Dimensions::resolveForProducts,
        // приоритет источников настраивается в options.php), а не из $item['DIMENSIONS'] — это
        // стандартное поле корзины Bitrix, которое само заполняется только из стандартных
        // WIDTH/HEIGHT/LENGTH товара и не знает про кастомные свойства.
        // ВАЖНО: $item['ID'] в BASKET_ITEMS — это ID строки корзины (b_sale_basket.ID), а не
        // товара/предложения (см. sale.order.ajax/class.php: $arElementId[] = $arBasketItem["PRODUCT_ID"],
        // отдельное поле). Резолвер должен получать именно PRODUCT_ID — иначе он ищет
        // несуществующий элемент и всегда падает на дефолт (это и было причиной "габариты
        // всегда 0" при заполненном кастомном свойстве).
        $productIds = array_map(static function ($item) { return (int)$item['PRODUCT_ID']; }, $arResult['BASKET_ITEMS']);
        $productDimensions = $productIds ? \Eshoplogistic\Delivery\Helpers\Dimensions::resolveForProducts($productIds) : [];

		foreach ($arResult['BASKET_ITEMS'] as $item) {
            $dimensions = $productDimensions[(int)$item['PRODUCT_ID']] ?? [];
            $itemWidth  = $dimensions['WIDTH']  ?? $width;
            $itemHeight = $dimensions['HEIGHT'] ?? $height;
            $itemLength = $dimensions['LENGTH'] ?? $length;
			$offers[] = array(
				'article' => $item['ID'],
				'name' => $item['NAME'],
				'count' => $item['QUANTITY'],
				'price' => $item['PRICE'],
				'weight' => isset($item['WEIGHT']) && $item['WEIGHT'] != '0.00' ? $item['WEIGHT'] / 1000 : $weightDefault,
                "dimensions" => $itemWidth."*".$itemHeight."*".$itemLength
			);
		}
		$jsonValueOffers = htmlspecialcharsbx(\Bitrix\Main\Web\Json::encode($offers), ENT_QUOTES);
		$html .= '<input id="widgetOffersEsl" value="' . $jsonValueOffers . '" type="hidden">';

		$configClass = new Config();
		$paymentTypesList = $configClass->getPaymentTypes();
        $paymentResult = array();

        foreach ($paymentTypesList as $key=>$payment) {
            $paymentType = self::getCurrentPaymentTypes($key);
            if ($paymentType === '') continue;

            $paymentResult[$paymentType] = $payment;
        }

		$jsonValuePayment = htmlspecialcharsbx(\Bitrix\Main\Web\Json::encode($paymentResult), ENT_QUOTES);
		$html .= '<input id="widgetPaymentEsl" value="' . $jsonValuePayment . '" type="hidden">';

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