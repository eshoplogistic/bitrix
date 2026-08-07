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
if ($SALE_RIGHT !== 'W') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$ID = (int)$request->getQuery('elementId');
if ($ID <= 0) {
    die('Bad request');
}

$unloading = new Unloading();
$status = $unloading->infoOrder($ID);

if (isset($status['success']) && $status['success'] === false) {
    $type = 'error';
    $apiMessages = $status['data']['messages'] ?? null;
    $message = $apiMessages
        ? Loc::GetMessage("ESHOP_LOGISTIC_VIEW_UPDATESTATUS_ERROR") . ': ' . (is_array($apiMessages) ? implode('; ', $apiMessages) : $apiMessages)
        : Loc::GetMessage("ESHOP_LOGISTIC_VIEW_UPDATESTATUS_ERROR");
} else {
    $result = $unloading->updateStatusById($status['data'], $ID);
    if (!$result) {
        $type = 'warning';
        $message = Loc::GetMessage("ESHOP_LOGISTIC_VIEW_UPDATESTATUS_NOT_FOUND");
    } else {
        $type = $result['type'];
        $message = $result['message'];
    }
}

$icons = ['success' => '✓', 'error' => '✕', 'warning' => '!', 'info' => 'i'];
$icon = $icons[$type] ?? $icons['info'];
$modifier = isset($icons[$type]) ? $type : 'info';

?>
<?= \CUtil::InitJSCore(['dialog_lib'], true) ?>
<div class="esl-dialog">
    <div class="esl-status-result esl-status-result--<?= $modifier ?>">
        <div class="esl-status-result__icon"><?= $icon ?></div>
        <div class="esl-status-result__text"><?= htmlspecialchars($message) ?></div>
    </div>
</div>
