<?php

namespace Eshoplogistic\Delivery\Event;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Delivery\Services\Manager;
use CSaleOrder;
use Eshoplogistic\Delivery\Api\Export;
use Eshoplogistic\Delivery\Api\Site;
use Eshoplogistic\Delivery\Config;
use Bitrix\Sale;
use Eshoplogistic\Delivery\Helpers\ExportFileds;
use Eshoplogistic\Delivery\Helpers\ShippingHelper;
use Eshoplogistic\Delivery\Logger\Logger;

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

        // OnAdminContextMenuShow — общее событие ядра для контекстного меню ЛЮБОГО
        // списка в админке (сайты, каталог, пользователи и т.д.), не только заказов.
        // Без этой проверки loadShippingMethodsAnswer() ниже вызывает Sale\Order::load()
        // с чужим ID (или 0 на страницах без параметра ID), а тот бросает
        // ArgumentNullException — страница вроде "Список сайтов" падает с ошибкой.
        $isOrderPage = $_SERVER['REQUEST_METHOD'] == 'GET' && $elementId > 0
            && in_array($GLOBALS['APPLICATION']->GetCurPage(), array(
                '/bitrix/admin/sale_order_edit.php',
                '/bitrix/admin/sale_order_view.php',
            ), true);
        if (!$isOrderPage) {
            return;
        }

        $arReports = array();
        $exportAnswer = self::loadShippingMethodsAnswer($elementId);
        $isUnloaded = !empty($exportAnswer['order']['id']);

        if (!$isUnloaded) {
            $arReports[] = array(
                "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER"),
                "LINK" => "/bitrix/admin/eshoplogistic_delivery_form.php?elementId=" . $elementId . "",
            );
        } else {
            // Обновление/проверка статуса, печать и удаление работают только с заказом,
            // уже созданным у ТК (нужен order.id из ответа), — до выгрузки их не показываем.
            $arReports[] = array(
                "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER_UPDATE"),
                "ACTION" => "(new BX.CAdminDialog({
				'content_url': '/bitrix/admin/eshoplogistic_delivery_updatestatus.php?elementId=" . $elementId . "&sessid=" . bitrix_sessid() . "',
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
                "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT"),
                "ACTION" => "(new BX.CAdminDialog({
				'content_url': '/bitrix/admin/eshoplogistic_delivery_print.php?elementId=" . $elementId . "',
				'draggable': true,
				'resizable': true,
				'width' : 700,
				'height' : 500
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
        }

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && $GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order_edit.php' && $_REQUEST['ID'] > 0
            || $_SERVER['REQUEST_METHOD'] == 'GET' && $GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order_view.php' && $_REQUEST['ID'] > 0) {
            // Скрывает строку свойства ESHOPLOGISTIC_SHIPPING_METHODS в таблице свойств заказа -
            // сама логика в install/js/admin.js (unloading_lib), тут только подключение файла.
            \CUtil::InitJSCore(['unloading_lib']);
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
        // Защита от гонки при двойном клике/повторной отправке формы выгрузки: без неё
        // два параллельных запроса на один и тот же заказ могли независимо уйти в API
        // с 'action' => 'create' и создать у ТК два разных отправления на один заказ.
        $orderId = (int)($data['order_id'] ?? 0);
        $lockName = 'esl_unload_order_' . $orderId;
        $connection = $orderId > 0 ? Application::getConnection() : null;

        if ($connection && !$connection->lock($lockName, 0)) {
            return ['errors' => ['request' => Loc::getMessage('ESHOP_LOGISTIC_UNLOADING_ORDER_LOCKED')]];
        }

        try {

        $defaultParamsCreate = $this->defaultFieldApiCreate($data);

        $export = new Export();
        $result = $export->sendExport($defaultParamsCreate);

        if (empty($result)) {
            // Client::request() возвращает null при сетевом сбое или невалидном/пустом
            // ответе API (например, из-за неверного ключа) — без этого такой результат
            // молча принимался за успех выгрузки заказа перевозчику.
            $result = ['errors' => ['request' => 'Empty or invalid API response']];
        }

        // API отдаёт ошибки create-запроса в двух разных формах в зависимости от типа
        // сбоя: настоящая ошибка валидации полей — 'errors' в корне ответа (проверено
        // вживую), а общая ошибка выгрузки (в т.ч. фейковая, см. Config::API_FAKE_MODE=3)
        // — 'errors' внутри 'data'. Раньше проверялся только корень, поэтому такой ответ
        // (http_status 422) молча принимался за успешную выгрузку.
        if (!empty($result['data']['errors']) && !isset($result['errors'])) {
            $result['errors'] = $result['data']['errors'];
        }

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
                    if ($hasError) {
                        // СДЭК принимает create() сразу ("accepted"), а валидирует заказ
                        // асинхронно: ошибка в get-ответе (например "выбранный тариф
                        // недоступен", http 422) означает, что ТК заказ отклонила и в ЛК его
                        // нет. Сохранённый выше ответ create() откатываем — иначе меню заказа
                        // считает его выгруженным и не даёт выгрузить заново после исправления.
                        $this->saveShippingMethodsAnswer($orderId, null);
                    }
                    if (!empty($resultGet['data']['state']['errors'])) {
                        $resultGet['errors'] = $resultGet['data']['state']['errors'];
                    } elseif (!isset($resultGet['errors'])) {
                        // Пустой массив здесь означал бы "isset(errors) == true, но без текста" —
                        // вызывающий код (и UI) отличает наличие ошибки только по isset(), поэтому
                        // без реального сообщения ошибка есть, а показать нечего (и http_status_message
                        // от get-запроса в этом случае — "OK", т.к. HTTP-статус запроса не про это).
                        $resultGet['errors'] = ['request' => 'Трек-номер не подтверждён ТК в отведённое время'];
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
        } finally {
            if ($connection) {
                $connection->unlock($lockName);
            }
        }
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
                'fake' => Config::API_FAKE_MODE,
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
        if (empty($data['delivery_id']))
            return false;

        $apiKey = Option::get(Config::MODULE_ID, 'api_key');

        if (empty($apiKey))
            return false;

        $deliveryId = $data['delivery_id'];
        if (isset($data['fulfillment']))
            $deliveryId = 'pochtalion';

        // По умолчанию (как в МС): ставка НДС — из формы либо -1 ("без НДС"), стоимость — расчётная.
        // Поле "Сумма к взятию с получателя" (delivery-custom-cost) подменяет и cost, и vat_rate
        // ТОЛЬКО когда заказ уже оплачен (payment_type=already_paid) — это декларируемая стоимость
        // для собственного учёта магазина, а не сумма, которую ТК должен взыскать с получателя.
        // При наложенном платеже (cash_on_receipt и т.п.) ТК обязан получить реальную esl-unload-price,
        // подменять её нельзя — иначе с получателя будет запрошена не та сумма при вручении.
        $vatRate = $data['delivery-vat_rate'] ?? -1;
        $cost = $data['esl-unload-price'];
        if (isset($data['delivery']['delivery-custom-cost'])) {
            if ($data['payment_type'] === 'already_paid' && $data['delivery']['delivery-custom-cost'] !== '') {
                $vatRate = Option::get(Config::MODULE_ID, 'cost-custom-delivery-' . $deliveryId, $vatRate);
                $cost = $data['delivery']['delivery-custom-cost'];
            }
            unset($data['delivery']['delivery-custom-cost']);
        }

        $defaultFields = array(
            'key' => $apiKey, //Ключ доступа
            'action' => 'create', //Значение: create
            'cms' => 'bitrix',
            'service' => $deliveryId,
            // Тестовый режим — см. Config::API_FAKE_MODE (портировано из МойСклад,
            // AppConfig->appFake): API сам подменяет ответ, не обращаясь к реальной ТК.
            'fake' => Config::API_FAKE_MODE,
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
                'vat_rate' => $vatRate, //Значение ставки НДС на доставку
                'cost' => $cost, //Стоимость доставки, рубли.
                'location_to' => array(),
            ),
        );

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
            $priceNull = Config::isCarrierFeatureEnabled('type_price_null', $deliveryId)
                && Option::get(Config::MODULE_ID, 'type-price-null-' . $deliveryId) == 'Y';
            // Ставка НДС по месту по умолчанию — та же настройка ТК, что и для delivery.vat_rate.
            $defaultPlaceVatRate = Option::get(Config::MODULE_ID, 'cost-custom-delivery-' . $deliveryId, -1);

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
                    'price' => $item['price'], // цена за единицу товара (не итог по позиции)
                    'weight' => $item['weight'], //Вес, в кг.
                    'dimensions' => $item['width'] . '*' . $item['length'] . '*' . $item['height'], //Габариты. Формат: строка вида «Д*Ш*В», в сантиметрах. Например: 15*25*10
                    'vat_rate' => $item['vat'] ?? $defaultPlaceVatRate, //Значение ставки НДС Возможные варианты:0, 10, 20, -1 (без НДС)
                );

                if ($priceNull) {
                    $place['declared_price'] = 0;
                }

                $defaultFields['places'][] = $place;
            }
        }

        if (isset($data['order']) && $data['order']) {
            foreach ($data['order'] as $key => $value){
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

        $sellerName = Config::isCarrierFeatureEnabled('seller', $deliveryId) ? Option::get(Config::MODULE_ID, 'seller-name-' . $deliveryId, '') : '';
        $sellerPhone = Config::isCarrierFeatureEnabled('seller', $deliveryId) ? Option::get(Config::MODULE_ID, 'seller-phone-' . $deliveryId, '') : '';
        if ($sellerName !== '' || $sellerPhone !== '') {
            $defaultFields['seller'] = array(
                'name' => $sellerName,
                'phone' => $sellerPhone,
            );
        }

        // Точка доработки: обработчик события в проекте получает уже полностью собранный
        // запрос на выгрузку (places/receiver/sender/delivery и т.д.) и может поправить его
        // перед фактической отправкой ТК — например, если штатных настроек ТК не хватает.
        // См. Config::EVENT_BEFORE_EXPORT.
        $originalFields = $defaultFields;

        // 'order' — загруженный объект заказа сайта (не заказа ТК), чтобы обработчик мог
        // читать свойства/состав заказа, а не только плоские данные формы выгрузки ($data).
        $order = !empty($data['order_id']) ? Sale\Order::load($data['order_id']) : null;

        $event = new Event(Config::MODULE_ID, Config::EVENT_BEFORE_EXPORT, array(
            'order' => $order,
            'data' => $data,
            'fields' => $defaultFields,
        ));
        $event->send();
        foreach ($event->getResults() as $eventResult) {
            if ($eventResult->getType() !== EventResult::SUCCESS) continue;
            $modified = $eventResult->getParameters();
            if (isset($modified['fields'])) {
                $defaultFields = $modified['fields'];
            }
        }

        if ($defaultFields !== $originalFields) {
            // 'key' — токен доступа к API, в журнал событий не пишем
            $sanitizedBefore = $originalFields;
            $sanitizedAfter = $defaultFields;
            unset($sanitizedBefore['key'], $sanitizedAfter['key']);

            Logger::log(
                'EVENT_BEFORE_EXPORT',
                'Заказ #' . ($data['order_id'] ?? '') . ', ТК: ' . ($data['delivery_id'] ?? '') . '<br>'
                . 'До:<br>' . Logger::pretty($sanitizedBefore) . '<br>'
                . 'После:<br>' . Logger::pretty($sanitizedAfter),
                \CEventLog::SEVERITY_INFO,
                $data['order_id'] ?? false
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
            'service' => $nameCurrectDelivery,
            'fake' => Config::API_FAKE_MODE,
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
            $saveResult = $order->save();

            if (!$saveResult->isSuccess()) {
                return [
                    'type' => 'error',
                    'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_ERR") . ': ' . implode('; ', $saveResult->getErrorMessages())
                ];
            }

            return ['type' => 'success', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_OK")];
        }

        $apiStatusCode = $id['state']['status']['code'] ?? '';
        $apiStatusDescription = $id['state']['status']['description'] ?? '';

        $message = Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_ERR");
        if ($apiStatusCode) {
            $message .= ': ' . Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_ERR_NO_MAPPING", [
                '#CODE#' => $apiStatusCode,
                '#DESCRIPTION#' => $apiStatusDescription ?: $apiStatusCode,
            ]);
        }

        return ['type' => 'error', 'message' => $message];
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

    /** Поддерживает ли служба доставки этого заказа удаление заказа через API у ТК —
     * портировано из МойСклад (MainMenu.php: clientState->services[code]->order->delete,
     * получено из того же client/state, что и Site::getAuthStatus()). Не все ТК это умеют
     * (например kit, halva, pochtalion на момент проверки — delete: false).
     * @param int $orderId
     * @return bool
     */
    public function isDeleteSupportedAtCarrier($orderId)
    {
        return $this->carrierOrderCapability($orderId, 'delete');
    }

    /** Поддерживает ли служба доставки этого заказа получение печатных форм через API —
     * портировано из МойСклад (MainMenu.php: clientState->services[code]->order->print).
     * @param int $orderId
     * @return bool
     */
    public function isPrintSupportedAtCarrier($orderId)
    {
        return $this->carrierOrderCapability($orderId, 'print');
    }

    /** @param int $orderId
     * @param string $capability 'delete'|'print'|'get'|'create'|'track' (см. client/state)
     * @return bool
     */
    private function carrierOrderCapability($orderId, $capability)
    {
        $deliveryId = $this->resolveDeliveryId($orderId);
        if (!$deliveryId) {
            return false;
        }

        $site = new Site();
        $authStatus = $site->getAuthStatus();

        return (bool)($authStatus['settings'][$deliveryId]['order'][$capability] ?? false);
    }

    /** Удаляет заказ на стороне ТК через API (action=delete, портировано из МойСклад:
     * UnloadingOrder::infoOrder('delete') + Ajax::eslUnloadingStatusesInfo), и только при
     * успехе сбрасывает локальные данные выгрузки (см. clearUnloading). Раньше "удаление"
     * в этом модуле было только локальной очисткой полей — заказ у ТК оставался активным,
     * и его приходилось отменять там вручную.
     * @param int $orderId
     * @return array{type:string,message:string}
     */
    public function deleteUnloadingAtCarrier($orderId)
    {
        $order = Sale\Order::load($orderId);
        if (!$order) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_NOTFOUND")];
        }

        $deliveryId = $this->resolveDeliveryId($orderId);
        $answer = self::loadShippingMethodsAnswer($orderId);
        $carrierOrderId = $answer['order']['id'] ?? null;

        if (!$deliveryId || !$carrierOrderId) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_NOT_UNLOADED")];
        }

        if (!$this->isDeleteSupportedAtCarrier($orderId)) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_UNSUPPORTED")];
        }

        $apiKey = Option::get(Config::MODULE_ID, 'api_key');
        $export = new Export();
        $result = $export->sendExport([
            'key' => $apiKey,
            'action' => 'delete',
            'order_id' => $carrierOrderId,
            'service' => $deliveryId,
            'fake' => Config::API_FAKE_MODE,
        ]);

        if (empty($result)) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_ERR")];
        }

        // Та же нормализация формы ошибки, что и в params_delivery_init(): API отдаёт часть
        // ошибок как 'errors' в корне ответа, часть — вложенными в 'data.errors'.
        if (!empty($result['data']['errors']) && !isset($result['errors'])) {
            $result['errors'] = $result['data']['errors'];
        }

        if (isset($result['errors'])) {
            $errorText = self::flattenErrors($result['errors']);
            $message = Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_ERR");
            if ($errorText !== '') {
                $message .= ': ' . $errorText;
            }

            return ['type' => 'error', 'message' => $message];
        }

        $clearResult = $this->clearUnloading($orderId);
        if ($clearResult['type'] === 'success') {
            $clearResult['message'] = Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_OK");
        }

        return $clearResult;
    }

    /** Получает у ТК ссылку на печатную форму (action=print, портировано из МойСклад:
     * UnloadingPrint::initType()). Набор доступных $mode/$type — Config::PRINT_FORM_BUTTONS.
     * @param int $orderId
     * @param string $mode код формы (barcodes/order/label/act и т.п. — зависит от ТК)
     * @param string $paper формат бумаги (см. Config::PRINT_FORM_PAPER_TYPES), необязательно
     * @param string $type подвид формы (напр. у Яндекса 'one'/'many' — этикеток на страницу)
     * @return array{type:string,message?:string,url?:string}
     */
    public function getPrintForm($orderId, $mode, $paper = '', $type = '')
    {
        $deliveryId = $this->resolveDeliveryId($orderId);
        $answer = self::loadShippingMethodsAnswer($orderId);
        $carrierOrderId = $answer['order']['id'] ?? null;

        if (!$deliveryId || !$carrierOrderId) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_NOT_UNLOADED")];
        }

        $data = [
            'key' => Option::get(Config::MODULE_ID, 'api_key'),
            'action' => 'print',
            'order_id' => $carrierOrderId,
            'service' => $deliveryId,
            'mode' => $mode,
            'fake' => Config::API_FAKE_MODE,
        ];

        if ($paper !== '') {
            $data['format'] = $paper;
        }
        if ($type !== '') {
            $data['type'] = $type;
        }

        $export = new Export();
        $result = $export->sendExport($data);

        if (empty($result)) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_ERR")];
        }

        if (!empty($result['data']['errors']) && !isset($result['errors'])) {
            $result['errors'] = $result['data']['errors'];
        }

        if (isset($result['errors'])) {
            $errorText = self::flattenErrors($result['errors']);
            $message = Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_ERR");
            if ($errorText !== '') {
                $message .= ': ' . $errorText;
            }

            return ['type' => 'error', 'message' => $message];
        }

        $url = $result['data']['url'] ?? '';
        if (!$url) {
            return ['type' => 'error', 'message' => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_EMPTY")];
        }

        return ['type' => 'success', 'url' => $url];
    }

    /** Публичная обёртка над resolveDeliveryId() — нужна view-слою (print.php), чтобы
     * заранее подобрать набор кнопок печатных форм для службы доставки этого заказа.
     * @param int $orderId
     * @return string|null
     */
    public function getDeliveryId($orderId)
    {
        return $this->resolveDeliveryId($orderId);
    }

    /** @param int $orderId
     * @return string|null код службы доставки (см. ShippingHelper::getSlugMethod), либо null
     */
    private function resolveDeliveryId($orderId)
    {
        $orderData = CSaleOrder::GetByID($orderId);
        if (!$orderData) {
            return null;
        }

        $shippingHelper = new ShippingHelper();

        return $shippingHelper->getSlugMethod($orderData['DELIVERY_ID']);
    }

    /** Читает сохранённый ответ ТК (см. saveShippingMethodsAnswer) — order_id/трек-код/статус
     * у ТК из свойства заказа ESHOPLOGISTIC_SHIPPING_METHODS.
     * @param int $orderId
     * @return array|null
     */
    public static function loadShippingMethodsAnswer($orderId)
    {
        $order = Sale\Order::load($orderId);
        if (!$order) {
            return null;
        }

        foreach ($order->getPropertyCollection() as $propertyItem) {
            if ($propertyItem->getField("CODE") == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
                $value = $propertyItem->getValue();
                if ($value) {
                    $decoded = json_decode($value, true);

                    return $decoded['answer'] ?? null;
                }
            }
        }

        return null;
    }

    /** Разворачивает произвольно вложенный массив ошибок API в одну читаемую строку.
     * @param mixed $errors
     * @return string
     */
    private static function flattenErrors($errors)
    {
        if (!is_array($errors)) {
            return (string)$errors;
        }

        $flat = [];
        array_walk_recursive($errors, function ($value) use (&$flat) {
            $flat[] = $value;
        });

        return implode('; ', $flat);
    }

}
