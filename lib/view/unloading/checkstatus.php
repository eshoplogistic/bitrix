<?php

use Bitrix\Main,
    Bitrix\Sale,
    Bitrix\Main\Loader,
    Eshoplogistic\Delivery\Event\Unloading,
    Eshoplogistic\Delivery\Config;
use Bitrix\Main\Localization\Loc;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

Loader::includeModule("sale");
Loader::includeModule("eshoplogistic.delivery");
IncludeModuleLangFile(__FILE__);

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$SALE_RIGHT = $APPLICATION->GetGroupRight('sale');
if ($SALE_RIGHT < 'R') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$ID = (int)$request->getQuery('elementId');
if ($ID <= 0) {
    die('Bad request');
}

$order = Sale\Order::load($ID);
$propertyCollection = $order->getPropertyCollection();

foreach ($propertyCollection as $propertyItem) {
    $propertyCode = $propertyItem->getField("CODE");
    if ($propertyCode == 'ESHOPLOGISTIC_SHIPPING_METHODS') {
        $shippingMethods = $propertyItem->getValue();
        if ($shippingMethods)
            $shippingMethods = json_decode($shippingMethods, true);
    }
}

$unloading = new Unloading();
$status = $unloading->infoOrder($ID);

// Сбор данных для отображения
$isError = false;
$errorMessage = '';
$rows = [];

if (isset($status['data']['messages'])) {
    $isError = true;
    $errorMessage = $status['data']['messages'];
} elseif (!isset($status['data'])) {
    $isError = true;
    $errorMessage = Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_ERROR");
}

// Для служб с асинхронным подтверждением (например, ПЭК) отсутствие данных сразу
// после выгрузки — это ожидаемое состояние, а не ошибка.
if ($isError && !empty($shippingMethods['pending_confirmation'])) {
    $isError = false;
    $rows[] = [Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFO_NOW"), Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_PENDING")];
}

if (!$isError) {
    if (isset($status['data']['state']['number'])) {
        $rows[] = [Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFOTITILE"), $status['data']['state']['number']];
    }
    if (isset($shippingMethods['answer']['order']['id'])) {
        $rows[] = [Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFOTITILE"), $shippingMethods['answer']['order']['id']];
    }
    if (isset($status['data']['order']['orderId'])) {
        $rows[] = [Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFOTITILE_2"), $status['data']['order']['orderId']];
    }
    if (isset($status['data']['state']['status']['description'])) {
        $rows[] = [Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_INFO_NOW"), $status['data']['state']['status']['description']];
    }
    if (isset($status['data']['state']['service_status']['description'])) {
        $rows[] = [Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_DESCRIPTION"), $status['data']['state']['service_status']['description']];
    }

    if (empty($rows)) {
        $isError = true;
        $errorMessage = Loc::GetMessage("ESHOP_LOGISTIC_VIEW_CHECKSTATUS_ERROR");
    }
}
?>
<?= \CUtil::InitJSCore(['dialog_lib'], true) ?>
<div class="esl-dialog">
<?php if ($isError): ?>
<div class="esl-status-result esl-status-result--error">
    <div class="esl-status-result__icon">&#10005;</div>
    <div class="esl-status-result__body">
        <div class="esl-status-result__text"><?= htmlspecialchars($errorMessage) ?></div>
    </div>
</div>
<?php else: ?>
<div class="esl-status-result esl-status-result--info">
    <div class="esl-status-result__icon">i</div>
    <div class="esl-status-result__body">
        <table class="esl-status-result__table">
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row[0]) ?></td>
                <td><?= htmlspecialchars($row[1]) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>
</div>
