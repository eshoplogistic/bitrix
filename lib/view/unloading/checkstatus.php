<?php

use Bitrix\Main,
    Bitrix\Sale,
    Bitrix\Main\Loader,
    Eshoplogistic\Delivery\Event\Unloading,
    Eshoplogistic\Delivery\Config;
use Bitrix\Main\Localization\Loc;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/subscribe/include.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/subscribe/prolog.php");

Loader::includeModule("sale");
Loader::includeModule("eshoplogistic.delivery");
IncludeModuleLangFile(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight("subscribe");
if ($POST_RIGHT == "D")
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$ID = intval($_REQUEST['elementId']);

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
<style>
    .esl-status-result {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        margin: 16px;
        border-radius: 8px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .esl-status-result--error {
        background: #fef2f2;
        border: 1px solid #fecaca;
    }
    .esl-status-result--info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
    }
    .esl-status-result__icon {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        font-weight: 700;
        line-height: 1;
    }
    .esl-status-result--error .esl-status-result__icon {
        background: #fee2e2;
        color: #dc2626;
    }
    .esl-status-result--info .esl-status-result__icon {
        background: #dbeafe;
        color: #2563eb;
    }
    .esl-status-result__body {
        flex: 1;
        padding-top: 4px;
    }
    .esl-status-result__error-text {
        font-size: 14px;
        font-weight: 500;
        color: #7f1d1d;
        line-height: 1.5;
    }
    .esl-status-result__table {
        width: 100%;
        border-collapse: collapse;
    }
    .esl-status-result__table tr + tr td {
        border-top: 1px solid #dbeafe;
    }
    .esl-status-result__table td {
        padding: 7px 4px;
        font-size: 13px;
        line-height: 1.4;
        vertical-align: top;
    }
    .esl-status-result__table td:first-child {
        color: #3b5a8a;
        font-weight: 500;
        width: 160px;
        white-space: nowrap;
        padding-right: 12px;
    }
    .esl-status-result__table td:last-child {
        color: #1e3a8a;
        font-weight: 600;
    }
</style>

<?php if ($isError): ?>
<div class="esl-status-result esl-status-result--error">
    <div class="esl-status-result__icon">✕</div>
    <div class="esl-status-result__body">
        <div class="esl-status-result__error-text"><?= htmlspecialchars($errorMessage) ?></div>
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
