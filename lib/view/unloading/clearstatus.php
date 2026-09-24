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

// Тестовый режим: локальный сброс без отмены заказа у ТК доступен только при ?esl_debug=1
// (прокидывается со страницы заказа, см. Unloading::OrderDetailAdminContextMenuShow).
$debug = $request->getQuery('esl_debug') === '1';

$unloading = new Unloading();
$type = null;
$message = null;

if ($request->isPost()) {
    if (!check_bitrix_sessid()) {
        die('Access denied');
    }

    $mode = $request->getPost('mode');
    if ($mode === 'local' && !$debug) {
        die('Access denied');
    }
    $result = ($mode === 'local')
        ? $unloading->clearUnloading($ID)
        : $unloading->deleteUnloadingAtCarrier($ID);
    $type = $result['type'];
    $message = $result['message'];
}

$deleteSupported = $unloading->isDeleteSupportedAtCarrier($ID);

$icons = ['success' => '&#10003;', 'error' => '&#10005;', 'warning' => '!', 'info' => 'i'];
?>
<?= \CUtil::InitJSCore(['dialog_lib'], true) ?>
<div id="esl-clear-root" class="esl-dialog">
<?php if ($type !== null): ?>
    <?php $modifier = isset($icons[$type]) ? $type : 'warning'; ?>
    <div class="esl-status-result esl-status-result--<?= $modifier ?>">
        <div class="esl-status-result__icon"><?= $icons[$modifier] ?></div>
        <div class="esl-status-result__text"><?= htmlspecialchars($message) ?></div>
    </div>
<?php else: ?>
    <div class="esl-clear-confirm">
        <form method="POST" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?elementId=<?= (int)$ID ?><?= $debug ? '&amp;esl_debug=1' : '' ?>">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="mode" value="">
            <div class="esl-clear-confirm__option">
                <?php if ($deleteSupported): ?>
                    <div class="esl-clear-confirm__text"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_CONFIRM") ?></div>
                    <button type="button" class="esl-clear-btn esl-clear-btn--danger" onclick="eslClearSubmit(this, 'carrier')"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_CONFIRM_BUTTON") ?></button>
                <?php else: ?>
                    <div class="esl-clear-confirm__text"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_DELETE_UNSUPPORTED_NOTE") ?></div>
                <?php endif; ?>
            </div>
            <?php if ($debug): ?>
                <div class="esl-clear-confirm__option esl-clear-confirm__option--debug">
                    <button type="button" class="esl-clear-btn" onclick="eslClearSubmit(this, 'local')"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_CONFIRM_BUTTON") ?></button>
                    <div class="esl-clear-confirm__note"><?= GetMessage("ESHOP_LOGISTIC_UNLOADING_CLEAR_CONFIRM_NOTE") ?></div>
                </div>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>
</div>
