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
$type = null;
$message = null;

if ($request->isPost()) {
    if (!check_bitrix_sessid()) {
        die('Access denied');
    }

    $result = $unloading->clearUnloading($ID);
    $type = $result['type'];
    $message = $result['message'];
}

$styles = [
    'success' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'icon_bg' => '#dcfce7', 'icon_color' => '#16a34a', 'text' => '#166534', 'icon' => '✓'],
    'error'   => ['bg' => '#fef2f2', 'border' => '#fecaca', 'icon_bg' => '#fee2e2', 'icon_color' => '#dc2626', 'text' => '#7f1d1d', 'icon' => '✕'],
    'warning' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'icon_bg' => '#fef3c7', 'icon_color' => '#d97706', 'text' => '#78350f', 'icon' => '!'],
];
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
    .esl-status-result__text {
        padding-top: 6px;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.5;
    }
    .esl-clear-confirm {
        margin: 16px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .esl-clear-confirm__text {
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 16px;
        color: #78350f;
    }
    .esl-clear-confirm__buttons {
        display: flex;
        gap: 8px;
    }
</style>
<div id="esl-clear-root">
<?php if ($type !== null): ?>
    <?php $s = $styles[$type] ?? $styles['warning']; ?>
    <div class="esl-status-result" style="background:<?= $s['bg'] ?>;border:1px solid <?= $s['border'] ?>;">
        <div class="esl-status-result__icon" style="background:<?= $s['icon_bg'] ?>;color:<?= $s['icon_color'] ?>;"><?= $s['icon'] ?></div>
        <div class="esl-status-result__text" style="color:<?= $s['text'] ?>;"><?= htmlspecialchars($message) ?></div>
    </div>
<?php else: ?>
    <div class="esl-clear-confirm">
        <div class="esl-clear-confirm__text"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_CONFIRM") ?></div>
        <form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?elementId=<?= $ID ?>">
            <?= bitrix_sessid_post() ?>
            <div class="esl-clear-confirm__buttons">
                <?php
                // BX.CAdminDialog перехватывает submit формы только если диалог создан с
                // параметром buttons — у нас его нет, обычный submit пробивает диалог и
                // уводит на голую страницу. Сохраняем вручную через XHR (см. additionalservices.php).
                $onclickJs = "var f=this.closest('form'),x=new XMLHttpRequest();"
                    . "x.open('POST',f.action,true);"
                    . "x.onload=function(){if(x.status===200){document.getElementById('esl-clear-root').outerHTML=x.responseText;}else{alert('Ошибка '+x.status);}};"
                    . "x.onerror=function(){alert('Запрос не удался');};"
                    . "x.send(new FormData(f));";
                ?>
                <button type="button" class="button button-primary" onclick="<?= htmlspecialchars($onclickJs) ?>"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_CONFIRM_BUTTON") ?></button>
            </div>
        </form>
    </div>
<?php endif; ?>
</div>
