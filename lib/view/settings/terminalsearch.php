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
    .esl-terminal-search {
        --esl-accent: #4a7dff;
        --esl-accent-dark: #3563e0;
        --esl-accent-soft: rgba(74, 125, 255, 0.12);
        --esl-text: #1a2540;
        --esl-text-muted: #5a6782;
        --esl-text-faint: #93a0bd;
        --esl-border: #dde3ed;
        --esl-bg-subtle: #f8f9fc;
        --esl-radius: 10px;
        --esl-radius-sm: 6px;
        margin: 18px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        color: var(--esl-text);
    }
    .esl-terminal-search__row { margin-bottom: 14px; }
    .esl-terminal-search__row label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: var(--esl-text-muted); }
    .esl-terminal-search__row input[type="text"] {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #c8d0e0;
        border-radius: var(--esl-radius-sm);
        padding: 8px 12px;
        font-size: 13px;
        color: var(--esl-text);
        background: #fafbfd;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    }
    .esl-terminal-search__row input[type="text"]:focus {
        border-color: var(--esl-accent);
        box-shadow: 0 0 0 3px var(--esl-accent-soft);
        background: #fff;
    }
    .esl-terminal-search__submit {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 18px;
        border: 1px solid var(--esl-accent);
        border-radius: 999px;
        background: var(--esl-accent);
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s;
    }
    .esl-terminal-search__submit:hover { background: var(--esl-accent-dark); border-color: var(--esl-accent-dark); }
    .esl-terminal-search__results {
        margin-top: 18px;
        border: 1px solid var(--esl-border);
        border-radius: var(--esl-radius);
        max-height: 260px;
        overflow-y: auto;
        background: #fff;
    }
    .esl-terminal-search__item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid var(--esl-bg-subtle);
        font-size: 13px;
        color: var(--esl-text);
        transition: background 0.12s;
    }
    .esl-terminal-search__item:last-child { border-bottom: none; }
    .esl-terminal-search__item:hover { background: var(--esl-accent-soft); }
    .esl-terminal-search__item-icon { flex: none; color: var(--esl-text-faint); }
    .esl-terminal-search__item:hover .esl-terminal-search__item-icon { color: var(--esl-accent); }
    .esl-terminal-search__item-name { font-weight: 600; }
    .esl-terminal-search__item-desc { color: var(--esl-text-muted); font-weight: normal; }
    .esl-terminal-search__empty { padding: 24px 4px; text-align: center; font-size: 13px; color: var(--esl-text-faint); }
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
        <button type="button" class="esl-terminal-search__submit" onclick="<?= htmlspecialchars($searchJs) ?>">
            <svg width="14" height="14" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.6 10.6L14 14M12.3 6.65C12.3 9.73 9.73 12.3 6.65 12.3C3.57 12.3 1 9.73 1 6.65C1 3.57 3.57 1 6.65 1C9.73 1 12.3 3.57 12.3 6.65Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?= GetMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_SEARCH_BUTTON") ?>
        </button>
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
                        <svg class="esl-terminal-search__item-icon" width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 15s5-4.6 5-8.5A5 5 0 0 0 3 6.5C3 10.4 8 15 8 15Z" stroke="currentColor" stroke-width="1.3"/><circle cx="8" cy="6.5" r="1.8" stroke="currentColor" stroke-width="1.3"/></svg>
                        <span>
                            <span class="esl-terminal-search__item-name"><?= htmlspecialchars((string)($terminal['name'] ?? $code)) ?></span>
                            <span class="esl-terminal-search__item-desc"> — <?= htmlspecialchars((string)($terminal['settlement'] ?? '')) ?>, <?= htmlspecialchars((string)($terminal['address'] ?? '')) ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="esl-terminal-search__empty"><?= htmlspecialchars($error ?: Loc::getMessage("ESHOP_LOGISTIC_SETTINGS_TERMINAL_EMPTY")) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
