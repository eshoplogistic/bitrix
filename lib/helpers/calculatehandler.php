<?

namespace Eshoplogistic\Delivery\Helpers;

use Bitrix\Bizproc\Workflow\Template\Packer\Result\Pack;
use \Bitrix\Sale,
    \Bitrix\Main\Error,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\EventManager,
    \Bitrix\Main\Event,
    \Bitrix\Main\EventResult,
    \Bitrix\Main\Config\Option,
    \Eshoplogistic\Delivery\Api,
    \Eshoplogistic\Delivery\Helpers,
    \Bitrix\Main\Application,
    \Eshoplogistic\Delivery\Config;

use Eshoplogistic\Delivery\Logger\Logger;

//EventManager::getInstance()->addEventHandler('sale', 'onSaleDeliveryServiceCalculate', 'getDefaultCalculateDelivery');
/** Class for calculate deliveries
 * Class LocationHandler
 * @package Eshoplogistic\Delivery\Helpers
 * @author negen
 */
class CalculateHandler
{
    /** Счётчик вложенных вызовов withRealCalculation(): пока > 0, skipRealCalculation()
     * всегда возвращает false. Выставляется только серверным кодом перед сохранением заказа.
     * @var int
     */
    private static $forceRealCalculation = 0;

    /** Ошибки расчёта служб модуля, случившиеся внутри последнего withRealCalculation()
     * @var \Bitrix\Main\Error[]
     */
    private static $realCalculationErrors = [];

    /** Выполняет $callback с гарантированно реальным расчётом доставки (без нулевой заглушки
     * режима виджета). Используется на всех точках сохранения заказа — признак "это финальное
     * сохранение" определяется сервером, а не клиентским параметром запроса.
     * @param callable $callback
     * @return mixed результат $callback
     */
    public static function withRealCalculation(callable $callback)
    {
        if (self::$forceRealCalculation === 0) {
            self::$realCalculationErrors = [];
        }
        self::$forceRealCalculation++;
        try {
            return $callback();
        } finally {
            self::$forceRealCalculation--;
        }
    }

    /** Calculating deliveries
     * @param Sale\Shipment $shipmentx
     * @param string $service
     * @param string $type
     * @return Sale\Delivery\CalculationResult $result
     */
    /** Ошибки расчёта служб доставки модуля за последний withRealCalculation(). Нужны, чтобы
     * отличить отказ НАШЕГО расчёта от ошибок служб доставки других модулей в общем
     * результате ShipmentCollection::calculateDelivery().
     * @return \Bitrix\Main\Error[]
     */
    public static function getRealCalculationErrors()
    {
        return self::$realCalculationErrors;
    }

    public static function getDefaultCalculateDelivery(Sale\Shipment $shipment, $service, $type)
    {
        $result = self::calculateShipment($shipment, $service, $type);
        if (self::$forceRealCalculation > 0 && !$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                self::$realCalculationErrors[] = $error;
            }
        }

        return $result;
    }

    private static function calculateShipment(Sale\Shipment $shipment, $service, $type)
    {
        if (self::skipRealCalculation($shipment)) {
            $result = new Sale\Delivery\CalculationResult();
            $result->setDeliveryPrice(0);
            return $result;
        }

        $order = $shipment->getCollection()->getOrder();
        $basket = $order->getBasket();
        $props = $order->getPropertyCollection();
        $locationCode = $props->getDeliveryLocation();
        if ($locationCode) {
            $locationCode = $locationCode->getValue();
        }
        
        $addressFieldCityName = null;
        if (!$locationCode) {
            $addressRequarOption = \Bitrix\Main\Config\Option::get(Config::MODULE_ID, 'api_address_requar');
            if ($addressRequarOption) {
                $addressRequarIds = array_filter(explode(',', $addressRequarOption));
                foreach ($props as $prop) {
                    if ($prop->isUtil()) continue;
                    $arProp = $prop->getProperty();
                    if (in_array((string)$arProp['ID'], $addressRequarIds)) {
                        $val = $prop->getValue();
                        if ($val) {
                            if ($arProp['TYPE'] === 'LOCATION') {
                                $locationCode = $val;
                            } else {
                                $addressFieldCityName = $val;
                                $locationCode = $val;
                            }
                            break;
                        }
                    }
                }
            }
        }

        if (!$locationCode) {
            $locationCode = Helpers\OrderHandler::getCodeCityByApi();
        }

        $paymentCollection = $order->getPaymentCollection();

        $configClass = new Config();
        $paymentType = '';
        $paymentTypesList = $configClass->getPaymentTypes();

        foreach ($paymentCollection as $payment) {
            $paymentId = $payment->getPaymentSystemId();
            $paymentType = self::getCurrentPaymentTypes($paymentTypesList, $paymentId);
            if ($paymentType !== '') break;
        }

        $result = new Sale\Delivery\CalculationResult();

        $sendPoint = self::getSendPoint();

        $orderData['payment'] = $paymentType;

        $orderData['offers'] = OrderHandler::getCurrentBasketItems($basket);

        $deliveriesListFrom = $sendPoint['services'];
        $from = $deliveriesListFrom[$service]['city_code'];
        if ($addressFieldCityName !== null) {
            $resolved = LocationHandler::resolveCityFromText($addressFieldCityName);
            $deliveriesListTo = $resolved['parsedCity'] ?? [];
        } else {
            $deliveriesListTo = LocationHandler::getAvailableDeliveriesByLocation($locationCode);
        }
        $to = $deliveriesListTo['services'][$service]??$deliveriesListTo['fias'];


        if($service === 'dostavista'){
            $request = Application::getInstance()->getContext()->getRequest();
            $requestData = $request->getPost("order");
            $fullAdressValue = trim($requestData['ESHOPLOGISTIC_FULL_ADDRESS'] ?? '');
            if($fullAdressValue)
                $orderData['address'] = $fullAdressValue;
        }

        // Точка доработки: позволяет обработчику события в проекте скорректировать состав
        // заказа/адреса перед реальным запросом к API (например, поправить кол-во/вес позиций,
        // если это не покрывается настройками модуля).
        $originalTo = $to;
        $originalOrderData = $orderData;

        $event = new Event(Config::MODULE_ID, Config::EVENT_BEFORE_CALCULATE, array(
            'shipment' => $shipment,
            // 'order' и 'basket' — для обработчика: значения
            // свойства заказа (order->getPropertyCollection()) или реальных данных товара из
            // каталога (basketItem->getProductId() + CIBlockElement::GetByID/ProductTable).
            'order' => $order,
            'basket' => $basket,
            'service' => $service,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'orderData' => $orderData,
        ));
        $event->send();
        foreach ($event->getResults() as $eventResult) {
            if ($eventResult->getType() !== EventResult::SUCCESS) continue;
            $modified = $eventResult->getParameters();
            if (isset($modified['to'])) {
                $to = $modified['to'];
            }
            if (isset($modified['orderData'])) {
                $orderData = $modified['orderData'];
            }
        }

        if ($to !== $originalTo || $orderData !== $originalOrderData) {
            Logger::log(
                'EVENT_BEFORE_CALCULATE',
                Logger::msg('ORDER_SERVICE', ['#ORDER_ID#' => $order->getId(), '#SERVICE#' => $service]) . '<br>'
                . Logger::msg('BEFORE') . '<br>' . Logger::pretty(array('to' => $originalTo, 'orderData' => $originalOrderData)) . '<br>'
                . Logger::msg('AFTER') . '<br>' . Logger::pretty(array('to' => $to, 'orderData' => $orderData)),
                \CEventLog::SEVERITY_INFO,
                $order->getId() ?: false
            );
        }

        if (!$to) {
            // Запрос к API тут не уходит, поэтому без этой записи отказ в логе не виден вовсе.
            Logger::error(
                'CALC_NO_LOCATION',
                Logger::msg('NO_LOCATION', ['#SERVICE#' => $service, '#TYPE#' => $type, '#LOCATION#' => (string)$locationCode]),
                $order->getId() ?: false
            );
            $result->addError(new \Bitrix\Main\Error($configClass->locationError));
            return $result;
        }

        $deliveryProfileData = Api\Delivery::getLocationDeliveryData($service, $from, $to, $orderData);

        unset($deliveryProfileData['data']['terminals']);

        if (!empty($deliveryProfileData['success']) || (isset($deliveryProfileData['http_status']) && $deliveryProfileData['http_status'] == 200)) {
            if (empty($deliveryProfileData['data'][$type])) {
                $result->addError(new \Bitrix\Main\Error($configClass->dataError));
            }

            // Строгое "=== 0" не ловит нулевую цену, если API прислал её как float (0.0)
            // или числовую строку ("0.00") - тогда ниже сработала бы ложная priceError
            // для легитимной бесплатной доставки. Вложенную форму цены (['value' => 0, ...])
            // не трогаем - empty() на непустом массиве и так не считает её ошибкой.
            $rawPrice = $deliveryProfileData['data'][$type]['price'] ?? null;
            if (!is_array($rawPrice) && is_numeric($rawPrice) && (float)$rawPrice === 0.0) {
                $deliveryProfileData['data'][$type]['price'] = 'free';
            }

            if ($deliveryProfileData['data']['comments']) {
                $result->setDescription($deliveryProfileData['data']['comments']);
            }
            $deliveryPrice = $deliveryProfileData['data'][$type]['price']['value'] ?? $deliveryProfileData['data'][$type]['price'];

            $result->setDeliveryPrice(
                roundEx(
                    $deliveryPrice,
                    SALE_VALUE_PRECISION
                )
            );
            if (empty($deliveryProfileData['data'][$type]['price'])) {
                $result->addError(new \Bitrix\Main\Error($configClass->priceError));
            }

            $time = (isset($deliveryProfileData['data'][$type]['time']['value']))?
                $deliveryProfileData['data'][$type]['time']['value'].' '.$deliveryProfileData['data'][$type]['time']['unit']
                :$deliveryProfileData['data'][$type]['time'];
            $result->setPeriodDescription($time);

            if(isset($deliveryProfileData['data']['debug'])){
                $debugAnswer = $deliveryProfileData['data']['debug'];
                $debugArr = array(
                    'settlement_to' => $debugAnswer['shipping_route']['to']['settlement']??'',
                    'region_to' => $debugAnswer['shipping_route']['to']['region']??'',
                    'settlement_from' => $debugAnswer['shipping_route']['from']['settlement']??'',
                    'region_from' => $debugAnswer['shipping_route']['from']['region']??'',
                    'terminal_tarrif' => $deliveryProfileData['data'][$type]['tariff']??'',
                );
                $result->setDescription('<input name="ESHOPLOGISTIC_SHIPPING_METHODS" type="hidden" value="'.htmlspecialchars(json_encode( $debugArr )).'"/>');
            }
        } else {
            $errorString = '';
            $msg = $deliveryProfileData['msg'] ?? null;
            if (is_array($msg)) {
                foreach ($msg as $err => $text) {
                    if ($text)
                        $errorString .= 'Error: ' . $err . ' ' . $text . '. ';
                }
            } elseif ($msg) {
                $errorString = 'Error: ' . $msg;
            }

            // Ответ API с ошибкой (422 и т.п.) приходит без msg: текст лежит в errors
            // ({"to": "Ошибка определения города-получателя..."}) и http_status_message —
            // иначе покупатель видел в карточке доставки пустое "Error: ".
            if (!$errorString) {
                $texts = [];
                $errors = $deliveryProfileData['errors'] ?? null;
                if (is_array($errors)) {
                    array_walk_recursive($errors, function ($text) use (&$texts) {
                        if (is_scalar($text) && (string)$text !== '') $texts[] = (string)$text;
                    });
                } elseif (is_scalar($errors) && (string)$errors !== '') {
                    $texts[] = (string)$errors;
                }
                if (!$texts && !empty($deliveryProfileData['http_status_message'])) {
                    $texts[] = (string)$deliveryProfileData['http_status_message'];
                }
                if ($texts) $errorString = 'Error: ' . implode('. ', $texts);
            }

            if (!$errorString) $errorString = 'Unknown error';

            $result->addError(new \Bitrix\Main\Error($errorString));
        }

        return $result;
    }

    /** Bitrix Sale (sale.order.ajax) вызывает calculate() у CHECKED-профиля безусловно на
     * КАЖДОМ построении заказа — и на обычном рендере/refresh страницы оформления (там
     * Order создаётся заново и отбрасывается после вывода), и на реальном подтверждении
     * заказа (processOrderAction(): isOrderConfirmed = POST + confirmorder=Y, единственный
     * случай, когда посчитанная тут цена реально попадёт в сохранённый заказ).
     * В режиме виджета (frame_lib) чекаут показывает один смёрженный пункт "Калькулятор
     * доставки eShopLogistic" — цену/срок берёт из данных виджета или сессии (см.
     * ComponentOrder::orderDeliveryBuildListFrame), а результат ЭТОГО расчёта отбрасывает
     * целиком. Поэтому на всех НЕ-подтверждающих запросах реальный POST на api.esplc.ru тут
     * не нужен — только на реальном оформлении заказа, где считаем как обычно, и в админке
     * (ADMIN_SECTION), которая не проходит через виджет и должна видеть настоящую цену при
     * просмотре/правке заказа.
     * ВАЖНО: используемый на сайте компонент — sale.order.ajax (не старый sale.order), и его
     * фронтенд на кнопке "Оформить заказ" шлёт action=saveOrderAjax (см. bootstrap_v4/order_ajax.js,
     * OrderAjaxComponent.sendRequest) — параметра confirmorder в этом запросе нет вообще, это
     * поле от старого не-ajax компонента. Проверка по confirmorder здесь была всегда false,
     * поэтому реальный расчёт не выполнялся никогда и в заказ уходила нулевая цена — см. class.php
     * sale.order.ajax: $this->action === 'saveOrderAjax' (а не confirmorder) — тот же признак,
     * которым сам Bitrix определяет подтверждение заказа.
     * БЕЗОПАСНОСТЬ: action=saveOrderAjax — клиентский параметр, поэтому он не может быть
     * единственной защитой от сохранения заказа с нулевой ценой. Любое сохранение нового
     * заказа (sale.order.ajax, "заказ в один клик", не-AJAX submit, кастомные формы) проходит
     * через ComponentOrder::saleOrderBeforeSaved(), который пересчитывает доставку внутри
     * withRealCalculation() — там заглушка отключена. Уже сохранённые заказы заглушку не
     * получают никогда (пересчёт Bitrix при их изменении должен давать реальную цену).
     * @param Sale\Shipment $shipment
     * @return bool
     */
    private static function skipRealCalculation(Sale\Shipment $shipment)
    {
        if (!Option::get(Config::MODULE_ID, 'frame_lib')) {
            return false;
        }

        if (self::$forceRealCalculation > 0) {
            return false;
        }

        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return false;
        }

        $order = $shipment->getCollection() ? $shipment->getCollection()->getOrder() : null;
        if (!$order || !$order->isNew()) {
            return false;
        }

        $request = Application::getInstance()->getContext()->getRequest();
        if ($request->isPost() && $request->get('action') === 'saveOrderAjax') {
            return false;
        }

        return true;
    }

    /** Get paysystem type
     * @param array $paymentTypesList
     * @param integer $psID
     * @return string
     */
    private static function getCurrentPaymentTypes($paymentTypesList, $psID)
    {

        $paymentType = '';

        if (in_array($psID, $paymentTypesList['card'])) {
            $paymentType = 'card';
        } else if (in_array($psID, $paymentTypesList['cache'])) {
            $paymentType = 'cash';
        } else if (in_array($psID, $paymentTypesList['cashless'])) {
            $paymentType = 'cashless';
        } else if (in_array($psID, $paymentTypesList['prepay'])) {
            $paymentType = 'prepay';
        } else if (in_array($psID, $paymentTypesList['payment_upon_receipt'])) {
            $paymentType = 'payment_upon_receipt';
        }
        return $paymentType;
    }

    /** Get send point
     * @return string
     */
    public static function getSendPoint()
    {
        $sendPoint = Api\Site::getSendPoint();
        return $sendPoint;
    }

    /** Get current delivery PVZ list
     * @param $locationCode
     * @param $service
     * @param $paymentId
     * @return array
     */
    public static function getDefaultPvzData($locationCode, $service, $paymentId, $isAddressName = false)
    {

        $sendPoint = self::getSendPoint();

        $configClass = new Config();
        $paymentTypesList = $configClass->getPaymentTypes();
        $paymentType = self::getCurrentPaymentTypes($paymentTypesList, $paymentId);
        $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), \Bitrix\Main\Context::getCurrent()->getSite());

        $orderData['payment'] = $paymentType;

        $orderData['offers'] = Helpers\OrderHandler::getCurrentBasketItems($basket);

        $deliveriesListFrom = $sendPoint['services'];
        $from = $deliveriesListFrom[$service]['city_code'];

        if ($isAddressName) {
            $resolved = Helpers\LocationHandler::resolveCityFromText($locationCode);
            $deliveriesListTo = $resolved['parsedCity'] ?? [];
        } else {
            $deliveriesListTo = Helpers\LocationHandler::getAvailableDeliveriesByLocation($locationCode);
        }
        $to = $deliveriesListTo['fias'];

        $deliveryProfileData = Api\Delivery::getLocationDeliveryData($service, $from, $to, $orderData);

        return $deliveryProfileData;
    }
}