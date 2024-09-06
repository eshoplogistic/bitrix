<?php

use Bitrix\Main,
    Bitrix\Sale,
    Bitrix\Main\Loader,
    Eshoplogistic\Delivery\Event\Unloading,
    Eshoplogistic\Delivery\Helpers\Table,
    Eshoplogistic\Delivery\Config,
    Eshoplogistic\Delivery\Helpers\ShippingHelper;
use Bitrix\Main\Config\Option;
use Eshoplogistic\Delivery\Api\Site;
use Eshoplogistic\Delivery\Helpers\ExportFileds;
use Eshoplogistic\Delivery\Api\Additional;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php"); // первый общий пролог
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/subscribe/include.php"); // инициализация модуля
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/subscribe/prolog.php"); // пролог модуля

Loader::includeModule("sale");
Loader::includeModule("eshoplogistic.delivery");
IncludeModuleLangFile(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight("subscribe");
if ($POST_RIGHT == "D") {
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}

$aTabs = [
    [
        "DIV" => "edit1",
        "TAB" => GetMessage("FORM_SECTION_1"),
        "ICON" => "main_user_edit",
        "TITLE" => GetMessage("FORM_SECTION_1"),
    ],
    [
        "DIV" => "edit2",
        "TAB" => GetMessage("FORM_SECTION_2"),
        "ICON" => "main_user_edit",
        "TITLE" => GetMessage("FORM_SECTION_2"),
    ],
    [
        "DIV" => "edit3",
        "TAB" => GetMessage("FORM_SECTION_3"),
        "ICON" => "main_user_edit",
        "TITLE" => GetMessage("FORM_SECTION_3"),
    ],
    [
        "DIV" => "edit4",
        "TAB" => GetMessage("FORM_SECTION_4"),
        "ICON" => "main_user_edit",
        "TITLE" => GetMessage("FORM_SECTION_4"),
    ],
];
$tabControl = new CAdminTabControl("tabControl", $aTabs);
$ID = intval($_REQUEST['elementId']);
$message = null;
$bVarsFromForm = false; // флаг "Данные получены с формы", обозначающий, что выводимые данные получены с формы, а не из БД.

$order = Sale\Order::load($_REQUEST['elementId']);
$basket = $order->getBasket();
$basketItems = $basket->getBasketItems();
$orderItems = [];
$orderData = CSaleOrder::GetByID($ID);
$propertyCollection = $order->getPropertyCollection();
$shipmentCollection = $order->getShipmentCollection()->getNotSystemItems();


foreach ($basket as $item) {
    $weight = $item->getWeight();
    $dimensions = $item->getField('DIMENSIONS');
    if ($dimensions) {
        $dimensions = unserialize($dimensions);
        if ($dimensions['WIDTH']) {
            $width = $dimensions['WIDTH'] / 10;
        }
        if ($dimensions['LENGTH']) {
            $height = $dimensions['LENGTH'] / 10;
        }
        if ($dimensions['HEIGHT']) {
            $length = $dimensions['HEIGHT'] / 10;
        }
    }

    $orderItems[] = [
        "product_id" => $item->getProductId(),
        "name" => $item->getField('NAME'),
        "quantity" => $item->getQuantity(),
        "total" => $item->getFinalPrice(),
        "price" => $item->getPrice(),
        "weight" => isset($weight) && $weight != '0.00' ? $weight / 1000 : 1,
        "width" => isset($width) && $width != '0.00' ? $width : 0,
        "length" => isset($length) && $length != '0.00' ? $length : 0,
        "height" => isset($height) && $height != '0.00' ? $height : 0,
    ];
}

foreach ($shipmentCollection as $shipment) {
    $orderShipping = [
        'id' => $shipment->getField('DELIVERY_ID'),
        'name' => $orderData['DELIVERY_ID'],
        'title' => $shipment->getField('DELIVERY_NAME'),
        'total' => $shipment->getField('BASE_PRICE_DELIVERY'),
        'tax' => $shipment->getField('DISCOUNT_PRICE'),
    ];
}

$checkDelivery = stripos($orderData['DELIVERY_ID'], Config::DELIVERY_CODE);
if ($checkDelivery === false) {
    return false;
}

$propertyAddress = '';
$propertyAddressPVZ = '';
$propertyCodeValue = [];
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
        if ($shippingMethods) {
            $shippingMethods = json_decode($shippingMethods, true);
        }
    }
    $propertyCodeValue[$propertyCode] = $propertyItem->getValue();
}

$propertyCodeValue['FIO'] = $propertyCodeValue['name'] ?? $propertyCodeValue['FIO'];
$propertyCodeValue['PHONE'] = $propertyCodeValue['phone'] ?? $propertyCodeValue['PHONE'];
$propertyCodeValue['EMAIL'] = $propertyCodeValue['email'] ?? $propertyCodeValue['EMAIL'];

$shippingHelper = new ShippingHelper();
$typeMethodTitle = $shippingHelper->getTypeMethod($orderShipping['name']);
$nameCurrectDelivery = $shippingHelper->getSlugMethod($orderShipping['name']);

$typeMethod = [
    'name' => $nameCurrectDelivery,
    'type' => $typeMethodTitle,
];

$cutAddressShipping = [
    'terminal' => '',
    'terminal_address' => '',
];
if ($typeMethod['type'] === 'door') {
    $addressShipping['terminal_address'] = $propertyAddress;
    $addressShipping['terminal_code'] = explode(',', $propertyAddressPVZ)[0];
}

if ($typeMethod['type'] === 'terminal') {
    $addressShipping['terminal_address'] = $propertyAddressPVZ;
    $addressShipping['terminal_code'] = explode(',', $propertyAddressPVZ)[0];
}
$additional = [
    'service' => mb_strtolower($typeMethod['name']),
    'detail' => true,
];

$methodDelivery = new ExportFileds();
$fieldDelivery = $methodDelivery->exportFields(mb_strtolower($typeMethod['name']), $shippingMethods);

$exportFields = new Additional();
$additionalFields = $exportFields->sendExport($additional);

$additionalFieldsRu = GetMessage("ADDITIONAL_FIELDS");

$siteClass = new Site();
$authStatus = $siteClass->getAuthStatus();
$fulfillment = false;
if (isset($authStatus['settings']['pochtalion'])) {
    if ($typeMethod['name'] == 'sdek' || $typeMethod['name'] == 'boxberry' || $typeMethod['name'] == 'postrf') {
        $fulfillment = $authStatus['settings']['pochtalion'];
    }
}


$orderShipping = isset($orderShipping) ? $orderShipping : [];
$address = isset($address) ? $address : [];
$addressShipping = isset($addressShipping) ? $addressShipping : [];
$typeMethod = isset($typeMethod) ? $typeMethod : [];
$additionalFields = isset($additionalFields) ? $additionalFields : [];
$exportFormSettings = isset($exportFormSettings) ? $exportFormSettings : [];
$shippingMethods = isset($shippingMethods) ? $shippingMethods : [];
$fieldDelivery = isset($fieldDelivery) ? $fieldDelivery : [];
$orderShippingId = isset($orderShippingId) ? $orderShippingId : '';

$APPLICATION->SetTitle(($ID > 0 ? GetMessage("UNLOADING_TITLE_EDIT") . $ID : ''));

$unloading = new Unloading();
$eslTable = new Table();

\CUtil::InitJSCore(['unloading_lib']);


require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

?>
<?php
// если есть сообщения об ошибках или об успешном сохранении - выведем их.
if ($_GET['UNLOADING_SAVED']) {
    CAdminMessage::ShowMessage(["MESSAGE" => GetMessage("UNLOADING_SAVED"), "TYPE" => "OK"]);
}
?>

<form method="POST" id="eslUnloadngForm" action="<?php
echo $APPLICATION->GetCurPage() ?>?elementId=<?php
echo $ID ?>"
      ENCTYPE="multipart/form-data"
      name="unloading_form">
    <div class="error-msg"></div>

    <?php
    echo bitrix_sessid_post(); ?>
    <?php
    $tabControl->Begin(); ?>
    <?php
    $tabControl->BeginNextTab(); ?>
    <?php
    if ($fulfillment): ?>
        <tr>
            <td><?php
                echo GetMessage("FULFILLMENT") ?></td>
            <td><input type="checkbox" name="fulfillment" value=""></td>
        </tr>
    <?php
    endif; ?>
    <tr>
        <td><?php
            echo GetMessage("TYPE_DELIVERY") ?>:
        </td>
        <td>
            <select name="delivery_type">
                <option value="door" <?php
                echo ($typeMethod['type'] === 'door') ? 'selected' : '' ?>>
                    <?php
                    echo GetMessage("COURIER") ?>
                </option>
                <option value="terminal" <?php
                echo ($typeMethod['type'] === 'terminal') ? 'selected' : '' ?>>
                    <?php
                    echo GetMessage("PICKUP_POINT") ?>
                </option>
            </select>
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("TERMINAL_CODE") ?></td>
        <td><input type="text" name="terminal-code" value="<?php
            echo $addressShipping['terminal_code'] ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("TERMINAL_ADDRESS") ?></td>
        <td><input type="text" name="terminal-address" value="<?php
            echo $addressShipping['terminal_address'] ?>"></td>
    </tr>
    <tr>
        <td><?php
            echo GetMessage("PAYMENT_TYPE") ?>:
        </td>
        <td>
            <select name="payment_type">
                <option value="already_paid"><?php
                    echo GetMessage("ALREADY_PAID") ?></option>
                <option value="cash_on_receipt"><?php
                    echo GetMessage("CASH_RECEIPT") ?></option>
                <option value="card_on_receipt"><?php
                    echo GetMessage("CARD_RECEIPT") ?></option>
                <option value="cashless"><?php
                    echo GetMessage("CASHLESS") ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("UNLOAD_PRICE") ?></td>
        <td><input type="text" name="esl-unload-price" value="<?php
            echo $orderData['PRICE_DELIVERY'] ?>"></td>
    </tr>
    <tr>
        <td><?php
            echo GetMessage("COMMENT") ?></td>
        <td><textarea class="typearea" name="comment" cols="45" rows="5" wrap="VIRTUAL"></textarea></td>
    </tr>

    <tr>
        <td>
            <hr>
        </td>
        <td><h3><?php
                echo GetMessage("ADD_FIELDS") ?></h3></td>
    </tr>

    <?php
    foreach ($fieldDelivery as $nameArr => $arr):
        ?>

        <?php
        foreach ($arr as $key => $value):
            $explodeKey = explode('||', $key);
            $name = $explodeKey[0];
            $type = $explodeKey[1];
            ?>

            <?php
            if ($type === 'text'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="text" name="<?php
                        echo $nameArr ?>[<?php
                        echo $name ?>]" value="<?php
                        echo $value ?>"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'date'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="date" name="<?php
                        echo $nameArr ?>[<?php
                        echo $name ?>]" value="<?php
                        echo $value ?>"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'checkbox'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="checkbox" name="<?php
                        echo $nameArr ?>[<?php
                        echo $name ?>]"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'select'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?>:
                    </td>
                    <td>
                        <select name="<?php
                        echo $nameArr ?>[<?php
                        echo $name ?>]">
                            <?php
                            foreach ($value as $k => $v): ?>
                                <option value="<?php
                                echo $k ?>"><?php
                                    echo $v ?></option>
                            <?php
                            endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'hr'): ?>
                <tr>
                    <td></td>
                    <td>
                        <hr>
                        <h3><?php
                            echo GetMessage("ADDFIELDS_HR_" . $name) ?></h3></td>
                </tr>
            <?php
            endif; ?>

        <?php
        endforeach; ?>
    <?php
    endforeach; ?>

    <?php
    $tabControl->BeginNextTab(); ?>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_NAME") ?></td>
        <td><input type="text" name="receiver-name" value="<?php
            echo $propertyCodeValue['FIO'] ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_PHONE") ?></td>
        <td><input type="text" name="receiver-phone" value="<?php
            echo $propertyCodeValue['PHONE'] ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_REGION") ?></td>
        <td><input type="text" name="receiver-region"
                   value="<?php
                   echo (isset($shippingMethods['region_to'])) ? $shippingMethods['region_to'] : '' ?>">
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_CITY") ?></td>
        <td><input type="text" name="receiver-city" value="<?php
            echo $propertyCodeValue['CITY'] ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_STREET") ?></td>
        <td><input type="text" name="receiver-street" value="<?php
            echo $propertyCodeValue['ADDRESS'] ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_HOUSE") ?></td>
        <td><input type="text" name="receiver-house" value=""></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_ROOM") ?></td>
        <td><input type="text" name="receiver-room" value=""></td>
    </tr>

    <hr>

    <tr>
        <td><?php
            echo GetMessage("DELIVERY_METHOD_TERMINAL") ?>:
        </td>
        <td>
            <select name="pick_up">
                <option value="0"><?php
                    echo GetMessage("BRING_OURSELVES") ?></option>
                <option value="1"><?php
                    echo GetMessage("TRANSPORT_COMPANY_PICK") ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_NAME") ?></td>
        <td><input type="text" name="sender-name"
                   value="<?php
                   echo Option::get(Config::MODULE_ID, 'sender-name') ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_PHONE") ?></td>
        <td><input type="text" name="sender-phone"
                   value="<?php
                   echo Option::get(Config::MODULE_ID, 'sender-phone') ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_EMAIL") ?></td>
        <td><input type="text" name="sender-email"
                   value="<?php
                   echo Option::get(Config::MODULE_ID, 'sender-email') ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_TERMINAL") ?></td>
        <td><input type="text" name="sender-terminal"
                   value="<?php
                   echo Option::get(Config::MODULE_ID, 'sender-terminal-' . $typeMethod['name']) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_REGION") ?></td>
        <td><input type="text" name="sender-region"
                   value="<?php
                   echo Option::get(Config::MODULE_ID, 'sender-region') ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_CITY") ?></td>
        <td><input type="text" name="sender-city" value="<?php
            echo Option::get(Config::MODULE_ID, 'sender-city') ?>">
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_STREET") ?></td>
        <td><input type="text" name="sender-street"
                   value="<?php
                   echo Option::get(Config::MODULE_ID, 'sender-street') ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_HOUSE") ?></td>
        <td><input type="text" name="sender-house" value="<?php
            echo Option::get(Config::MODULE_ID, 'sender-house') ?>">
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_ROOM") ?></td>
        <td><input type="text" name="sender-room" value="<?php
            echo Option::get(Config::MODULE_ID, 'sender-room') ?>">
        </td>
    </tr>

    <?php
    $tabControl->BeginNextTab(); ?>
    <?php
    $eslTable->prepare_items($orderItems);
    $eslTable->display();
    ?>

    <?php
    $tabControl->BeginNextTab(); ?>
    <?php
    if (isset($additionalFields['data']) && $additionalFields['data']): ?>
        <tr class="esl-box_add">
            <?php
            foreach ($additionalFields['data'] as $key => $value): ?>
                <p class="titleBox"><?php
                    echo ($additionalFieldsRu[$key]) ?? $key ?></p>
                <?php
                foreach ($value as $k => $v):
                    if (!isset($v['name'])) {
                        continue;
                    }
                    ?>
                    <div class="form-field_add">
                        <label class="label" for="<?php
                        echo $k ?>"><?php
                            echo $v['name'] ?></label>
                        <?php
                        if ($v['type'] === 'integer'): ?>
                            <input class="form-value_add" name="<?php
                            echo $k ?>" type="number"
                                   value="0" max="<?php
                            echo $v['max_value'] ?>">
                        <?php
                        else: ?>
                            <input class="form-value_add" name="<?php
                            echo $k ?>" type="checkbox">
                        <?php
                        endif; ?>
                    </div>
                <?php
                endforeach; ?>
            <?php
            endforeach; ?>
        </tr>
    <?php
    else: ?>
        <tr>
            <td width="100%"><?php
                echo GetMessage("ERROR_SERVICES") ?></td>
        </tr>
    <?php
    endif; ?>


    <?php
    $tabControl->Buttons(
        [
            "disabled" => ($POST_RIGHT < "W"),
            "back_url" => "/bitrix/admin/sale_order_view.php?ID=" . $orderData['ID'] . "&lang=" . LANG,

        ],
    );
    ?>
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="delivery_id" value="<?php
    echo $typeMethod['name'] ?>">
    <input type="hidden" name="order_id" value="<?php
    echo $orderData['ID'] ?>">
    <input type="hidden" name="order_status" value="<?php
    echo $orderData['STATUS_ID'] ?>">
    <input type="hidden" name="order_shipping_id" value="<?php
    echo $orderData['DELIVERY_ID'] ?>">
    <?php
    if ($ID > 0): ?>
        <input type="hidden" name="ID" value="<?= $ID ?>">
    <?php
    endif; ?>
    <?php
    $tabControl->End(); ?>
    <?php
    $tabControl->ShowWarnings("post_form", $message); ?>

    <?php
    echo BeginNote(); ?>
    <span class="required">*</span><?php
    echo GetMessage("REQUIRED_FIELDS") ?>
    <?php
    echo EndNote(); ?>
</form>
<script>
    ajaxFormEsl(document.getElementById('eslUnloadngForm'), '/bitrix/services/main/ajax.php?action=eshoplogistic:delivery.api.ajaxhandler.unloadingForm')
</script>
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php"); ?>
