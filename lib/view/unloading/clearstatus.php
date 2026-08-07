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

    $mode = $request->getPost('mode');
    $result = ($mode === 'carrier')
        ? $unloading->deleteUnloadingAtCarrier($ID)
        : $unloading->clearUnloading($ID);
    $type = $result['type'];
    $message = $result['message'];
}

$deleteSupported = $unloading->isDeleteSupportedAtCarrier($ID);

$styles = [
    'success' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'icon_bg' => '#dcfce7', 'icon_color' => '#16a34a', 'text' => '#166534', 'icon' => '✓'],
    'error'   => ['bg' => '#fef2f2', 'border' => '#fecaca', 'icon_bg' => '#fee2e2', 'icon_color' => '#dc2626', 'text' => '#7f1d1d', 'icon' => '✕'],
    'warning' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'icon_bg' => '#fef3c7', 'icon_color' => '#d97706', 'text' => '#78350f', 'icon' => '!'],
    'info'    => ['bg' => '#f0f4ff', 'border' => '#c7d5fb', 'icon_bg' => '#dee7fd', 'icon_color' => '#3563e0', 'text' => '#1a2540', 'icon' => 'i'],
];
?>
<style>
    /* Те же переменные дизайн-системы, что и на странице настроек (см. install/css/settings.css)
       — этот диалог грузится отдельным фрагментом в CAdminDialog и её каскад не наследует. */
    #esl-clear-root {
        --esl-accent: #4a7dff;
        --esl-accent-dark: #3563e0;
        --esl-accent-soft: rgba(74, 125, 255, .12);
        --esl-danger: #dc2626;
        --esl-danger-dark: #b91c1c;
        --esl-danger-soft: rgba(220, 38, 38, .08);
        --esl-danger-border: rgba(220, 38, 38, .35);
        --esl-text: #1a2540;
        --esl-text-muted: #5a6782;
        --esl-border: #dde3ed;
        --esl-bg-card: #ffffff;
        --esl-bg-subtle: #f8f9fc;
        --esl-radius: 10px;
        --esl-radius-sm: 6px;
        --esl-shadow-card: 0 1px 2px rgba(20, 30, 60, .05), 0 2px 10px rgba(20, 30, 60, .045);
        display: block;
        padding: 16px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: var(--esl-text);
    }
    .esl-status-result {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        border-radius: var(--esl-radius);
        box-shadow: var(--esl-shadow-card);
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
    .esl-clear-confirm__text {
        font-size: 13px;
        line-height: 1.55;
        margin-bottom: 14px;
        color: var(--esl-text-muted);
    }
    .esl-clear-confirm__option {
        padding: 14px 16px;
        margin-bottom: 10px;
        background: var(--esl-bg-card);
        border: 1px solid var(--esl-border);
        border-radius: var(--esl-radius);
        box-shadow: var(--esl-shadow-card);
    }
    .esl-clear-confirm__option:last-child {
        margin-bottom: 0;
    }
    .esl-clear-confirm__note {
        font-size: 12px;
        line-height: 1.5;
        color: var(--esl-text-muted);
        margin-top: 8px;
    }
    .esl-clear-btn {
        padding: 7px 16px;
        background: var(--esl-bg-subtle);
        color: var(--esl-text-muted);
        border: 1px solid var(--esl-border);
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background .15s, border-color .15s, color .15s;
    }
    .esl-clear-btn:hover {
        background: var(--esl-accent-soft);
        border-color: var(--esl-accent);
        color: var(--esl-accent-dark);
    }
    .esl-clear-btn--danger {
        background: var(--esl-danger-soft);
        border-color: var(--esl-danger-border);
        color: var(--esl-danger);
    }
    .esl-clear-btn--danger:hover {
        background: var(--esl-danger);
        border-color: var(--esl-danger);
        color: #fff;
    }
    .esl-clear-btn[disabled] {
        opacity: .5;
        cursor: not-allowed;
    }
    .esl-clear-btn[disabled]:hover {
        background: var(--esl-bg-subtle);
        border-color: var(--esl-border);
        color: var(--esl-text-muted);
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
            <input type="hidden" name="mode" value="">
            <div class="esl-clear-confirm__option">
                <button type="button" class="esl-clear-btn" onclick="eslClearSubmit(this, 'local')"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_CONFIRM_BUTTON") ?></button>
                <div class="esl-clear-confirm__note"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_CONFIRM_NOTE") ?></div>
            </div>
            <div class="esl-clear-confirm__option">
                <?php if ($deleteSupported): ?>
                    <button type="button" class="esl-clear-btn esl-clear-btn--danger" onclick="eslClearSubmit(this, 'carrier')"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_CONFIRM_BUTTON") ?></button>
                    <div class="esl-clear-confirm__note"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_CONFIRM_NOTE") ?></div>
                <?php else: ?>
                    <button type="button" class="esl-clear-btn" disabled><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_CONFIRM_BUTTON") ?></button>
                    <div class="esl-clear-confirm__note"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_UNSUPPORTED_NOTE") ?></div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <script>
        // BX.CAdminDialog перехватывает submit формы только если диалог создан с параметром
        // buttons — у нас его нет, обычный submit пробивает диалог и уводит на голую
        // страницу. Сохраняем вручную через XHR (см. additionalservices.php). "mode" в
        // скрытом поле определяет, какое действие выполнит POST-обработчик выше.
        function eslClearSubmit(btn, mode) {
            var f = btn.closest('form');
            f.querySelector('[name="mode"]').value = mode;
            var x = new XMLHttpRequest();
            x.open('POST', f.action, true);
            x.onload = function () {
                if (x.status === 200) {
                    document.getElementById('esl-clear-root').outerHTML = x.responseText;
                } else {
                    alert('Ошибка ' + x.status);
                }
            };
            x.onerror = function () {
                alert('Запрос не удался');
            };
            x.send(new FormData(f));
        }
    </script>
<?php endif; ?>
</div>
