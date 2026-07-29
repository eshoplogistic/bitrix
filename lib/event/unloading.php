<?php

namespace Eshoplogistic\Delivery\Event;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Delivery\Services\Manager;
use CSaleOrder;
use Eshoplogistic\Delivery\Api\Export;
use Eshoplogistic\Delivery\Config;
use Bitrix\Sale;
use Eshoplogistic\Delivery\Helpers\ExportFileds;
use Eshoplogistic\Delivery\Helpers\ShippingHelper;

class Unloading
{

    private $deliveryEsl = false;
    private $shippingMethods = [];

    public $defaultFields = array(
        'key' => '', //Ключ доступа
        'action' => '', //Значение: create
        'cms' => '',
        'service' => '',
        'order' => array(
            'id' => '', //Идентификатор заказа на сайте.
            'comment' => '',
        ),
        'places' => array(
            'article' => '',
            'name' => '',
            'count' => '',
            'price' => '',
            'weight' => '', //Вес, в кг.
            'dimensions' => '', //Габариты. Формат: строка вида «Д*Ш*В», в сантиметрах. Например: 15*25*10
            'vat_rate' => '' //Значение ставки НДС Возможные варианты:0, 10, 20, -1 (без НДС)
        ),
        'receiver' => array( //Данные получателя
            'name' => '',
            'phone' => ''
        ),
        'sender' => array(
            'name' => '',
            'phone' => '',
        ),
        'delivery' => array(
            'type' => '',
            'location_from' => array( //Адрес отправителя (при заборе груза от отправителя)
                'pick_up' => '', //Забор груза от отправителя
                'terminal' => '', //Идентификатор пункта приёма груза Обязательно, если delivery.location_from.pick_up === false
                'address' => array( //Адрес забора груза Обязательно, если delivery.location_from.pick_up === true
                    'region' => '', //Регион. Например: Московская область
                    'city' => '', //Населённый пункт
                    'street' => '', //Улица
                    'house' => '', //Номер строения
                    'room' => '' //Квартира / офис / помещение
                ),
            ),
            'payment' => '',
            'cost' => '', //Стоимость доставки, рубли.
            'location_to' => array(
                'terminal' => '',
                'address' => array(
                    'region' => '',
                    'city' => '',
                    'street' => '',
                    'house' => '',
                    'room' => '',
                ),
            ),
        ),
    );

    public static function OrderDetailAdminContextMenuShow(&$items)
    {
        $moduleId = Config::MODULE_ID;
        $elementId = (int)$_REQUEST['ID'];
        $currectDeliveryEsl = null;
        $checkUnloading = false;

        $arReports[] = array(
            "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER"),
            "LINK" => "/bitrix/admin/eshoplogistic_delivery_form.php?elementId=" . $elementId . "",
        );
        $arReports[] = array(
            "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER_UPDATE"),
            "ACTION" => "(new BX.CAdminDialog({
				'content_url': '/bitrix/admin/eshoplogistic_delivery_updatestatus.php?elementId=" . $elementId . "',
				'draggable': true,
				'resizable': true,
				'width' : 800,
				'height' : 400
			})).Show();",
        );
        $arReports[] = array(
            "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_CHECK_STATUS"),
            "ACTION" => "(new BX.CAdminDialog({
				'content_url': '/bitrix/admin/eshoplogistic_delivery_checkstatus.php?elementId=" . $elementId . "',
				'draggable': true,
				'resizable': true,
				'width' : 1200,
				'height' : 400
			})).Show();",
        );
        $arReports[] = array(
            "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR"),
            "ACTION" => "(new BX.CAdminDialog({
				'content_url': '/bitrix/admin/eshoplogistic_delivery_clearstatus.php?elementId=" . $elementId . "',
				'draggable': true,
				'resizable': true,
				'width' : 600,
				'height' : 260
			})).Show();",
        );

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && $GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order_edit.php' && $_REQUEST['ID'] > 0
            || $_SERVER['REQUEST_METHOD'] == 'GET' && $GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order_view.php' && $_REQUEST['ID'] > 0) {
            $GLOBALS['APPLICATION']->AddHeadString('<script>BX.ready(function(){document.querySelectorAll("td").forEach(function(td){if(td.childElementCount===0&&td.textContent.trim().indexOf("EShopLogistic данные для выгрузки")===0){var r=td.closest("tr");if(r)r.style.display="none";}});});</script>');
            $order = Sale\Order::load($elementId);
            $deliveryIds = $order->getDeliverySystemId();
            $shippingHelper = new ShippingHelper();
            foreach ($deliveryIds as $delivery) {
                $deliveryService = Manager::getObjectById($delivery);
                if ($deliveryService) {
                    $deliveryCode = $deliveryService->getCode();
                    $currectDeliveryEsl = $shippingHelper->getSlugMethod($deliveryCode);
                    if($currectDeliveryEsl)
                        $checkUnloading = $shippingHelper->checkUnloadingDelivery($currectDeliveryEsl);
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && $GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order_edit.php' && $_REQUEST['ID'] > 0 && $currectDeliveryEsl && $checkUnloading) {
            $items[] = array(
                "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER_ASSEMBLY"),
                "LINK" => "button.php",
                "TITLE" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER_ASSEMBLY"),
                "ICON" => "btn_new",
                "MENU" => $arReports
            );
        }
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && $GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order_view.php' && $_REQUEST['ID'] > 0 && $currectDeliveryEsl && $checkUnloading) {
            $items[] = array(
                "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER_2"),
                "TITLE" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER_2"),
                "ICON" => "btn_new",
                "MENU" => $arReports
            );

        }
    }

    /** Службы, у которых номер/трек-код приходят не сразу при создании заказа, а с задержкой,
     * поэтому после создания их нужно опрашивать повторно (action=get).
     * attempts   — сколько раз запрашивать статус
     * firstDelay — пауза перед первым запросом, сек.
     * retryDelay — пауза перед последующими попытками, сек.
     */
    private $pollAfterCreate = array(
        'sdek'  => array('attempts' => 1, 'firstDelay' => 3, 'retryDelay' => 3),
        'pecom' => array('attempts' => 3, 'firstDelay' => 8, 'retryDelay' => 2),
    );

    public function params_delivery_init($data)
    {
        $defaultParamsCreate = $this->defaultFieldApiCreate($data);

        $export = new Export();
        $result = $export->sendExport($defaultParamsCreate);

        if (!isset($result['errors'])) {
            if(!isset($data['order_id']))
                return false;

            $orderId = $data['order_id'];
            $deliveryId = $data['delivery_id'];
            $shippingMethods = $this->saveShippingMethodsAnswer($orderId, $result['data'] ?? null);

            if (!isset($this->pollAfterCreate[$deliveryId]) || !isset($shippingMethods['answer']['order']['id'])) {
                return $result;
            }

            $trackOrderId = $shippingMethods['answer']['order']['id'];
            $poll = $this->pollAfterCreate[$deliveryId];
            $resultGet = $this->pollExportStatus($deliveryId, $trackOrderId, $poll['attempts'], $poll['firstDelay'], $poll['retryDelay']);

            $hasError = isset($resultGet['errors']) || !empty($resultGet['data']['state']['errors']);
            $hasNumber = isset($resultGet['data']['state']['number']);

            if ($deliveryId === 'sdek') {
                // Поведение sdek оставлено как было: при отсутствии номера/ошибке возвращаем
                // именно ответ get-запроса с ошибкой, ответ create() в свойстве уже сохранён.
                if ($hasError || !$hasNumber) {
                    if (!empty($resultGet['data']['state']['errors'])) {
                        $resultGet['errors'] = $resultGet['data']['state']['errors'];
                    } elseif (!isset($resultGet['errors'])) {
                        $resultGet['errors'] = [];
                    }
                    return $resultGet;
                }

                return $result;
            }

            // Остальные службы из pollAfterCreate (сейчас — pecom): если за все попытки
            // трек так и не подтверждён — это не ошибка, а асинхронное подтверждение на
            // стороне ТК. Помечаем заказ флагом и оставляем на следующую проверку статуса
            // (вручную либо агентом UnloadingHandler).
            if (!$hasError && isset($resultGet['data']) && $resultGet['data']) {
                $this->setPendingConfirmation($orderId, false);
                $this->saveShippingMethodsAnswer($orderId, $resultGet['data']);
            } else {
                $this->setPendingConfirmation($orderId, true);
            }
        }

        return $result;
    }

    /** Сохраняет ответ ТК (create/get) в свойство заказа ESHOPLOGISTIC_SHIPPING_METHODS.answer
     * @param int $orderId
     * @param mixed $answerData
     * @return array текущее содержимое свойства после сохранения
     */
    private function saveShippingMethodsAnswer($orderId, $answerData)
    {
        $order = Sale\Order::load($orderId);
        $shippingMethods = array();

        $propertyCollection = $order->getPropertyCollection();
        foreach ($propertyCollection as $propertyItem) {
            if ($propertyItem->getField("CODE") == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
                $propertyCodeValue = $propertyItem->getValue();
                if ($propertyCodeValue) {
                    $shippingMethods = json_decode($propertyCodeValue, true) ?: array();
                }
                $shippingMethods['answer'] = $answerData;
                $propertyItem->setValue(json_encode($shippingMethods, JSON_UNESCAPED_UNICODE));
                $order->save();
            }
        }

        return $shippingMethods;
    }

    /** Ставит/снимает флаг "ожидает подтверждения от ТК" в том же свойстве заказа.
     * @param int $orderId
     * @param bool $value
     */
    private function setPendingConfirmation($orderId, $value)
    {
        $order = Sale\Order::load($orderId);
        $propertyCollection = $order->getPropertyCollection();
        foreach ($propertyCollection as $propertyItem) {
            if ($propertyItem->getField("CODE") == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
                $propertyCodeValue = $propertyItem->getValue();
                $shippingMethods = $propertyCodeValue ? (json_decode($propertyCodeValue, true) ?: array()) : array();
                if ($value) {
                    $shippingMethods['pending_confirmation'] = true;
                } else {
                    unset($shippingMethods['pending_confirmation']);
                }
                $propertyItem->setValue(json_encode($shippingMethods, JSON_UNESCAPED_UNICODE));
                $order->save();
            }
        }
    }

    /** @param int $orderId */
    public function clearPendingConfirmation($orderId)
    {
        $this->setPendingConfirmation($orderId, false);
    }

    /** @param int $orderId
     * @return bool
     */
    public function getPendingConfirmation($orderId)
    {
        $order = Sale\Order::load($orderId);
        if (!$order) {
            return false;
        }

        $propertyCollection = $order->getPropertyCollection();
        foreach ($propertyCollection as $propertyItem) {
            if ($propertyItem->getField("CODE") == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
                $propertyCodeValue = $propertyItem->getValue();
                if ($propertyCodeValue) {
                    $shippingMethods = json_decode($propertyCodeValue, true);
                    return !empty($shippingMethods['pending_confirmation']);
                }
            }
        }

        return false;
    }

    /** Опрашивает статус заказа у ТК (action=get) с повторами — номер/трек у части служб
     * появляются не сразу при создании.
     * @param string $service
     * @param string|int $orderId идентификатор заказа в системе ТК (не ID заказа сайта)
     * @param int $attempts
     * @param int $firstDelay
     * @param int $retryDelay
     * @return array|null последний ответ Export::sendExport()
     */
    private function pollExportStatus($service, $orderId, $attempts, $firstDelay, $retryDelay)
    {
        $apiKey = Option::get(Config::MODULE_ID, 'api_key');
        $export = new Export();
        $resultGet = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            sleep($attempt === 1 ? $firstDelay : $retryDelay);

            $resultGet = $export->sendExport(array(
                'key' => $apiKey,
                'action' => 'get',
                'order_id' => $orderId,
                'service' => $service,
            ));

            $hasError = isset($resultGet['errors']) || !empty($resultGet['data']['state']['errors']);
            if (!$hasError && isset($resultGet['data'])) {
                break;
            }
        }

        return $resultGet;
    }

    private function defaultFieldApiCreate($data)
    {
        if (!isset($data['delivery_id']) && !$data['delivery_id'])
            return false;

        $apiKey = Option::get(Config::MODULE_ID, 'api_key');

        if (!isset($apiKey) && !$apiKey)
            return false;

        $deliveryId = $data['delivery_id'];
        if (isset($data['fulfillment']))
            $deliveryId = 'pochtalion';

        $defaultFields = array(
            'key' => $apiKey, //Ключ доступа
            'action' => 'create', //Значение: create
            'cms' => 'bitrix',
            'service' => $deliveryId,
            'order' => array(
                'id' => $data['order_id'], //Идентификатор заказа на сайте.
                'comment' => $data['comment'],
            ),
            'receiver' => array( //Данные получателя
                'name' => $data['receiver-name'],
                'phone' => $data['receiver-phone'],
            ),
            'sender' => array( //Данные отправителя
                'name' => $data['sender-name'],
                'phone' => $data['sender-phone'],
                'email' => $data['sender-email'],
            ),
            'delivery' => array(
                'type' => $data['delivery_type'],
                'location_from' => array( //Адрес отправителя (при заборе груза от отправителя)
                    'pick_up' => $data['pick_up'] == '1', //Забор груза от отправителя
                ),
                'payment' => $data['payment_type'],
                'vat_rate' => $data['delivery-vat_rate'] ?? Option::get(Config::MODULE_ID, 'cost-custom-delivery-' . $deliveryId, -1), //Значение ставки НДС на доставку
                'cost' => $data['esl-unload-price'], //Стоимость доставки, рубли.
                'location_to' => array(),
            ),
        );

        // Если в форме указана отдельная сумма к взятию с получателя (наложенный платёж),
        // она заменяет базовую cost — те же поля delivery[take_payment]/delivery[delivery-custom-cost],
        // что и в форме экспорта ExportFileds.
        if (isset($data['delivery']['delivery-custom-cost']) && $data['delivery']['delivery-custom-cost'] !== '') {
            $defaultFields['delivery']['cost'] = $data['delivery']['delivery-custom-cost'];
            unset($data['delivery']['delivery-custom-cost']);
        }

        if ($data['pick_up'] == '1') {
            $defaultFields['delivery']['location_from']['address'] = array( //Адрес забора груза Обязательно, если delivery.location_from.pick_up === true
                'region' => $data['sender-region'], //Регион. Например: Московская область
                'city' => $data['sender-city'],
                'street' => $data['sender-street'],
                'house' => $data['sender-house'],
                'room' => $data['sender-room'],
            );
        }
        if ($data['pick_up'] == '0') {
            $defaultFields['delivery']['location_from']['terminal'] = $data['sender-terminal'];//Идентификатор пункта приёма груза Обязательно, если delivery.location_from.pick_up === false

        }

        $defaultFields['delivery']['location_to'] = array(
            'address' => array(
                'region' => $data['receiver-region'],
                'city' => $data['receiver-city'],
                'street' => $data['receiver-street'],
                'house' => $data['receiver-house'],
                'room' => $data['receiver-room'],
            ),
        );

        if ($data['delivery_type'] === 'terminal') {
            $defaultFields['delivery']['location_to']['terminal'] = $data['terminal-code'];
        }

        if (isset($data['products'])) {
            // Если в настройках ТК включена «нулевая объявленная стоимость по умолчанию»,
            // передаём declared_price = 0 по каждому месту (иначе объявленная стоимость ТК
            // берёт из price места, что не всегда нужно для деклараций малой ценности).
            $priceNull = Option::get(Config::MODULE_ID, 'type-price-null-' . $deliveryId) == 'Y';

            foreach ($data['products'] as $item) {
                if (empty($item['product_id']))
                    continue;

                // ID товара обычно есть всегда (это product_id корзины), но на случай пустого
                // значения (например, ручная строка в таблице мест) подставляем заглушку —
                // пустой article роняет запрос на стороне ТК.
                $article = trim((string)$item['product_id']);
                if ($article === '') {
                    $article = uniqid('esl_');
                }

                $place = array(
                    'article' => $article,
                    'name' => $item['name'],
                    'count' => $item['quantity'],
                    'price' => $item['total'],
                    'weight' => $item['weight'], //Вес, в кг.
                    'dimensions' => $item['width'] . '*' . $item['length'] . '*' . $item['height'], //Габариты. Формат: строка вида «Д*Ш*В», в сантиметрах. Например: 15*25*10
                    'vat_rate' => 0, //Значение ставки НДС Возможные варианты:0, 10, 20, -1 (без НДС)
                );

                if ($priceNull) {
                    $place['declared_price'] = 0;
                }

                $defaultFields['places'][] = $place;
            }
        }

        if (isset($data['order']) && $data['order']) {
            foreach ($data['order'] as $key => $value){
                if(isset($value['apply']) && $value['apply'] == 'on'){
                    $value['apply'] = true;
                }
                $defaultFields['order'][$key] = $value;
            }
        }

        $exportFields = new ExportFileds();
        $exportFields = $exportFields->sendExportFields($data['delivery_id']);
        foreach ($exportFields as $key => $value) {
            if (isset($data[$key])){
                // Глубокий merge вместо array_merge: плоский merge затирал бы вложенный массив
                // целиком (например order[combine_places]), теряя часть уже заполненных базовых полей.
                $defaultFields[$key] = self::mergeFieldsDeep($defaultFields[$key], $data[$key]);
            }
        }

        if (isset($data['fulfillment']))
            $defaultFields['delivery']['variant'] = $data['delivery_id'];

        // Доп. услуги (упаковка, тепловой режим, опасный груз и т.п.) — чекбоксы/количества
        // с формы выгрузки заказа (вкладка "Дополнительные услуги"), поля complement[код].
        if (isset($data['complement']) && is_array($data['complement'])) {
            $defaultFields['complement'] = $data['complement'];
        }

        if (isset($data['sender-custom-order-id']) && $data['sender-custom-order-id'] !== '') {
            $defaultFields['order']['id'] = $data['sender-custom-order-id'];
        }

        if (isset($data['platform_id']) && $data['platform_id'] !== '') {
            $defaultFields['delivery']['location_from']['platform_id'] = $data['platform_id'];
        }

        $sellerName = Option::get(Config::MODULE_ID, 'seller-name-' . $deliveryId, '');
        $sellerPhone = Option::get(Config::MODULE_ID, 'seller-phone-' . $deliveryId, '');
        if ($sellerName !== '' || $sellerPhone !== '') {
            $defaultFields['seller'] = array(
                'name' => $sellerName,
                'phone' => $sellerPhone,
            );
        }

        return $defaultFields;
    }

    /**
     * Рекурсивно сливает доп.поля службы доставки (ExportFileds) поверх базовых полей выгрузки.
     * @param array $base
     * @param array $overlay
     * @return array
     */
    private static function mergeFieldsDeep($base, $overlay)
    {
        foreach ($overlay as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeFieldsDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    public function infoOrder($id)
    {

        $order = Sale\Order::load($id);
        $shipmentCollection = $order->getShipmentCollection()->getNotSystemItems();
        $propertyCollection = $order->getPropertyCollection();
        $orderData = CSaleOrder::GetByID($id);

        foreach ($shipmentCollection as $shipment) {
            $orderShipping = array(
                'id' => $shipment->getField('DELIVERY_ID'),
                'name' => $orderData['DELIVERY_ID'],
                'title' => $shipment->getField('DELIVERY_NAME'),
                'total' => $shipment->getField('BASE_PRICE_DELIVERY'),
                'tax' => $shipment->getField('DISCOUNT_PRICE'),
            );
        }

        $shippingHelper = new ShippingHelper();
        $typeMethodTitle = $shippingHelper->getTypeMethod($orderShipping['name']);
        $nameCurrectDelivery = $shippingHelper->getSlugMethod($orderShipping['name']);

        foreach ($propertyCollection as $propertyItem) {
            $propertyCode = $propertyItem->getField("CODE");
            if ($propertyCode == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
                $shippingMethods = $propertyItem->getValue();
                if ($shippingMethods) {
                    $shippingMethods = json_decode($shippingMethods, true);
                    if (isset($shippingMethods['answer']['order']['id']))
                        $id = $shippingMethods['answer']['order']['id'];
                }
            }
            $propertyCodeValue[$propertyCode] = $propertyItem->getValue();
        }

        $data = array(
            'action' => 'get',
            'order_id' => $id,
            'service' => $nameCurrectDelivery
        );
        $export = new Export();
        $result = $export->sendExport($data);

        return $result;
    }


    public function updateStatusById($id, $order_id)
    {
        if (!isset($id['state']['number']) && !isset($id['state']['status']['code']))
            return false;

        $settingsStatus = json_decode(Option::get(Config::MODULE_ID, 'status-form'), true);

        $order = Sale\Order::load($order_id);
        $orderData = CSaleOrder::GetByID($order_id);

        $resultNameStatus = '';

        if (isset($settingsStatus[$id['state']['status']['code']])) {
            $resultNameStatus = $settingsStatus[$id['state']['status']['code']][0]['name'];
        }

        if ($resultNameStatus) {
            if ($orderData['STATUS_ID'] == $resultNameStatus)
                return ['type' => 'info', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_NOCHANGE")];

            $order->setField('STATUS_ID', $resultNameStatus);
            $order->save();
            return ['type' => 'success', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_OK")];
        }

        return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_ERR")];
    }

    /** Сбрасывает результат выгрузки заказа: очищает свойство ESHOPLOGISTIC_SHIPPING_METHODS
     * (order_id/трек/номер/pending_confirmation у ТК), чтобы заказ можно было выгрузить заново.
     * Статус заказа в Bitrix и позиции корзины не трогаются — модуль их не создаёт.
     * @param int $orderId
     * @return array{type:string,message:string}
     */
    public function clearUnloading($orderId)
    {
        $order = Sale\Order::load($orderId);
        if (!$order) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_NOTFOUND")];
        }

        $propertyCollection = $order->getPropertyCollection();
        $cleared = false;
        foreach ($propertyCollection as $propertyItem) {
            if ($propertyItem->getField("CODE") == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
                $propertyItem->setValue('');
                $cleared = true;
            }
        }

        if (!$cleared) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_NOTFOUND")];
        }

        $order->save();

        return ['type' => 'success', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_OK")];
    }


}
