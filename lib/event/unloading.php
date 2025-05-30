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
        $elementId = $_REQUEST['ID'];
        $currectDeliveryEsl = null;
        $checkUnloading = false;

        $arReports[] = array(
            "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER"),
            "LINK" => "/bitrix/admin/" . $moduleId . "/unloading/form.php?elementId=" . $elementId . "",
        );
        $arReports[] = array(
            "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_ORDER_UPDATE"),
            "ACTION" => "(new BX.CAdminDialog({
				'content_url': '/bitrix/admin/" . $moduleId . "/unloading/updatestatus.php?elementId=" . $elementId . "',
				'draggable': true,
				'resizable': true,
				'width' : 800,
				'height' : 400
			})).Show();",
        );
        $arReports[] = array(
            "TEXT" => Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_CHECK_STATUS"),
            "ACTION" => "(new BX.CAdminDialog({
				'content_url': '/bitrix/admin/" . $moduleId . "/unloading/checkstatus.php?elementId=" . $elementId . "',
				'draggable': true,
				'resizable': true,
				'width' : 1200,
				'height' : 400
			})).Show();",
        );

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && $GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order_edit.php' && $_REQUEST['ID'] > 0
            || $_SERVER['REQUEST_METHOD'] == 'GET' && $GLOBALS['APPLICATION']->GetCurPage() == '/bitrix/admin/sale_order_view.php' && $_REQUEST['ID'] > 0) {
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

    public function params_delivery_init($data)
    {
        $defaultParamsCreate = $this->defaultFieldApiCreate($data);

        $export = new Export();
        $result = $export->sendExport($defaultParamsCreate);

        if (!isset($result['errors'])) {
            if(!isset($data['order_id']))
                return false;

            $order = Sale\Order::load($data['order_id']);
            $propertyCollection = $order->getPropertyCollection();
            foreach ($propertyCollection as $propertyItem) {
                $propertyCode = $propertyItem->getField("CODE");
                if ($propertyCode == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
                    $propertyCodeValue = $propertyItem->getValue();
                    if ($propertyCodeValue) {
                        $shippingMethods = json_decode($propertyCodeValue, true);
                    }
                    $shippingMethods['answer'] = $result['data'];
                    $propertyItem->setValue(json_encode($shippingMethods, JSON_UNESCAPED_UNICODE));
                    $order->save();
                }
            }

            if ($data['delivery_id'] == 'sdek' && isset($shippingMethods['answer']['order']['id'])) {
                sleep(3);

                $apiKey = Option::get(Config::MODULE_ID, 'api_key');
                $data = array(
                    'key' => $apiKey,
                    'action' => 'get',
                    'order_id' => $shippingMethods['answer']['order']['id'],
                    'service' => $data['delivery_id']
                );
                $resultGet = $export->sendExport($data);
                if (isset($resultGet['errors']) || !isset($resultGet['data']['state']['number'])) {
                    $resultGet['errors'] = $resultGet['data']['state']['errors'];
                    return $resultGet;
                }
            }
        }

        return $result;
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
                'cost' => $data['esl-unload-price'], //Стоимость доставки, рубли.
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
            foreach ($data['products'] as $item) {
                if (empty($item['product_id']))
                    continue;

                $defaultFields['places'][] = array(
                    'article' => $item['product_id'],
                    'name' => $item['name'],
                    'count' => $item['quantity'],
                    'price' => $item['total'],
                    'weight' => $item['weight'], //Вес, в кг.
                    'dimensions' => $item['width'] . '*' . $item['length'] . '*' . $item['height'], //Габариты. Формат: строка вида «Д*Ш*В», в сантиметрах. Например: 15*25*10
                    'vat_rate' => 0, //Значение ставки НДС Возможные варианты:0, 10, 20, -1 (без НДС)
                );
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
                //$defaultFields[$key] = $defaultFields[$key] + $data[$key];
                $defaultFields[$key] = array_merge($defaultFields[$key], $data[$key]);
            }
        }

        if (isset($data['fulfillment']))
            $defaultFields['delivery']['variant'] = $data['delivery_id'];

        return $defaultFields;
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
                return Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_NOCHANGE");

            $order->setField('STATUS_ID', $resultNameStatus); //статус
            $order->save();
            return Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_OK");
        }

        return Loc::GetMessage("ESHOP_LOGISTIC_UNLOADING_STATUS_ERR");

    }


}
