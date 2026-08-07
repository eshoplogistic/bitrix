<?php

use Bitrix\Main\Loader,
    Eshoplogistic\Delivery\Event\Unloading,
    Eshoplogistic\Delivery\Config,
    Eshoplogistic\Delivery\Helpers\ShippingHelper;

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

// POST: возвращает только фрагмент результата (ссылку или ошибку) — кнопки на странице
// не перерисовываются, чтобы можно было сразу запросить следующую форму без переоткрытия
// диалога (портировано из МойСклад: printResult — отдельная панель под кнопками).
if ($request->isPost()) {
    if (!check_bitrix_sessid()) {
        die('Access denied');
    }

    $mode = (string)$request->getPost('mode');
    $paper = (string)$request->getPost('paper');
    $type = (string)$request->getPost('type');

    if ($mode === '') {
        die('Bad request');
    }

    $result = $unloading->getPrintForm($ID, $mode, $paper, $type);
    if ($result['type'] === 'success') {
        ?>
        <div class="esl-print-result esl-print-result--success">
            <a href="<?= htmlspecialcharsbx($result['url']) ?>" target="_blank" rel="noopener"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_OPEN") ?> →</a>
        </div>
        <?php
    } else {
        ?>
        <div class="esl-print-result esl-print-result--error"><?= htmlspecialcharsbx($result['message']) ?></div>
        <?php
    }

    exit;
}

$orderData = CSaleOrder::GetByID($ID);
$deliveryId = $orderData ? $unloading->getDeliveryId($ID) : null;
$printSupported = $deliveryId ? $unloading->isPrintSupportedAtCarrier($ID) : false;
$buttons = $deliveryId ? Config::getPrintFormButtons($deliveryId) : [];
$paperTypes = $deliveryId ? (Config::PRINT_FORM_PAPER_TYPES[$deliveryId] ?? []) : [];
$sessid = bitrix_sessid();
?>
<style>
    #esl-print-root {
        --esl-accent: #4a7dff;
        --esl-accent-dark: #3563e0;
        --esl-accent-soft: rgba(74, 125, 255, .12);
        --esl-danger: #dc2626;
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
    .esl-print-empty {
        font-size: 13px;
        line-height: 1.55;
        color: var(--esl-text-muted);
    }
    .esl-print-paper {
        margin-bottom: 14px;
    }
    .esl-print-paper label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--esl-text-muted);
    }
    .esl-print-paper select {
        padding: 6px 10px;
        border: 1px solid var(--esl-border);
        border-radius: var(--esl-radius-sm);
        background: var(--esl-bg-card);
        color: var(--esl-text);
        font-size: 13px;
    }
    .esl-print-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }
    .esl-print-btn {
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
    .esl-print-btn:hover {
        background: var(--esl-accent-soft);
        border-color: var(--esl-accent);
        color: var(--esl-accent-dark);
    }
    .esl-print-btn.esl-print-btn--active {
        background: var(--esl-accent);
        border-color: var(--esl-accent);
        color: #fff;
    }
    .esl-print-btn--danger {
        background: var(--esl-danger-soft);
        border-color: var(--esl-danger-border);
        color: var(--esl-danger);
    }
    .esl-print-btn--danger:hover {
        background: var(--esl-danger);
        border-color: var(--esl-danger);
        color: #fff;
    }
    .esl-print-result {
        padding: 12px 16px;
        border-radius: var(--esl-radius);
        box-shadow: var(--esl-shadow-card);
        font-size: 13px;
        line-height: 1.5;
    }
    .esl-print-result--success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
    }
    .esl-print-result--success a {
        color: #166534;
        font-weight: 600;
        text-decoration: none;
    }
    .esl-print-result--success a:hover {
        text-decoration: underline;
    }
    .esl-print-result--error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #7f1d1d;
    }
    .esl-print-loading {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 4px;
        font-size: 13px;
        color: var(--esl-text-muted);
    }
    .esl-print-spinner {
        flex-shrink: 0;
        width: 16px;
        height: 16px;
        border: 2px solid var(--esl-accent-soft);
        border-top-color: var(--esl-accent);
        border-radius: 50%;
        animation: esl-print-spin .7s linear infinite;
    }
    @keyframes esl-print-spin {
        to { transform: rotate(360deg); }
    }
</style>
<div id="esl-print-root">
    <?php if (!$deliveryId): ?>
        <div class="esl-print-empty"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_NO_DELIVERY") ?></div>
    <?php elseif (!$printSupported): ?>
        <div class="esl-print-empty"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_UNSUPPORTED") ?></div>
    <?php else: ?>
        <?php if ($paperTypes): ?>
            <div class="esl-print-paper">
                <label>
                    <?= GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_PAPER") ?>
                    <select id="esl-print-paper">
                        <option value=""><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_PAPER_EMPTY") ?></option>
                        <?php foreach ($paperTypes as $paperValue): ?>
                            <option value="<?= htmlspecialcharsbx($paperValue) ?>"><?= htmlspecialcharsbx($paperValue) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        <?php endif; ?>
        <div class="esl-print-buttons">
            <?php foreach ($buttons as $button): ?>
                <button type="button"
                        class="esl-print-btn<?= !empty($button['danger']) ? ' esl-print-btn--danger' : '' ?>"
                        data-mode="<?= htmlspecialcharsbx($button['mode']) ?>"
                        data-type="<?= htmlspecialcharsbx($button['type'] ?? '') ?>"
                        onclick="eslPrintSubmit(this)">
                    <?= htmlspecialcharsbx($button['label']) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div id="esl-print-result"></div>
        <script>
            function eslPrintSubmit(btn) {
                document.querySelectorAll('.esl-print-btn').forEach(function (b) {
                    b.classList.remove('esl-print-btn--active');
                });
                btn.classList.add('esl-print-btn--active');

                var paperSelect = document.getElementById('esl-print-paper');
                var resultEl = document.getElementById('esl-print-result');
                resultEl.innerHTML = '<div class="esl-print-loading"><span class="esl-print-spinner"></span><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_LOADING") ?></div>';

                var fd = new FormData();
                fd.append('sessid', <?= \Bitrix\Main\Web\Json::encode($sessid) ?>);
                fd.append('mode', btn.getAttribute('data-mode'));
                fd.append('type', btn.getAttribute('data-type') || '');
                fd.append('paper', paperSelect ? paperSelect.value : '');

                var x = new XMLHttpRequest();
                // window.location тут — это адрес РОДИТЕЛЬСКОЙ страницы (sale_order_view.php),
                // не нашего print.php: CAdminDialog не изолирует контент в iframe (см. тот же
                // манёвр через f.action в clearstatus.php). Поэтому урл жёстко зашит из PHP.
                x.open('POST', <?= \Bitrix\Main\Web\Json::encode($APPLICATION->GetCurPage() . '?elementId=' . $ID) ?>, true);
                x.onload = function () {
                    if (x.status === 200) {
                        resultEl.innerHTML = x.responseText;
                    } else {
                        resultEl.innerHTML = '<div class="esl-print-result esl-print-result--error">Ошибка ' + x.status + '</div>';
                    }
                };
                x.onerror = function () {
                    resultEl.innerHTML = '<div class="esl-print-result esl-print-result--error">Запрос не удался</div>';
                };
                x.send(fd);
            }
        </script>
    <?php endif; ?>
</div>
