<?php

use Bitrix\Main,
    Bitrix\Sale,
    Bitrix\Main\Loader,
    Eshoplogistic\Delivery\Event\Unloading,
    Eshoplogistic\Delivery\Helpers\Table,
    Eshoplogistic\Delivery\Config,
    Eshoplogistic\Delivery\Helpers\ShippingHelper;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php"); // первый общий пролог
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/subscribe/include.php"); // инициализация модуля
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/subscribe/prolog.php"); // пролог модуля


Loader::includeModule("sale");
Loader::includeModule("eshoplogistic.delivery");
IncludeModuleLangFile(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight("subscribe");
if ($POST_RIGHT == "D")
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));


$ID = intval($_REQUEST['elementId']);
$message = null;
$bVarsFromForm = false; // флаг "Данные получены с формы", обозначающий, что выводимые данные получены с формы, а не из БД.

$order = Sale\Order::load($ID);
$basket = $order->getBasket();
$basketItems = $basket->getBasketItems();
$orderItems = array();
$orderData = CSaleOrder::GetByID($ID);
$propertyCollection = $order->getPropertyCollection();
$shipmentCollection = $order->getShipmentCollection()->getNotSystemItems();

$APPLICATION->SetTitle(($ID > 0 ? GetMessage("UNLOADING_TITLE_EDIT") . $ID : ''));

$unloading = new Unloading();
$status = $unloading->infoOrder($ID);

$propertyCollection = $order->getPropertyCollection();
foreach ($propertyCollection as $propertyItem) {
    $propertyCode = $propertyItem->getField("CODE");
    if ($propertyCode == 'ADDRESS') {
        $propertyAddress = $propertyItem->getValue();
    }
    if ($propertyCode == 'ESHOPLOGISTIC_PVZ') {
        $propertyAddressPVZ = $propertyItem->getValue();
    }
    if ($propertyCode == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
        $shippingMethods = $propertyItem->getValue();
        if ($shippingMethods)
            $shippingMethods = json_decode($shippingMethods, true);
    }
    $propertyCodeValue[$propertyCode] = $propertyItem->getValue();
}

$html = '';
if (isset($status['data']['messages'])) {
    $html = '<div class="esl-status_infoTitle">' . $status['data']['messages'] . '</div>';
}
if (isset($result['data']['state']['number'])) {
    $html .= '<div class="esl-status_infoTitle">' . Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFOTITILE") . ': ' . $status['data']['state']['number'] . '</div>';
}
if (isset($shippingMethod) && $shippingMethod) {
    $shippingMethods = json_decode($shippingMethod, true);
    if (isset($shippingMethods['answer']['order']['id'])) {
        $html .= '<div class="esl-status_infoTitle">' . Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFOTITILE") . ': ' . $shippingMethods['answer']['order']['id'] . '</div>';
    }
}
if (isset($status['data']['order']['orderId'])) {
    $html .= '<div class="esl-status_infoTitle">' . Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFOTITILE_2") . ': ' . $status['data']['order']['orderId'] . '</div>';
}
if (isset($status['data']['state']['status']['description'])) {
    $html .= '<div class="esl-status_info">' . Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFO_NOW") . ': ' . $status['data']['state']['status']['description'] . '</div>';
}
if (isset($status['data']['state']['service_status']['description'])) {
    $html .= '<br><div class="esl-status_info">' . Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_DESCRIPTION") . ': ' . $status['data']['state']['service_status']['description'] . '</div>';
}

if (!$html)
    $html = '<div class="esl-status_infoTitle">' . Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_ERROR") . '</div>';

echo $html
?>


