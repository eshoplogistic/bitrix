<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Eshoplogistic\Delivery\Api\Terminal;
use Eshoplogistic\Delivery\Config;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

Loader::includeModule("eshoplogistic.delivery");
IncludeModuleLangFile(__FILE__);

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

$moduleRight = $APPLICATION->GetGroupRight(Config::MODULE_ID);
if ($moduleRight < 'R') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

$service = trim((string)$request->getQuery('service'));
$target = trim((string)$request->getQuery('target'));
if ($service === '' || $target === '') {
    die('Bad request');
}

$settlement = '';
$address = '';
$terminals = [];
$searched = false;
$error = null;

if ($request->isPost()) {
    if (!check_bitrix_sessid()) {
        die('Access denied');
    }

    $settlement = trim((string)$request->getPost('settlement'));
    $address = trim((string)$request->getPost('address'));
    $searched = true;

    // ПЭК отдаёт список терминалов только по явному флагу "только филиалы" —
    // без него API возвращает пустой результат для этой ТК (см. moj_sklad).
    $onlyBranches = ($service === 'pecom');

    $result = Terminal::search($service, $settlement, '', $address, $onlyBranches);
    if (isset($result['data']) && is_array($result['data'])) {
        $terminals = $result['data'];
    } else {
        $error = Loc::getMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_NOT_FOUND");
    }
}

$actionUrl = $APPLICATION->GetCurPage() . '?service=' . urlencode($service) . '&target=' . urlencode($target);
?>
<style>
    .esl-terminal-search { margin: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .esl-terminal-search__row { margin-bottom: 10px; }
    .esl-terminal-search__row label { display: block; margin-bottom: 4px; font-size: 13px; color: #3d4f6e; }
    .esl-terminal-search__row input[type="text"] { width: 100%; box-sizing: border-box; }
    .esl-terminal-search__results { margin-top: 14px; border: 1px solid #d3dade; border-radius: 5px; max-height: 260px; overflow-y: auto; }
    .esl-terminal-search__item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eef1f3; font-size: 13px; }
    .esl-terminal-search__item:last-child { border-bottom: none; }
    .esl-terminal-search__item:hover { background: #f4f8ff; font-weight: 600; }
    .esl-terminal-search__item small { color: #8a97a3; font-weight: normal; }
    .esl-terminal-search__empty { padding: 10px 0; font-size: 13px; color: #8a97a3; }
</style>
<div class="esl-terminal-search" id="esl-terminal-root">
    <form id="esl-terminal-form">
        <div class="esl-terminal-search__row">
            <label><?= GetMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_SETTLEMENT") ?></label>
            <input type="text" name="settlement" value="<?= htmlspecialchars($settlement) ?>" placeholder="<?= GetMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_SETTLEMENT_PH") ?>">
        </div>
        <div class="esl-terminal-search__row">
            <label><?= GetMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_ADDRESS") ?></label>
            <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" placeholder="<?= GetMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_ADDRESS_PH") ?>">
        </div>
        <?= bitrix_sessid_post() ?>
        <?php
        // Тот же приём, что и в additionalservices.php/clearstatus.php: BX.CAdminDialog не
        // перехватывает submit без параметра buttons, поэтому поиск идёт через ручной XHR,
        // а не через обычную отправку формы — иначе диалог пробивается на голую страницу.
        $searchJs = "var f=this.closest('form'),root=document.getElementById('esl-terminal-root'),x=new XMLHttpRequest();"
            . "x.open('POST'," . json_encode($actionUrl) . ",true);"
            . "x.onload=function(){if(x.status===200){root.outerHTML=x.responseText;}else{alert('Ошибка '+x.status+' при поиске');}};"
            . "x.onerror=function(){alert('Запрос не удался');};"
            . "x.send(new FormData(f));";
        ?>
        <button type="button" class="button button-primary" onclick="<?= htmlspecialchars($searchJs) ?>"><?= GetMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_SEARCH_BUTTON") ?></button>
    </form>

    <?php if ($searched): ?>
        <div class="esl-terminal-search__results">
            <?php if ($terminals): ?>
                <?php foreach ($terminals as $terminal): ?>
                    <?php
                    $code = (string)($terminal['code'] ?? '');
                    if ($code === '') continue;
                    // BX.CAdminDialog вставляет содержимое прямо в основной документ (не iframe),
                    // поэтому поле настроек доступно напрямую через document.getElementsByName.
                    // Экземпляр диалога сохраняется в window.__eslTerminalDialog кнопкой,
                    // которая его открывает (см. options.php), чтобы можно было закрыть его отсюда.
                    $onSelectJs = "document.getElementsByName(" . json_encode($target) . ")[0].value=" . json_encode($code) . ";"
                        . "if(window.__eslTerminalDialog){window.__eslTerminalDialog.Close();}";
                    ?>
                    <div class="esl-terminal-search__item" onclick="<?= htmlspecialchars($onSelectJs) ?>">
                        <?= htmlspecialchars((string)($terminal['name'] ?? $code)) ?>
                        — <small><?= htmlspecialchars((string)($terminal['settlement'] ?? '')) ?>, <?= htmlspecialchars((string)($terminal['address'] ?? '')) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="esl-terminal-search__empty"><?= htmlspecialchars($error ?: Loc::getMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_EMPTY")) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
