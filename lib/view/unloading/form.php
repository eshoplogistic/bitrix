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
use Eshoplogistic\Delivery\Helpers\AddressParser;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

Loader::includeModule("sale");
Loader::includeModule("eshoplogistic.delivery");
IncludeModuleLangFile(__FILE__);

global $USER;
$moduleRight = $APPLICATION->GetGroupRight(Config::MODULE_ID);
$saleRight   = $APPLICATION->GetGroupRight("sale");
if ($moduleRight < "R" || $saleRight < "R") {
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
$ID = (int)($_REQUEST['elementId'] ?? 0);
if ($ID <= 0) {
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}
$message = null;
$bVarsFromForm = false;

$order = Sale\Order::load($ID);
if (!$order) {
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}
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
        $dimensions = unserialize($dimensions, ['allowed_classes' => false]);
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

// В заказе адрес хранится одной строкой (покупатель пишет улицу/дом/квартиру как придётся
// на чекауте) - для ПВЗ дом/квартира не нужны, поэтому разбираем строку только для доставки
// курьером. Полю "Улица" в форме оставляем разобранную улицу, а не всю строку целиком.
$parsedAddress = ['street' => '', 'building' => '', 'room' => ''];
if ($typeMethod['type'] === 'door' && $propertyAddress !== '') {
    $parsedAddress = AddressParser::parse(
        (string)$propertyAddress,
        '',
        (string)($propertyCodeValue['CITY'] ?? ''),
        (string)($shippingMethods['region_to'] ?? '')
    );
}

// Значения по умолчанию из настроек ТК (options.php, раздел "Настройки транспортных компаний").
$paymentTypeDefault = Option::get(Config::MODULE_ID, 'payment_type-' . $typeMethod['name']);
$pickupDefault = Config::isCarrierFeatureEnabled('pickup_terminal', $typeMethod['name'])
    ? Option::get(Config::MODULE_ID, 'type_delivery_from_tk-' . $typeMethod['name'])
    : null;

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


$orderShipping = $orderShipping ?? [];
$address = $address ?? [];
$addressShipping = $addressShipping ?? [];
$typeMethod = $typeMethod ?? [];
$additionalFields = $additionalFields ?? [];
$exportFormSettings = $exportFormSettings ?? [];
$shippingMethods = $shippingMethods ?? [];
$fieldDelivery = $fieldDelivery ?? [];
$orderShippingId = $orderShippingId ?? '';

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
            <td>
                <label class="esl-toggle">
                    <input type="checkbox" name="fulfillment" value="">
                    <span class="esl-toggle__track"></span>
                </label>
            </td>
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
        <td><input type="text" name="terminal-code" value="<?= htmlspecialcharsbx((string)($addressShipping['terminal_code'] ?? '')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("TERMINAL_ADDRESS") ?></td>
        <td><input type="text" name="terminal-address" value="<?= htmlspecialcharsbx((string)($addressShipping['terminal_address'] ?? '')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_REGION") ?></td>
        <td><input type="text" name="receiver-region"
                   value="<?= htmlspecialcharsbx((string)($shippingMethods['region_to'] ?? '')) ?>">
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_CITY") ?></td>
        <td><input type="text" name="receiver-city" value="<?= htmlspecialcharsbx((string)($propertyCodeValue['CITY'] ?? '')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_STREET") ?></td>
        <td><input type="text" name="receiver-street" value="<?= htmlspecialcharsbx((string)($parsedAddress['street'] !== '' ? $parsedAddress['street'] : ($propertyCodeValue['ADDRESS'] ?? ''))) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_HOUSE") ?></td>
        <td><input type="text" name="receiver-house" value="<?= htmlspecialcharsbx((string)$parsedAddress['building']) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_ROOM") ?></td>
        <td><input type="text" name="receiver-room" value="<?= htmlspecialcharsbx((string)$parsedAddress['room']) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_NAME") ?></td>
        <td><input type="text" name="receiver-name" value="<?= htmlspecialcharsbx((string)($propertyCodeValue['FIO'] ?? '')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("RECEIVER_PHONE") ?></td>
        <td><input type="text" name="receiver-phone" value="<?= htmlspecialcharsbx((string)($propertyCodeValue['PHONE'] ?? '')) ?>"></td>
    </tr>
    <tr>
        <td><?php
            echo GetMessage("PAYMENT_TYPE") ?>:
        </td>
        <td>
            <select name="payment_type">
                <option value="already_paid" <?= ($paymentTypeDefault === 'already_paid') ? 'selected' : '' ?>><?php
                    echo GetMessage("ALREADY_PAID") ?></option>
                <option value="cash_on_receipt" <?= ($paymentTypeDefault === 'cash_on_receipt') ? 'selected' : '' ?>><?php
                    echo GetMessage("CASH_RECEIPT") ?></option>
                <option value="card_on_receipt"><?php
                    echo GetMessage("CARD_RECEIPT") ?></option>
                <option value="cashless"><?php
                    echo GetMessage("CASHLESS") ?></option>
            </select>
        </td>
    </tr>

    <?php
    // Динамические поля выгрузки текущей ТК (lib/helpers/exportfileds.php). Группы с ключом
    // "sender"/"sender[...]"/"delivery[location_from]..." относятся к отправителю и рендерятся
    // на вкладке «Данные отправителя» (см. ниже); здесь — всё, что относится к получателю/заказу.
    // "order[combine_places]" рендерится отдельно на вкладке «Места», сразу под таблицей.
    foreach ($fieldDelivery as $nameArr => $arr):
        if ($nameArr === 'hr' || $nameArr === 'hr2' || $nameArr === 'hr3') {
            continue;
        }
        if ($nameArr === 'order[combine_places]') {
            continue;
        }
        if ($nameArr === 'sender' || strpos($nameArr, 'sender[') === 0 || strpos($nameArr, 'delivery[location_from') === 0) {
            continue;
        }
        ?>

        <?php
        foreach ($arr as $key => $value):
            $explodeKey = explode('||', $key);
            $name = $explodeKey[0];
            $type = $explodeKey[1];
            ?>

            <?php
            $fieldValue  = htmlspecialcharsbx((string)$value);
            $fieldName   = htmlspecialcharsbx((string)$name);
            $fieldArr    = htmlspecialcharsbx((string)$nameArr);
            if ($type === 'text'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="text" name="<?= $fieldArr ?>[<?= $fieldName ?>]" value="<?= $fieldValue ?>"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'date'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="date" name="<?= $fieldArr ?>[<?= $fieldName ?>]" value="<?= $fieldValue ?>"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'time'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="time" name="<?= $fieldArr ?>[<?= $fieldName ?>]" value="<?= $fieldValue ?>"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'checkbox'):
                ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td>
                        <label class="esl-toggle">
                            <input type="checkbox" name="<?php
                            echo $nameArr ?>[<?php
                            echo $name ?>]" <?php echo $value ?>>
                            <span class="esl-toggle__track"></span>
                        </label>
                    </td>
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
                                echo htmlspecialcharsbx((string)$k) ?>"><?php
                                    echo htmlspecialcharsbx((string)$v) ?></option>
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

    <?php
    $tabControl->BeginNextTab(); ?>
    <tr>
        <td><?php
            echo GetMessage("DELIVERY_METHOD_TERMINAL") ?>:
        </td>
        <td>
            <select name="pick_up">
                <option value="0" <?= ($pickupDefault === '0') ? 'selected' : '' ?>><?php
                    echo GetMessage("BRING_OURSELVES") ?></option>
                <option value="1" <?= ($pickupDefault === '1') ? 'selected' : '' ?>><?php
                    echo GetMessage("TRANSPORT_COMPANY_PICK") ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_NAME") ?></td>
        <td><input type="text" name="sender-name"
                   value="<?php
                   echo htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-name')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_PHONE") ?></td>
        <td><input type="text" name="sender-phone"
                   value="<?php
                   echo htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-phone')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_EMAIL") ?></td>
        <td><input type="text" name="sender-email"
                   value="<?php
                   echo htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-email')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_TERMINAL") ?></td>
        <td><input type="text" name="sender-terminal"
                   value="<?php
                   echo Config::isCarrierFeatureEnabled('pickup_terminal', $typeMethod['name'])
                       ? htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-terminal-' . $typeMethod['name']))
                       : '' ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_REGION") ?></td>
        <td><input type="text" name="sender-region"
                   value="<?php
                   echo htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-region')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_CITY") ?></td>
        <td><input type="text" name="sender-city" value="<?php
            echo htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-city')) ?>">
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_STREET") ?></td>
        <td><input type="text" name="sender-street"
                   value="<?php
                   echo htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-street')) ?>"></td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_HOUSE") ?></td>
        <td><input type="text" name="sender-house" value="<?php
            echo htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-house')) ?>">
        </td>
    </tr>
    <tr>
        <td><span class="required">*</span><?php
            echo GetMessage("SENDER_ROOM") ?></td>
        <td><input type="text" name="sender-room" value="<?php
            echo htmlspecialcharsbx(Option::get(Config::MODULE_ID, 'sender-room')) ?>">
        </td>
    </tr>

    <?php
    // Динамические поля отправителя (группы "sender"/"sender[...]"/"delivery[location_from]...",
    // отфильтрованные из общего цикла на вкладке «Данные получателя» выше).
    foreach ($fieldDelivery as $nameArr => $arr):
        if ($nameArr === 'hr' || $nameArr === 'hr2' || $nameArr === 'hr3') {
            continue;
        }
        if (!($nameArr === 'sender' || strpos($nameArr, 'sender[') === 0 || strpos($nameArr, 'delivery[location_from') === 0)) {
            continue;
        }
        ?>

        <?php
        foreach ($arr as $key => $value):
            $explodeKey = explode('||', $key);
            $name = $explodeKey[0];
            $type = $explodeKey[1];
            ?>

            <?php
            $fieldValue  = htmlspecialcharsbx((string)$value);
            $fieldName   = htmlspecialcharsbx((string)$name);
            $fieldArr    = htmlspecialcharsbx((string)$nameArr);
            if ($type === 'text'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="text" name="<?= $fieldArr ?>[<?= $fieldName ?>]" value="<?= $fieldValue ?>"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'date'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="date" name="<?= $fieldArr ?>[<?= $fieldName ?>]" value="<?= $fieldValue ?>"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'time'): ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td><input type="time" name="<?= $fieldArr ?>[<?= $fieldName ?>]" value="<?= $fieldValue ?>"></td>
                </tr>
            <?php
            endif; ?>
            <?php
            if ($type === 'checkbox'):
                ?>
                <tr>
                    <td><?php
                        echo GetMessage("ADDFIELDS_" . $name) ?></td>
                    <td>
                        <label class="esl-toggle">
                            <input type="checkbox" name="<?php
                            echo $nameArr ?>[<?php
                            echo $name ?>]" <?php echo $value ?>>
                            <span class="esl-toggle__track"></span>
                        </label>
                    </td>
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
                                echo htmlspecialcharsbx((string)$k) ?>"><?php
                                    echo htmlspecialcharsbx((string)$v) ?></option>
                            <?php
                            endforeach; ?>
                        </select>
                    </td>
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
        <td colspan="2">
            <?php
            $eslTable->prepare_items($orderItems);
            $eslTable->display();
            ?>
        </td>
    </tr>

    <?php
    // "Объединить все грузовые места в одно" — под таблицей мест, а не среди полей получателя
    // (как в МС/WP: unloading.html.php / unloading-form.php, секция с таблицей мест).
    // Тело вкладки — table.edit-table, поэтому свой блок тоже оборачиваем в <tr><td colspan="2">,
    // иначе браузер выносит "голый" <div> из <tbody> и рвёт границы вкладок (foster parenting).
    if (isset($fieldDelivery['order[combine_places]'])): ?>
        <tr>
            <td colspan="2">
                <div class="esl-combine-places">
                    <?php foreach ($fieldDelivery['order[combine_places]'] as $key => $value):
                        $explodeKey = explode('||', $key);
                        $name = $explodeKey[0];
                        $type = $explodeKey[1];
                        $fieldId = 'esl-combine-places-' . htmlspecialcharsbx($name);
                        ?>
                        <div class="esl-combine-places__field<?= $type === 'checkbox' ? ' esl-combine-places__field--checkbox' : '' ?>">
                            <label class="esl-combine-places__label" for="<?= $fieldId ?>"><?php
                                echo GetMessage("ADDFIELDS_" . $name) ?></label>
                            <?php if ($type === 'checkbox'): ?>
                                <label class="esl-toggle">
                                    <input id="<?= $fieldId ?>" type="checkbox" name="order[combine_places][<?= htmlspecialcharsbx($name) ?>]" <?php echo $value ?>>
                                    <span class="esl-toggle__track"></span>
                                </label>
                            <?php else: ?>
                                <input id="<?= $fieldId ?>" type="text" name="order[combine_places][<?= htmlspecialcharsbx($name) ?>]" value="<?= htmlspecialcharsbx((string)$value) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </td>
        </tr>
    <?php endif; ?>

    <?php
    $tabControl->BeginNextTab(); ?>
    <?php
    if (isset($additionalFields['data']) && $additionalFields['data']): ?>
        <tr>
            <td colspan="2" class="esl-services-td">
                <div class="esl-services-container">
                    <?php
                    foreach ($additionalFields['data'] as $key => $value): ?>
                        <div class="esl-services-group">
                            <p class="titleBox"><?php
                                echo ($additionalFieldsRu[$key]) ?? $key ?></p>
                            <div class="esl-services-grid">
                                <?php
                                foreach ($value as $k => $v):
                                    if (!isset($v['name'])) {
                                        continue;
                                    }
                                    // Значение по умолчанию берётся из настроек ТК (options.php,
                                    // кнопка "Настройка дополнительных услуг"), чтобы не отмечать
                                    // одни и те же услуги вручную в каждом заказе.
                                    $addFieldDefault = Option::get(Config::MODULE_ID, 'addfield-' . $typeMethod['name'] . '-' . $k);
                                    ?>
                                    <div class="form-field_add">
                                        <label class="label" for="esl-field-<?php echo $k ?>"><?php
                                            echo $v['name'] ?></label>
                                        <?php
                                        if ($v['type'] === 'integer'): ?>
                                            <input class="form-value_add form-value_number"
                                                   id="esl-field-<?php echo $k ?>"
                                                   name="complement[<?php echo $k ?>]"
                                                   type="number"
                                                   value="<?= htmlspecialcharsbx((string)($addFieldDefault !== '' ? $addFieldDefault : 0)) ?>"
                                                   max="<?php echo $v['max_value'] ?>">
                                        <?php
                                        else: ?>
                                            <label class="esl-toggle" for="esl-field-<?php echo $k ?>">
                                                <input class="form-value_add form-value_check"
                                                       id="esl-field-<?php echo $k ?>"
                                                       name="complement[<?php echo $k ?>]"
                                                       type="checkbox" <?= ($addFieldDefault === 'Y') ? 'checked' : '' ?>>
                                                <span class="esl-toggle__track"></span>
                                            </label>
                                        <?php
                                        endif; ?>
                                    </div>
                                <?php
                                endforeach; ?>
                            </div>
                        </div>
                    <?php
                    endforeach; ?>
                </div>
            </td>
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
            "disabled" => ($saleRight < "W"),
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
    <input type="hidden" name="platform_id" value="<?= htmlspecialcharsbx((string)Option::get(Config::MODULE_ID, 'platform_id-' . $typeMethod['name'])) ?>">
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
