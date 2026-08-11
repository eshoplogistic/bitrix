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
if ($SALE_RIGHT < 'R') {
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
            <a href="<?= htmlspecialcharsbx($result['url']) ?>" target="_blank" rel="noopener"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_OPEN") ?> &rarr;</a>
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
<?= \CUtil::InitJSCore(['dialog_lib'], true) ?>
<div id="esl-print-root" class="esl-dialog"
     data-sessid="<?= htmlspecialcharsbx($sessid) ?>"
     data-action-url="<?= htmlspecialcharsbx($APPLICATION->GetCurPage() . '?elementId=' . $ID) ?>"
     data-loading-text="<?= htmlspecialcharsbx(GetMessage("ESHOP_LOGISTIC_UNLOADING_PRINT_LOADING")) ?>">
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
    <?php endif; ?>
</div>
