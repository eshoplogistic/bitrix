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

$unloading = new Unloading();
$status = $unloading->infoOrder($ID);

if (isset($status['success']) && $status['success'] === false) {
    $type = 'error';
    $message = $status['data']['messages'] ?? Loc::GetMessage("ESHOP_LOGISTIC_VIEW_UPDATESTATUS_ERROR");
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

$styles = [
    'success' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'icon_bg' => '#dcfce7', 'icon_color' => '#16a34a', 'text' => '#166534', 'icon' => '✓'],
    'error'   => ['bg' => '#fef2f2', 'border' => '#fecaca', 'icon_bg' => '#fee2e2', 'icon_color' => '#dc2626', 'text' => '#7f1d1d', 'icon' => '✕'],
    'warning' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'icon_bg' => '#fef3c7', 'icon_color' => '#d97706', 'text' => '#78350f', 'icon' => '!'],
    'info'    => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'icon_bg' => '#dbeafe', 'icon_color' => '#2563eb', 'text' => '#1e3a8a', 'icon' => 'i'],
];

$s = $styles[$type] ?? $styles['info'];
?>
<style>
    .esl-status-result {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        margin: 16px;
        background: <?= $s['bg'] ?>;
        border: 1px solid <?= $s['border'] ?>;
        border-radius: 8px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .esl-status-result__icon {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: <?= $s['icon_bg'] ?>;
        color: <?= $s['icon_color'] ?>;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        font-weight: 700;
        line-height: 1;
    }
    .esl-status-result__text {
        padding-top: 6px;
        font-size: 14px;
        font-weight: 500;
        color: <?= $s['text'] ?>;
        line-height: 1.5;
    }
</style>
<div class="esl-status-result">
    <div class="esl-status-result__icon"><?= $s['icon'] ?></div>
    <div class="esl-status-result__text"><?= htmlspecialchars($message) ?></div>
</div>
